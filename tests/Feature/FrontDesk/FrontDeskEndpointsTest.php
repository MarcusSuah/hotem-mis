<?php

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Models\ExchangeRate;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Booking\NewReservation;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\FrontDesk\CheckInDetails;
use App\Services\FrontDesk\FrontDeskService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-10 15:00:00');
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '0', 'service_charge_rate' => '0', 'deposit_percent' => '50']);
});

function todaysArrival(): Reservation
{
    $room = Room::factory()->for(RoomType::factory()->rate(10000))->create();

    return app(ReservationService::class)->create(new NewReservation(
        guest: Guest::factory()->create(['first_name' => 'Comfort', 'last_name' => 'Weah']),
        roomType: $room->roomType,
        stay: new StayPeriod('2026-11-10', '2026-11-12'),
        adults: 2,
        children: 0,
        currency: Currency::USD,
        source: BookingSource::Phone,
        roomId: $room->id,
        confirmImmediately: true,
    ));
}

it('shows arrivals on the front desk to receptionists only', function () {
    todaysArrival();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();
    $housekeeper = User::factory()->withRole(SystemRole::HousekeepingStaff)->create();

    $this->actingAs($housekeeper)->get(route('admin.front-desk.index'))->assertForbidden();
    $this->actingAs($receptionist)
        ->get(route('admin.front-desk.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('arrivals', 1)->where('arrivals.0.guest', 'Comfort Weah'));
});

it('checks a guest in once the deposit is paid', function () {
    $reservation = todaysArrival();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();
    app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, Money::of('100', 'USD'));

    $this->actingAs($receptionist)
        ->post(route('admin.front-desk.check-in.store', $reservation), ['identity_type' => 'passport', 'identity_number' => 'A99'])
        ->assertRedirect(route('admin.front-desk.index'))
        ->assertSessionHasNoErrors();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::CheckedIn);
});

it('keeps the guest on the check-in page when the deposit is missing', function () {
    $reservation = todaysArrival();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();

    $this->actingAs($receptionist)
        ->from(route('admin.front-desk.check-in', $reservation))
        ->post(route('admin.front-desk.check-in.store', $reservation), ['waive_deposit' => true])
        ->assertRedirect(route('admin.front-desk.check-in', $reservation))
        ->assertSessionHasErrors(['waive_deposit' => 'You are not allowed to waive deposits.']);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('blocks check-out with a balance and explains why', function () {
    $reservation = todaysArrival();
    app(FrontDeskService::class)->checkIn($reservation, new CheckInDetails(waiveDeposit: true), User::factory()->withRole(SystemRole::HotelManager)->create());
    $this->travelTo('2026-11-12 10:00:00');
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();

    $this->actingAs($receptionist)
        ->from(route('admin.front-desk.check-out', $reservation))
        ->post(route('admin.front-desk.check-out.store', $reservation))
        ->assertRedirect(route('admin.front-desk.check-out', $reservation))
        ->assertSessionHasErrors(['balance' => 'The guest still owes USD 200.00. Record the payment or authorise settling later.']);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::CheckedIn);
});

it('records a payment from the folio once even if the form is submitted twice', function () {
    $reservation = todaysArrival();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();
    $payload = ['method' => 'mobile_money', 'amount' => '75.50', 'currency' => 'USD', 'external_reference' => 'MOMO-77812', 'idempotency_key' => (string) Str::uuid()];

    $this->actingAs($receptionist)->post(route('admin.folios.payments.store', $reservation->folio), $payload)->assertSessionHasNoErrors();
    $this->actingAs($receptionist)->post(route('admin.folios.payments.store', $reservation->folio), $payload)->assertSessionHasNoErrors();

    expect(Payment::query()->count())->toBe(1)
        ->and($reservation->fresh()->amount_paid_minor)->toBe(7550);
});

it('validates payment input on the folio', function () {
    $reservation = todaysArrival();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();

    $this->actingAs($receptionist)
        ->post(route('admin.folios.payments.store', $reservation->folio), ['method' => 'card', 'amount' => '0', 'currency' => 'EUR', 'idempotency_key' => (string) Str::uuid()])
        ->assertSessionHasErrors([
            'amount' => 'The payment amount must be greater than zero.',
            'currency',
            'external_reference' => 'Enter the transaction reference from the bank, terminal or mobile money provider.',
        ]);
});

it('restricts refunds to finance staff', function () {
    $reservation = todaysArrival();
    $payment = app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, Money::of('50', 'USD'));
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();
    $accountant = User::factory()->withRole(SystemRole::Accountant)->create();
    $payload = ['amount' => '20', 'method' => 'cash', 'reason' => 'Overcharged', 'idempotency_key' => (string) Str::uuid()];

    $this->actingAs($receptionist)->post(route('admin.payments.refunds.store', $payment), $payload)->assertForbidden();
    $this->actingAs($accountant)->post(route('admin.payments.refunds.store', $payment), $payload)->assertSessionHasNoErrors();

    expect($reservation->fresh()->amount_paid_minor)->toBe(3000);
});

