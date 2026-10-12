<?php

namespace App\Http\Controllers\Admin\Outlets;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Enums\ServiceOrderStatus;
use App\Enums\Settlement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Outlets\OutletActionRequest;
use App\Http\Requests\Admin\Outlets\OutletOrderRequest;
use App\Http\Requests\Admin\Outlets\ServiceOrderRequest;
use App\Http\Requests\Admin\Outlets\SettleRequest;
use App\Models\AdditionalService;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\ServiceOrder;
use App\Services\Outlets\OutletService;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Point of sale for the restaurant, bar and room service, plus guest services.
 */
class OutletController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::OrdersManage->value);

        $orders = RestaurantOrder::query()
            ->with(['items', 'reservation:id,reference,contact_name'])
            ->where(fn ($query) => $query->whereIn('status', [OrderStatus::Open->value, OrderStatus::Preparing->value, OrderStatus::Served->value])
                ->orWhere('updated_at', '>=', now()->subDay()))
            ->orderByRaw("FIELD(status, 'open', 'preparing', 'served', 'settled', 'cancelled')")
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (RestaurantOrder $order) => [
                'reference' => $order->reference,
                'type' => $order->type->value,
                'type_label' => $order->type->label(),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'table_or_room' => $order->table_or_room,
                'customer' => $order->customer_name,
                'reservation' => $order->reservation?->only(['id', 'reference']),
                'settlement' => $order->settlement?->label(),
                'items' => $order->items->map(fn (RestaurantOrderItem $item) => ['name' => $item->name, 'quantity' => $item->quantity, 'amount' => Money::ofMinor($item->amount_minor, $order->currency)]),
                'subtotal' => Money::ofMinor($order->subtotal_minor, $order->currency),
                'tax' => Money::ofMinor($order->tax_minor, $order->currency),
                'total' => Money::ofMinor($order->total_minor, $order->currency),
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        $services = ServiceOrder::query()
            ->with(['service:id,name', 'reservation:id,reference'])
            ->where(fn ($query) => $query->whereIn('status', [ServiceOrderStatus::Requested->value, ServiceOrderStatus::Scheduled->value])
                ->orWhere('updated_at', '>=', now()->subDay()))
            ->orderBy('scheduled_for')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ServiceOrder $order) => [
                'reference' => $order->reference,
                'service' => $order->service->name,
                'customer' => $order->customer_name,
                'reservation' => $order->reservation?->only(['id', 'reference']),
                'quantity' => $order->quantity,
                'total' => Money::ofMinor($order->total_minor, $order->currency),
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'scheduled_for' => $order->scheduled_for?->toIso8601String(),
                'settlement' => $order->settlement?->label(),
            ]);

        return Inertia::render('admin/outlets/index', [
            'orders' => $orders,
            'serviceOrders' => $services,
            'menu' => MenuCategory::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')
                ->with(['items' => fn ($query) => $query->where('is_available', true)])
                ->get()
                ->map(fn (MenuCategory $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'items' => $category->items->map(fn (MenuItem $item) => ['id' => $item->id, 'name' => $item->name, 'price' => Money::ofMinor($item->price_minor, $item->currency)]),
                ]),
            'services' => AdditionalService::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get()
                ->map(fn (AdditionalService $service) => ['id' => $service->id, 'name' => $service->name, 'unit' => $service->unit, 'price' => Money::ofMinor($service->price_minor, $service->currency)]),
            'inHouse' => Reservation::query()->where('status', ReservationStatus::CheckedIn->value)
                ->with('activeAllocations.room:id,number')
                ->orderBy('contact_name')
                ->get()
                ->map(function (Reservation $reservation): array {
                    $roomNumber = $reservation->activeAllocations->first()?->room?->number;

                    return [
                        'id' => $reservation->id,
                        'label' => $roomNumber ? "Room {$roomNumber} — {$reservation->contact_name}" : $reservation->contact_name,
                        'reference' => $reservation->reference,
                    ];
                }),
            'options' => [
                'types' => OrderType::options(),
                'methods' => collect(PaymentMethod::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label(), 'requires_reference' => $case->requiresReference()]),
            ],
        ]);
    }

    public function store(OutletOrderRequest $request, OutletService $outlets): RedirectResponse
    {
        $order = $outlets->createOrder(
            OrderType::from($request->string('type')->toString()),
            $request->lines(),
            $request->user(),
            $request->filled('reservation_id') ? Reservation::query()->findOrFail($request->integer('reservation_id')) : null,
            $request->filled('table_or_room') ? $request->string('table_or_room')->toString() : null,
            $request->filled('customer_name') ? $request->string('customer_name')->toString() : null,
            $request->filled('notes') ? $request->string('notes')->toString() : null,
            'order:'.$request->string('idempotency_key')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order :reference opened.', ['reference' => $order->reference])]);

        return back();
    }

    public function advance(OutletActionRequest $request, RestaurantOrder $order, OutletService $outlets): RedirectResponse
    {
        $outlets->advance($order, OrderStatus::from($request->string('status')->toString()));

        return back();
    }

    public function settle(SettleRequest $request, RestaurantOrder $order, OutletService $outlets): RedirectResponse
    {
        $outlets->settleOrder($order, $request->settlement(), $request->user(), $request->paymentMethod(), $request->paymentReference());

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->settlement() === Settlement::RoomCharge ? __('Charged to the guest folio.') : __('Order settled.')]);

        return back();
    }

    public function cancel(OutletActionRequest $request, RestaurantOrder $order, OutletService $outlets): RedirectResponse
    {
        $outlets->cancelOrder($order, $request->string('reason')->toString(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled.')]);

        return back();
    }

    public function storeService(ServiceOrderRequest $request, OutletService $outlets): RedirectResponse
    {
        $order = $outlets->bookService(
            AdditionalService::query()->findOrFail($request->integer('additional_service_id')),
            $request->integer('quantity'),
            $request->user(),
            $request->filled('reservation_id') ? Reservation::query()->findOrFail($request->integer('reservation_id')) : null,
            $request->filled('scheduled_for') ? CarbonImmutable::parse($request->string('scheduled_for')->toString()) : null,
            $request->filled('customer_name') ? $request->string('customer_name')->toString() : null,
            $request->filled('notes') ? $request->string('notes')->toString() : null,
            'service:'.$request->string('idempotency_key')->toString(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service :reference booked.', ['reference' => $order->reference])]);

        return back();
    }

    public function completeService(SettleRequest $request, ServiceOrder $serviceOrder, OutletService $outlets): RedirectResponse
    {
        $outlets->completeService($serviceOrder, $request->settlement(), $request->user(), $request->paymentMethod(), $request->paymentReference());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service completed.')]);

        return back();
    }

    public function cancelService(Request $request, ServiceOrder $serviceOrder, OutletService $outlets): RedirectResponse
    {
        Gate::authorize(Permission::OrdersManage->value);

        $outlets->cancelService($serviceOrder, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service cancelled.')]);

        return back();
    }
}
