<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\Permission;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingException;
use App\Support\Money\Money;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RoundingMode;

/**
 * The only way stock levels change. Each movement locks the item row, writes
 * an immutable ledger line, updates the cached balance and, for receipts,
 * the moving-average cost. Stock can never go negative.
 */
class InventoryService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Record a movement. `$quantity` is the positive amount moved; its sign is
     * taken from the movement type. Adjustments use `adjustTo()` instead.
     */
    public function record(
        InventoryItem $item,
        InventoryMovementType $type,
        string $quantity,
        ?User $actor = null,
        ?int $unitCostMinor = null,
        ?string $reference = null,
        ?Model $source = null,
        ?string $notes = null,
        ?CarbonImmutable $expiresOn = null,
    ): InventoryMovement {
        if ($type === InventoryMovementType::Adjustment) {
            throw new \LogicException('Use adjustTo() for stock count adjustments.');
        }

        $amount = new Number(Money::numeric($quantity));

        if ($amount->compare(0) <= 0) {
            throw new BookingException('The quantity must be greater than zero.', 'quantity');
        }

        $signed = $type->direction() > 0 ? $amount : $amount->mul(-1);

        return $this->apply($item, $type, $signed, $actor, $unitCostMinor, $reference, $source, $notes, $expiresOn);
    }

    /**
     * Set the stock to a physically counted quantity, recording the difference.
     */
    public function adjustTo(InventoryItem $item, string $countedQuantity, string $reason, ?User $actor = null): ?InventoryMovement
    {
        $counted = new Number(Money::numeric($countedQuantity));

        if ($counted->compare(0) < 0) {
            throw new BookingException('A counted quantity cannot be negative.', 'quantity');
        }

        return DB::transaction(function () use ($item, $counted, $reason, $actor): ?InventoryMovement {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $difference = $counted->sub(new Number(Money::numeric((string) $locked->quantity)));

            if ($difference->compare(0) === 0) {
                return null;
            }

            return $this->apply($locked, InventoryMovementType::Adjustment, $difference, $actor, null, 'Stock count', null, $reason, null);
        });
    }

    public function valuation(InventoryItem $item): Money
    {
        $value = (new Number(Money::numeric((string) $item->quantity)))
            ->mul($item->average_cost_minor)
            ->round(0, RoundingMode::HalfAwayFromZero);

        return Money::ofMinor((int) $value->value, $item->currency);
    }

    private function apply(
        InventoryItem $item,
        InventoryMovementType $type,
        Number $signedQuantity,
        ?User $actor,
        ?int $unitCostMinor,
        ?string $reference,
        ?Model $source,
        ?string $notes,
        ?CarbonImmutable $expiresOn,
    ): InventoryMovement {
        return DB::transaction(function () use ($item, $type, $signedQuantity, $actor, $unitCostMinor, $reference, $source, $notes, $expiresOn): InventoryMovement {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);
            $before = new Number(Money::numeric((string) $locked->quantity));
            $after = $before->add($signedQuantity);

            if ($after->compare(0) < 0) {
                throw new BookingException("Only {$locked->quantity} {$locked->unit} of {$locked->name} are in stock.", 'quantity');
            }

            if ($type === InventoryMovementType::Receipt && $unitCostMinor !== null && $after->compare(0) > 0) {
                $locked->average_cost_minor = (int) $before->mul($locked->average_cost_minor)
                    ->add($signedQuantity->mul($unitCostMinor))
                    ->div($after, 6)
                    ->round(0, RoundingMode::HalfAwayFromZero)
                    ->value;
            }

            $wasAboveReorder = $before->compare(new Number(Money::numeric((string) $locked->reorder_level))) > 0;
            $locked->quantity = $after->round(3)->value;
            $locked->save();

            $movement = InventoryMovement::create([
                'inventory_item_id' => $locked->id,
                'type' => $type,
                'quantity' => $signedQuantity->round(3)->value,
                'balance_after' => $locked->quantity,
                'unit_cost_minor' => $unitCostMinor ?? $locked->average_cost_minor,
                'reference' => $reference,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'expires_on' => $expiresOn,
                'notes' => $notes,
                'user_id' => $actor?->id,
            ]);

            if ($type === InventoryMovementType::Adjustment) {
                $this->audit->record('inventory.adjusted', $locked, ['quantity' => $before->value], ['quantity' => $locked->quantity, 'reason' => $notes], $actor);
            }

            $isNowLow = $after->compare(new Number(Money::numeric((string) $locked->reorder_level))) <= 0;

            if ($wasAboveReorder && $isNowLow) {
                DB::afterCommit(fn () => $this->notifyLowStock($locked));
            }

            return $movement;
        });
    }

    private function notifyLowStock(InventoryItem $item): void
    {
        $recipients = User::query()
            ->active()
            ->permission(Permission::InventoryManage->value)
            ->get();

        Notification::send($recipients, new LowStockAlert($item));
    }
}
