<?php

namespace App\Services\Outlets;

use App\Enums\ChargeCategory;
use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\ServiceCategory;
use App\Enums\ServiceOrderStatus;
use App\Enums\Settlement;
use App\Models\AdditionalService;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\RestaurantOrder;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\FolioService;
use App\Services\Booking\BookingException;
use App\Services\Currency\CurrencyConverter;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Restaurant, bar and room-service orders plus bookable guest services.
 *
 * Prices are kept in the base currency. Settling "to room" posts one charge
 * to the in-house guest's folio (idempotently, in the folio currency at the
 * current exchange rate). Settled orders are locked; corrections are made by
 * reversing the folio charge.
 */
class OutletService
{
    public function __construct(
        private FolioService $folios,
        private CurrencyConverter $converter,
        private SettingsRepository $settings,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{menu_item_id: int, quantity: int, notes?: string|null}>  $lines
     */
    public function createOrder(OrderType $type, array $lines, User $actor, ?Reservation $reservation = null, ?string $tableOrRoom = null, ?string $customer = null, ?string $notes = null, ?string $idempotencyKey = null): RestaurantOrder
    {
        if ($idempotencyKey !== null && ($existing = RestaurantOrder::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        if ($lines === []) {
            throw new BookingException('Add at least one item to the order.', 'lines');
        }

        if ($reservation !== null) {
            $this->ensureInHouse($reservation);
        }

        $currency = $this->converter->baseCurrency();
        $menu = MenuItem::query()->whereIn('id', array_column($lines, 'menu_item_id'))->get()->keyBy('id');

        return DB::transaction(function () use ($type, $lines, $actor, $reservation, $tableOrRoom, $customer, $notes, $idempotencyKey, $currency, $menu): RestaurantOrder {
            $order = RestaurantOrder::create([
                'reference' => $this->reference('ORD'),
                'type' => $type,
                'status' => OrderStatus::Open,
                'reservation_id' => $reservation?->id,
                'table_or_room' => $tableOrRoom,
                'customer_name' => $customer ?? $reservation?->contact_name,
                'currency' => $currency,
                'notes' => $notes,
                'created_by' => $actor->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            $subtotal = Money::zero($currency);

            foreach ($lines as $line) {
                $item = $menu->get($line['menu_item_id']) ?? throw new BookingException('A selected menu item no longer exists.', 'lines');

                if (! $item->is_available) {
                    throw new BookingException("{$item->name} is not available right now.", 'lines');
                }

                $unit = Money::ofMinor($item->price_minor, $item->currency);
                $amount = $unit->multiply($line['quantity']);
                $subtotal = $subtotal->add($amount);

                $order->items()->create([
                    'menu_item_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $line['quantity'],
                    'unit_price_minor' => $unit->minor,
                    'amount_minor' => $amount->minor,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            $tax = $subtotal->percentage((string) $this->settings->get('booking.tax_rate', '0'));

            $order->forceFill([
                'subtotal_minor' => $subtotal->minor,
                'tax_minor' => $tax->minor,
                'total_minor' => $subtotal->add($tax)->minor,
            ])->save();

            return $order;
        });
    }

    public function advance(RestaurantOrder $order, OrderStatus $status): RestaurantOrder
    {
        if (in_array($order->status, [OrderStatus::Settled, OrderStatus::Cancelled], true)) {
            throw new BookingException("This order is already {$order->status->label()}.", 'status');
        }

        if (in_array($status, [OrderStatus::Settled, OrderStatus::Open], true)) {
            throw new BookingException('Use settle to close an order.', 'status');
        }

        $order->forceFill(['status' => $status])->save();

        return $order;
    }

    public function settleOrder(RestaurantOrder $order, Settlement $settlement, User $actor, ?PaymentMethod $method = null, ?string $paymentReference = null): RestaurantOrder
    {
        return DB::transaction(function () use ($order, $settlement, $actor, $method, $paymentReference): RestaurantOrder {
            $locked = RestaurantOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (in_array($locked->status, [OrderStatus::Settled, OrderStatus::Cancelled], true)) {
                throw new BookingException("This order is already {$locked->status->label()}.", 'status');
            }

            $total = Money::ofMinor($locked->total_minor, $locked->currency);
            $chargeId = null;

            if ($settlement === Settlement::RoomCharge) {
                $reservation = $locked->reservation ?? throw new BookingException('Link the order to an in-house reservation to charge the room.', 'reservation_id');
                $chargeId = $this->chargeRoom($reservation, ChargeCategory::FoodAndBeverage, "{$locked->type->label()} order {$locked->reference}", $total, "order:{$locked->id}", $locked, $actor);
            } else {
                $this->ensurePaymentDetails($method, $paymentReference);
            }

            $locked->forceFill([
                'status' => OrderStatus::Settled,
                'settlement' => $settlement,
                'payment_method' => $method,
                'payment_reference' => $paymentReference,
                'folio_charge_id' => $chargeId,
                'closed_by' => $actor->id,
                'closed_at' => now(),
            ])->save();

            $this->audit->record('order.settled', $locked, newValues: ['settlement' => $settlement->value, 'total' => $total->format()], actor: $actor);

            return $locked;
        });
    }

    public function cancelOrder(RestaurantOrder $order, string $reason, User $actor): void
    {
        if ($order->status === OrderStatus::Settled) {
            throw new BookingException('Settled orders cannot be cancelled; reverse the folio charge instead.', 'status');
        }

        $order->forceFill(['status' => OrderStatus::Cancelled, 'notes' => trim(($order->notes ?? '')."\nCancelled: {$reason}")])->save();
        $this->audit->record('order.cancelled', $order, newValues: ['reason' => $reason], actor: $actor);
    }

    public function bookService(AdditionalService $service, int $quantity, User $actor, ?Reservation $reservation = null, ?CarbonImmutable $scheduledFor = null, ?string $customer = null, ?string $notes = null, ?string $idempotencyKey = null): ServiceOrder
    {
        if ($idempotencyKey !== null && ($existing = ServiceOrder::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        if (! $service->is_active) {
            throw new BookingException("{$service->name} is not currently offered.", 'additional_service_id');
        }

        if ($reservation !== null && ! in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true)) {
            throw new BookingException('Services can only be added to confirmed or in-house reservations.', 'reservation_id');
        }

        $unit = Money::ofMinor($service->price_minor, $service->currency);

        return ServiceOrder::create([
            'reference' => $this->reference('SRV'),
            'additional_service_id' => $service->id,
            'reservation_id' => $reservation?->id,
            'customer_name' => $customer ?? $reservation?->contact_name,
            'quantity' => $quantity,
            'currency' => $unit->currency,
            'unit_price_minor' => $unit->minor,
            'total_minor' => $unit->multiply($quantity)->minor,
            'scheduled_for' => $scheduledFor,
            'status' => $scheduledFor ? ServiceOrderStatus::Scheduled : ServiceOrderStatus::Requested,
            'notes' => $notes,
            'created_by' => $actor->id,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function completeService(ServiceOrder $order, Settlement $settlement, User $actor, ?PaymentMethod $method = null, ?string $paymentReference = null): ServiceOrder
    {
        return DB::transaction(function () use ($order, $settlement, $actor, $method, $paymentReference): ServiceOrder {
            $locked = ServiceOrder::query()->lockForUpdate()->with('service')->findOrFail($order->id);

            if (in_array($locked->status, [ServiceOrderStatus::Completed, ServiceOrderStatus::Cancelled], true)) {
                throw new BookingException("This service is already {$locked->status->label()}.", 'status');
            }

            $chargeId = null;

            if ($settlement === Settlement::RoomCharge) {
                $reservation = $locked->reservation ?? throw new BookingException('Link the service to an in-house reservation to charge the room.', 'reservation_id');
                $category = match ($locked->service->category) {
                    ServiceCategory::Spa => ChargeCategory::Spa,
                    ServiceCategory::Laundry => ChargeCategory::Laundry,
                    ServiceCategory::Transport => ChargeCategory::Transport,
                    default => ChargeCategory::Service,
                };
                $chargeId = $this->chargeRoom($reservation, $category, "{$locked->service->name} × {$locked->quantity} ({$locked->reference})", Money::ofMinor($locked->total_minor, $locked->currency), "service:{$locked->id}", $locked, $actor);
            } else {
                $this->ensurePaymentDetails($method, $paymentReference);
            }

            $locked->forceFill([
                'status' => ServiceOrderStatus::Completed,
                'settlement' => $settlement,
                'payment_method' => $method,
                'payment_reference' => $paymentReference,
                'folio_charge_id' => $chargeId,
                'completed_at' => now(),
            ])->save();

            $this->audit->record('service_order.completed', $locked, newValues: ['settlement' => $settlement->value], actor: $actor);

            return $locked;
        });
    }

    public function cancelService(ServiceOrder $order, User $actor): void
    {
        if ($order->status === ServiceOrderStatus::Completed) {
            throw new BookingException('Completed services cannot be cancelled; reverse the folio charge instead.', 'status');
        }

        $order->forceFill(['status' => ServiceOrderStatus::Cancelled])->save();
        $this->audit->record('service_order.cancelled', $order, actor: $actor);
    }

    private function chargeRoom(Reservation $reservation, ChargeCategory $category, string $description, Money $amount, string $key, RestaurantOrder|ServiceOrder $source, User $actor): int
    {
        $this->ensureInHouse($reservation);
        $folio = $this->folios->openFor($reservation);

        $conversion = $this->converter->convert($amount, $folio->currency);
        $note = $conversion->wasConverted()
            ? "Converted from {$amount->format()} at 1 {$amount->currency->value} = {$conversion->rate} {$folio->currency->value}"
            : null;

        return $this->folios->post($folio, $category, $description, $conversion->converted, 1, CarbonImmutable::today(), $actor, $key, $source, $note)->id;
    }

    private function ensureInHouse(Reservation $reservation): void
    {
        if ($reservation->status !== ReservationStatus::CheckedIn) {
            throw new BookingException('Charges can only be posted to guests who are checked in.', 'reservation_id');
        }
    }

    private function ensurePaymentDetails(?PaymentMethod $method, ?string $reference): void
    {
        if ($method === null) {
            throw new BookingException('Choose how the guest paid.', 'payment_method');
        }

        if ($method->requiresReference() && blank($reference)) {
            throw new BookingException("Enter the {$method->label()} transaction reference.", 'payment_reference');
        }
    }

    private function reference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.Str::upper(Str::random(6));
        } while (RestaurantOrder::query()->where('reference', $reference)->exists() || ServiceOrder::query()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * Currency used for outlet prices.
     */
    public function priceCurrency(): Currency
    {
        return $this->converter->baseCurrency();
    }
}