it('serves invoices and receipts as PDF documents', function () {
    $reservation = todaysArrival();
    $payment = app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, Money::of('200', 'USD'));
    app(FrontDeskService::class)->checkIn($reservation, new CheckInDetails);
    $this->travelTo('2026-11-12 10:00:00');
    $invoice = app(FrontDeskService::class)->checkOut($reservation->fresh())['invoice'];
    $accountant = User::factory()->withRole(SystemRole::Accountant)->create();

    $this->actingAs($accountant)->get(route('admin.invoices.show', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($accountant)->get(route('admin.payments.receipt', $payment))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('hides identity numbers from staff without the identity permission and audits views', function () {
    $guest = Guest::factory()->create(['identity_type' => 'passport', 'identity_number' => 'P7788']);
    $officer = User::factory()->withRole(SystemRole::ReservationOfficer)->create();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();

    $this->actingAs($officer)
        ->get(route('admin.guests.show', $guest))
        ->assertInertia(fn (Assert $page) => $page->where('guest.identity_number', null));

    $this->actingAs($receptionist)
        ->get(route('admin.guests.show', $guest))
        ->assertInertia(fn (Assert $page) => $page->where('guest.identity_number', 'P7788'));

    $this->assertDatabaseHas('audit_logs', ['action' => 'guest.identity_viewed', 'user_id' => $receptionist->id]);
});

it('refuses back-dated exchange rates', function () {
    $accountant = User::factory()->withRole(SystemRole::Accountant)->create();

    $this->actingAs($accountant)
        ->post(route('admin.exchange-rates.store'), ['base_currency' => 'USD', 'quote_currency' => 'LRD', 'rate' => '195.25', 'effective_at' => '2026-11-01 00:00:00'])
        ->assertSessionHasErrors(['effective_at' => 'Rates cannot be back-dated; historical transactions keep the rate they used.']);

    $this->actingAs($accountant)
        ->post(route('admin.exchange-rates.store'), ['base_currency' => 'USD', 'quote_currency' => 'LRD', 'rate' => '195.25', 'effective_at' => '2026-11-10 15:00:00'])
        ->assertSessionHasNoErrors();

    expect(ExchangeRate::query()->sole()->created_by)->toBe($accountant->id);
});

it('asks for an exchange rate instead of failing when none is recorded', function () {
    $reservation = todaysArrival();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();
    ExchangeRate::query()->delete();

    $this->actingAs($receptionist)
        ->from(route('admin.folios.show', $reservation->folio))
        ->post(route('admin.folios.payments.store', $reservation->folio), ['method' => 'cash', 'amount' => '5000', 'currency' => 'LRD', 'idempotency_key' => (string) Str::uuid()])
        ->assertRedirect(route('admin.folios.show', $reservation->folio))
        ->assertSessionHasErrors('currency');

    expect(Payment::query()->count())->toBe(0);
});
