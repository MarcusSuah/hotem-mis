<?php

namespace App\Http\Controllers\Admin\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\InventoryItemRequest;
use App\Http\Requests\Admin\Inventory\PurchaseOrderRequest;
use App\Http\Requests\Admin\Inventory\ReceiveGoodsRequest;
use App\Http\Requests\Admin\Inventory\StockMovementRequest;
use App\Http\Requests\Admin\Inventory\SupplierRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Currency\CurrencyConverter;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\PurchasingService;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stock items, movements, suppliers and purchase orders.
 */
class InventoryController extends Controller
{
    public function index(Request $request, InventoryService $inventory, CurrencyConverter $converter): Response
    {
        Gate::authorize(Permission::InventoryManage->value);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'stock' => ['nullable', 'in:low,expiring'],
        ]);

        $expiringIds = InventoryMovement::query()
            ->where('type', InventoryMovementType::Receipt->value)
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [today(), today()->addDays(30)])
            ->distinct()
            ->pluck('inventory_item_id');

        $items = InventoryItem::query()
            ->with(['category:id,name', 'preferredSupplier:id,name'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->when($filters['category'] ?? null, fn ($query, int $category) => $query->where('inventory_category_id', $category))
            ->when(($filters['stock'] ?? null) === 'low', fn ($query) => $query->whereColumn('quantity', '<=', 'reorder_level'))
            ->when(($filters['stock'] ?? null) === 'expiring', fn ($query) => $query->whereIn('id', $expiringIds))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (InventoryItem $item) => [
                ...$item->only(['id', 'sku', 'name', 'unit', 'inventory_category_id', 'preferred_supplier_id', 'tracks_expiry', 'is_active']),
                'quantity' => (string) $item->quantity,
                'reorder_level' => (string) $item->reorder_level,
                'category' => $item->category->name,
                'supplier' => $item->preferredSupplier?->name,
                'average_cost' => Money::ofMinor($item->average_cost_minor, $item->currency),
                'value' => $inventory->valuation($item),
                'is_low' => bccomp(Money::numeric($item->quantity), Money::numeric($item->reorder_level), 3) <= 0,
                'expiring_soon' => $expiringIds->contains($item->id),
            ]);

        $totalValue = Money::sum(
            InventoryItem::query()->where('is_active', true)->get()->map(fn (InventoryItem $item) => $inventory->valuation($item)),
            $converter->baseCurrency(),
        );

        return Inertia::render('admin/inventory/index', [
            'items' => $items,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => isset($filters['category']) ? (string) $filters['category'] : '',
                'stock' => $filters['stock'] ?? '',
            ],
            'summary' => [
                'value' => $totalValue,
                'low' => InventoryItem::query()->where('is_active', true)->whereColumn('quantity', '<=', 'reorder_level')->count(),
                'expiring' => $expiringIds->count(),
            ],
            'categories' => InventoryCategory::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get()->map(fn (Supplier $supplier) => $supplier->only(['id', 'name', 'contact_person', 'email', 'phone', 'address', 'tax_id', 'notes', 'is_active'])),
            'purchaseOrders' => PurchaseOrder::query()->with('supplier:id,name')->orderByDesc('created_at')->limit(50)->get()
                ->map(fn (PurchaseOrder $order) => [
                    'number' => $order->number,
                    'supplier' => $order->supplier->name,
                    'status' => $order->status->value,
                    'status_label' => $order->status->label(),
                    'total' => Money::ofMinor($order->total_minor, $order->currency),
                    'expected_on' => $order->expected_on?->toDateString(),
                    'created_at' => $order->created_at?->toIso8601String(),
                ]),
            'movementTypes' => collect(InventoryMovementType::manual())->map(fn (InventoryMovementType $type) => ['value' => $type->value, 'label' => $type->label(), 'direction' => $type->direction()]),
            'stockCatalog' => InventoryItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'unit']),
            'baseCurrency' => $converter->baseCurrency()->value,
        ]);
    }

    public function storeItem(InventoryItemRequest $request, CurrencyConverter $converter, AuditLogger $audit): RedirectResponse
    {
        $item = DB::transaction(fn () => InventoryItem::create([
            ...$this->itemAttributes($request),
            'currency' => $converter->baseCurrency(),
        ]));

        $audit->record('inventory.item_created', $item, newValues: ['sku' => $item->sku, 'name' => $item->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock item :name created.', ['name' => $item->name])]);

        return back();
    }

    public function updateItem(InventoryItemRequest $request, InventoryItem $item, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(fn () => $item->update($this->itemAttributes($request)));
        $audit->recordChanges('inventory.item_updated', $item);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock item updated.')]);

        return back();
    }

    public function movement(StockMovementRequest $request, InventoryItem $item, InventoryService $inventory): RedirectResponse
    {
        $type = $request->movementType();
        $notes = $request->filled('notes') ? $request->string('notes')->toString() : null;

        if ($type === InventoryMovementType::Adjustment) {
            $inventory->adjustTo($item, $request->string('quantity')->toString(), (string) $notes, $request->user());
        } else {
            $inventory->record($item, $type, $request->string('quantity')->toString(), $request->user(), reference: $request->filled('reference') ? $request->string('reference')->toString() : null, notes: $notes);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type recorded for :item.', ['type' => $type->label(), 'item' => $item->name])]);

        return back();
    }

    public function history(InventoryItem $item): Response
    {
        Gate::authorize(Permission::InventoryManage->value);

        return Inertia::render('admin/inventory/item', [
            'item' => [
                ...$item->only(['id', 'sku', 'name', 'unit']),
                'quantity' => (string) $item->quantity,
                'reorder_level' => (string) $item->reorder_level,
                'average_cost' => Money::ofMinor($item->average_cost_minor, $item->currency),
            ],
            'movements' => $item->movements()->with('user:id,name')->paginate(30)->through(fn (InventoryMovement $movement) => [
                'id' => $movement->id,
                'type_label' => $movement->type->label(),
                'quantity' => (string) $movement->quantity,
                'balance_after' => (string) $movement->balance_after,
                'unit_cost' => $movement->unit_cost_minor === null ? null : Money::ofMinor($movement->unit_cost_minor, $item->currency),
                'reference' => $movement->reference,
                'expires_on' => $movement->expires_on?->toDateString(),
                'notes' => $movement->notes,
                'user' => $movement->user?->name,
                'created_at' => $movement->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function storeSupplier(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->supplierAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier added.')]);

        return back();
    }

    public function updateSupplier(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->supplierAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier updated.')]);

        return back();
    }

    public function storeOrder(PurchaseOrderRequest $request, PurchasingService $purchasing, CurrencyConverter $converter): RedirectResponse
    {
        $order = $purchasing->create(
            Supplier::query()->findOrFail($request->integer('supplier_id')),
            $request->lines($converter->baseCurrency()->value),
            $request->filled('expected_on') ? $request->date('expected_on')?->toImmutable() : null,
            $request->filled('notes') ? $request->string('notes')->toString() : null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase order :number created.', ['number' => $order->number])]);

        return to_route('admin.purchase-orders.show', $order);
    }

    public function showOrder(PurchaseOrder $order, PurchasingService $purchasing): Response
    {
        Gate::authorize(Permission::InventoryManage->value);

        $order->load(['supplier', 'items.item', 'creator:id,name']);

        return Inertia::render('admin/inventory/purchase-order', [
            'order' => [
                'number' => $order->number,
                'supplier' => $order->supplier->only(['name', 'contact_person', 'email', 'phone']),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total' => Money::ofMinor($order->total_minor, $order->currency),
                'expected_on' => $order->expected_on?->toDateString(),
                'ordered_at' => $order->ordered_at?->toIso8601String(),
                'received_at' => $order->received_at?->toIso8601String(),
                'notes' => $order->notes,
                'created_by' => $order->creator?->name,
                'lines' => $order->items->map(fn (PurchaseOrderItem $line) => [
                    'id' => $line->id,
                    'item' => $line->item->name,
                    'sku' => $line->item->sku,
                    'unit' => $line->item->unit,
                    'tracks_expiry' => $line->item->tracks_expiry,
                    'quantity_ordered' => (string) $line->quantity_ordered,
                    'quantity_received' => (string) $line->quantity_received,
                    'unit_cost' => Money::ofMinor($line->unit_cost_minor, $order->currency),
                    'line_total' => Money::ofMinor($purchasing->lineTotal($line), $order->currency),
                ]),
            ],
            'can' => [
                'order' => $order->status === PurchaseOrderStatus::Draft,
                'receive' => in_array($order->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true),
                'cancel' => in_array($order->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered], true),
            ],
        ]);
    }

    public function markOrdered(Request $request, PurchaseOrder $order, PurchasingService $purchasing): RedirectResponse
    {
        Gate::authorize(Permission::InventoryManage->value);

        $purchasing->markOrdered($order, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order sent to supplier.')]);

        return back();
    }

    public function receive(ReceiveGoodsRequest $request, PurchaseOrder $order, PurchasingService $purchasing): RedirectResponse
    {
        $received = $purchasing->receive($order, $request->receipts(), $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => $received->status === PurchaseOrderStatus::Received ? __('All goods received.') : __('Partial delivery recorded.')]);

        return back();
    }

    public function cancelOrder(Request $request, PurchaseOrder $order, PurchasingService $purchasing): RedirectResponse
    {
        Gate::authorize(Permission::InventoryManage->value);

        $purchasing->cancel($order, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Purchase order cancelled.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function itemAttributes(InventoryItemRequest $request): array
    {
        $categoryId = $request->filled('new_category')
            ? InventoryCategory::query()->firstOrCreate(['name' => $request->string('new_category')->trim()->toString()])->id
            : $request->integer('inventory_category_id');

        return [
            'name' => $request->string('name')->toString(),
            'sku' => $request->string('sku')->toString(),
            'inventory_category_id' => $categoryId,
            'preferred_supplier_id' => $request->filled('preferred_supplier_id') ? $request->integer('preferred_supplier_id') : null,
            'unit' => $request->string('unit')->toString(),
            'reorder_level' => $request->string('reorder_level')->toString(),
            'tracks_expiry' => $request->boolean('tracks_expiry'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
