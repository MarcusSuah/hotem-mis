<?php

use App\Enums\BookingSource;
use App\Enums\ChargeCategory;
use App\Enums\Currency;
use App\Enums\InventoryMovementType;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseOrderStatus;
use App\Enums\Settlement;
use App\Enums\SystemRole;
use App\Models\ExchangeRate;
use App\Models\FolioCharge;
use App\Models\Guest;
use App\Models\InventoryItem;
use App\Models\LoyaltyTransaction;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Services\Booking\BookingException;
use App\Services\Booking\NewReservation;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\FrontDesk\CheckInDetails;
use App\Services\FrontDesk\FrontDeskService;
use App\Services\Guests\LoyaltyService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\PurchasingService;
use App\Services\Outlets\OutletService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '10', 'service_charge_rate' => '0', 'deposit_percent' => '0']);
});

function stockist(): User
{
    return User::factory()->withRole(SystemRole::InventoryManager)->create();
}

it('keeps a moving average cost as goods are received', function () {
    $item = InventoryItem::factory()->create();
    $inventory = app(InventoryService::class);

    $inventory->record($item, InventoryMovementType::Receipt, '10', stockist(), 100);
    $inventory->record($item, InventoryMovementType::Receipt, '30', stockist(), 200);

    expect($item->fresh())
        ->quantity->toBe('40.000')
        ->average_cost_minor->toBe(175);
    expect($inventory->valuation($item->fresh())->format())->toBe('USD 70.00');
});

it('never lets stock go negative', function () {
    $item = InventoryItem::factory()->create(['name' => 'Bath towel', 'unit' => 'pcs']);
    app(InventoryService::class)->record($item, InventoryMovementType::Receipt, '5', stockist(), 1000);

    app(InventoryService::class)->record($item, InventoryMovementType::Issue, '6', stockist());
})->throws(BookingException::class, 'Only 5.000 pcs of Bath towel are in stock.');

it('records stock count differences as audited adjustments', function () {
    $item = InventoryItem::factory()->create();
    $inventory = app(InventoryService::class);
    $inventory->record($item, InventoryMovementType::Receipt, '20', stockist(), 100);

    $movement = $inventory->adjustTo($item, '17', 'Monthly count', stockist());

    expect($movement->quantity)->toBe('-3.000')
        ->and($item->fresh()->quantity)->toBe('17.000');
    $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.adjusted', 'auditable_id' => $item->id]);
});

it('alerts inventory managers once when stock falls to the reorder level', function () {
    Notification::fake();
    $manager = stockist();
    $item = InventoryItem::factory()->create(['reorder_level' => '5']);
    $inventory = app(InventoryService::class);
    $inventory->record($item, InventoryMovementType::Receipt, '8', $manager, 100);

    $inventory->record($item, InventoryMovementType::Issue, '3', $manager);
    $inventory->record($item, InventoryMovementType::Issue, '1', $manager);

    Notification::assertSentToTimes($manager, LowStockAlert::class, 1);
});

it('receives purchase orders partially and then completely', function () {
    $item = InventoryItem::factory()->create();
    $purchasing = app(PurchasingService::class);
    $order = $purchasing->create(Supplier::factory()->create(), [['inventory_item_id' => $item->id, 'quantity' => '10', 'unit_cost' => Money::of('2.50', 'USD')]], null, null, stockist());
    $purchasing->markOrdered($order, stockist());
    $line = $order->items()->sole();

    $purchasing->receive($order->fresh(), [$line->id => ['quantity' => '4']], stockist());
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::PartiallyReceived);

    expect(fn () => $purchasing->receive($order->fresh(), [$line->id => ['quantity' => '7']], stockist()))
        ->toThrow(BookingException::class);

    $purchasing->receive($order->fresh(), [$line->id => ['quantity' => '6']], stockist());
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->fresh()->total_minor)->toBe(2500)
        ->and($item->fresh()->quantity)->toBe('10.000');
});

