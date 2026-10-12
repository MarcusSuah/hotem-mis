<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\DocumentNumberGenerator;
use App\Services\Booking\BookingException;
use App\Services\Currency\CurrencyConverter;
use App\Support\Money\Money;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RoundingMode;

/**
 * Purchase orders and goods received. Receiving creates inventory receipts at
 * the ordered unit cost, which feeds the moving-average stock valuation.
 */
class PurchasingService
{
    public function __construct(
        private InventoryService $inventory,
        private DocumentNumberGenerator $numbers,
        private CurrencyConverter $converter,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{inventory_item_id: int, quantity: string, unit_cost: Money}>  $lines
     */
    public function create(Supplier $supplier, array $lines, ?CarbonImmutable $expectedOn, ?string $notes, User $actor): PurchaseOrder
    {
        if ($lines === []) {
            throw new BookingException('Add at least one item to the order.', 'lines');
        }

        return DB::transaction(function () use ($supplier, $lines, $expectedOn, $notes, $actor): PurchaseOrder {
            $order = PurchaseOrder::create([
                'number' => $this->numbers->next('purchase_order', 'PO'),
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Draft,
                'currency' => $this->converter->baseCurrency(),
                'expected_on' => $expectedOn,
                'notes' => $notes,
                'created_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity_ordered' => $line['quantity'],
                    'unit_cost_minor' => $line['unit_cost']->minor,
                ]);
            }

            $order->forceFill(['total_minor' => $this->total($order)])->save();
            $this->audit->record('purchase_order.created', $order, newValues: ['number' => $order->number, 'supplier' => $supplier->name], actor: $actor);

            return $order;
        });
    }

    public function markOrdered(PurchaseOrder $order, User $actor): void
    {
        if ($order->status !== PurchaseOrderStatus::Draft) {
            throw new BookingException('Only draft orders can be sent to the supplier.', 'status');
        }

        $order->forceFill(['status' => PurchaseOrderStatus::Ordered, 'ordered_at' => now()])->save();
        $this->audit->record('purchase_order.ordered', $order, actor: $actor);
    }

    public function cancel(PurchaseOrder $order, User $actor): void
    {
        if (! in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true)) {
            throw new BookingException('Orders with received goods cannot be cancelled.', 'status');
        }

        $order->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();
        $this->audit->record('purchase_order.cancelled', $order, actor: $actor);
    }

    /**
     * Receive goods against the order.
     *
     * @param  array<int, array{quantity: string, expires_on?: string|null}>  $received  keyed by purchase order item id
     */
    public function receive(PurchaseOrder $order, array $received, User $actor): PurchaseOrder
    {
        if (! in_array($order->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw new BookingException('Goods can only be received against an ordered purchase order.', 'status');
        }

        return DB::transaction(function () use ($order, $received, $actor): PurchaseOrder {
            $lines = $order->items()->with('item')->lockForUpdate()->get()->keyBy('id');
            $anything = false;

            foreach ($received as $lineId => $receipt) {
                /** @var PurchaseOrderItem|null $line */
                $line = $lines->get($lineId);
                $quantity = new Number(Money::numeric((string) $receipt['quantity']));

                if ($line === null || $quantity->compare(0) <= 0) {
                    continue;
                }

                $outstanding = (new Number(Money::numeric((string) $line->quantity_ordered)))->sub(new Number(Money::numeric((string) $line->quantity_received)));

                if ($quantity->compare($outstanding) > 0) {
                    throw new BookingException("Only {$outstanding->round(3)->value} {$line->item->unit} of {$line->item->name} remain on this order.", "received.{$lineId}.quantity");
                }

                $this->inventory->record(
                    $line->item,
                    InventoryMovementType::Receipt,
                    $quantity->value,
                    $actor,
                    $line->unit_cost_minor,
                    $order->number,
                    $order,
                    expiresOn: filled($receipt['expires_on'] ?? null) ? CarbonImmutable::parse((string) $receipt['expires_on']) : null,
                );

                $line->forceFill(['quantity_received' => (new Number(Money::numeric((string) $line->quantity_received)))->add($quantity)->round(3)->value])->save();
                $anything = true;
            }

            if (! $anything) {
                throw new BookingException('Enter the quantity received for at least one item.', 'received');
            }

            $complete = $lines->every(fn (PurchaseOrderItem $line) => (new Number(Money::numeric((string) $line->quantity_received)))->compare(new Number(Money::numeric((string) $line->quantity_ordered))) >= 0);

            $order->forceFill([
                'status' => $complete ? PurchaseOrderStatus::Received : PurchaseOrderStatus::PartiallyReceived,
                'received_at' => $complete ? now() : null,
            ])->save();

            $this->audit->record('purchase_order.received', $order, newValues: ['complete' => $complete], actor: $actor);

            return $order;
        });
    }

    public function lineTotal(PurchaseOrderItem $line): int
    {
        return (int) (new Number(Money::numeric((string) $line->quantity_ordered)))
            ->mul($line->unit_cost_minor)
            ->round(0, RoundingMode::HalfAwayFromZero)
            ->value;
    }

    private function total(PurchaseOrder $order): int
    {
        return (int) $order->items()->get()->sum(fn (PurchaseOrderItem $line) => $this->lineTotal($line));
    }

    /**
     * @return array<int, InventoryItem>
     */
    public function lowStockSuggestions(): array
    {
        return InventoryItem::query()
            ->where('is_active', true)
            ->whereColumn('quantity', '<=', 'reorder_level')
            ->orderBy('name')
            ->get()
            ->all();
    }
}