function inHouseReservation(Currency $currency = Currency::USD): Reservation
{
    $room = Room::factory()->for(RoomType::factory()->rate(10000))->create();
    $reservation = app(ReservationService::class)->create(new NewReservation(
        guest: Guest::factory()->create(),
        roomType: $room->roomType,
        stay: new StayPeriod('2026-11-10', '2026-11-12'),
        adults: 2,
        children: 0,
        currency: $currency,
        source: BookingSource::WalkIn,
        roomId: $room->id,
        confirmImmediately: true,
    ));
    app(FrontDeskService::class)->checkIn($reservation, new CheckInDetails);

    return $reservation->fresh();
}

it('charges a room-service order to the folio exactly once', function () {
    $reservation = inHouseReservation();
    $waiter = User::factory()->withRole(SystemRole::Receptionist)->create();
    $dish = MenuItem::factory()->create(['price_minor' => 1500]);
    $outlets = app(OutletService::class);
    $order = $outlets->createOrder(OrderType::RoomService, [['menu_item_id' => $dish->id, 'quantity' => 2]], $waiter, $reservation);

    $outlets->settleOrder($order, Settlement::RoomCharge, $waiter);

    $charge = FolioCharge::query()->sole();
    expect($order->fresh()->total_minor)->toBe(3300)
        ->and($charge->amount_minor)->toBe(3300)
        ->and($charge->category)->toBe(ChargeCategory::FoodAndBeverage)
        ->and(fn () => $outlets->settleOrder($order->fresh(), Settlement::RoomCharge, $waiter))
        ->toThrow(BookingException::class, 'This order is already Settled.');
    expect(FolioCharge::query()->count())->toBe(1);
});

it('converts outlet charges into a Liberian dollar folio at the current rate', function () {
    ExchangeRate::factory()->rate('200')->create(['effective_at' => now()->subDay()]);
    $reservation = inHouseReservation(Currency::LRD);
    $waiter = User::factory()->withRole(SystemRole::Receptionist)->create();
    $dish = MenuItem::factory()->create(['price_minor' => 1000]);
    $outlets = app(OutletService::class);

    $outlets->settleOrder($outlets->createOrder(OrderType::Restaurant, [['menu_item_id' => $dish->id, 'quantity' => 1]], $waiter, $reservation), Settlement::RoomCharge, $waiter);

    expect(FolioCharge::query()->sole()->amount()->format())->toBe('LRD 2,200.00');
});

it('refuses to charge rooms of guests who are not in house', function () {
    $room = Room::factory()->create();
    $upcoming = Reservation::factory()->confirmed()->inRoom($room)->create();
    $dish = MenuItem::factory()->create();

    app(OutletService::class)->createOrder(OrderType::Restaurant, [['menu_item_id' => $dish->id, 'quantity' => 1]], User::factory()->create(), $upcoming);
})->throws(BookingException::class, 'Charges can only be posted to guests who are checked in.');

it('requires a reference for card payments taken at the outlet', function () {
    $dish = MenuItem::factory()->create();
    $waiter = User::factory()->withRole(SystemRole::Receptionist)->create();
    $order = app(OutletService::class)->createOrder(OrderType::Bar, [['menu_item_id' => $dish->id, 'quantity' => 1]], $waiter, customer: 'Walk-in');

    app(OutletService::class)->settleOrder($order, Settlement::PaidAtOutlet, $waiter, PaymentMethod::Card);
})->throws(BookingException::class, 'Enter the Card (terminal) transaction reference.');

it('awards loyalty points once per completed stay', function () {
    $reservation = Reservation::factory()->checkedOut()->create(['base_total_minor' => 45099]);
    $loyalty = app(LoyaltyService::class);

    $loyalty->earnForStay($reservation);
    $loyalty->earnForStay($reservation);

    expect(LoyaltyTransaction::query()->sole()->points)->toBe(450)
        ->and($loyalty->balance($reservation->guest))->toBe(450);
});
