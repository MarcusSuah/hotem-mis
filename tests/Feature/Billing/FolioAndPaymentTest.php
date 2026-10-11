<?php

use App\Enums\BookingSource;
use App\Enums\ChargeCategory;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\CancellationPolicy;
use App\Models\ExchangeRate;
use App\Models\FolioCharge;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Billing\FolioService;
use App\Services\Billing\PaymentService;
use App\Services\Booking\BookingException;
use App\Services\Booking\NewReservation;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;

beforeEach(function () {
    $this->travelTo('2026-11-01 09:00:00');
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '0', 'service_charge_rate' => '0', 'deposit_percent' => '50']);
});

function bookStay(array $overrides = []): Reservation
{
    $type = RoomType::factory()->rate(10000)->create();
    Room::factory()->for($type)->create();

    return app(ReservationService::class)->create(new NewReservation(...array_merge([
        'guest' => Guest::factory()->create(),
        'roomType' => $type,
        'stay' => new StayPeriod('2026-11-10', '2026-11-12'),
        'adults' => 2,
        'children' => 0,
        'currency' => Currency::USD,
        'source' => BookingSource::Phone,
        'confirmImmediately' => true,
    ], $overrides)));
}

it('opens a folio for every reservation', function () {
    $reservation = bookStay();

    expect($reservation->folio)->not->toBeNull()
        ->and($reservation->folio->number)->toBe('F-'.$reservation->reference)
        ->and($reservation->folio->currency)->toBe(Currency::USD);
});

it('records payments and derives the payment status from the ledger', function () {
    $reservation = bookStay();
    $payments = app(PaymentService::class);

    $payments->record($reservation->folio, PaymentMethod::Cash, Money::of('50', 'USD'));
    expect($reservation->fresh()->payment_status)->toBe(PaymentStatus::PartiallyPaid);

    $payments->record($reservation->folio, PaymentMethod::Card, Money::of('150', 'USD'), externalReference: 'POS-1182');
    expect($reservation->fresh())
        ->payment_status->toBe(PaymentStatus::Paid)
        ->amount_paid_minor->toBe(20000);
});

it('requires a transaction reference for non-cash payments', function () {
    $reservation = bookStay();

    app(PaymentService::class)->record($reservation->folio, PaymentMethod::MobileMoney, Money::of('50', 'USD'));
})->throws(BookingException::class, 'Enter the Mobile money transaction reference.');

it('converts a Liberian dollar payment to the folio currency and keeps the rate', function () {
    ExchangeRate::factory()->rate('200')->create(['effective_at' => now()->subDay()]);
    $reservation = bookStay();

    $payment = app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, Money::of('10000', 'LRD'));

    expect($payment->amount()->format())->toBe('LRD 10,000.00')
        ->and($payment->applied_minor)->toBe(5000)
        ->and($payment->exchange_rate)->toBe('0.00500000')
        ->and($reservation->fresh()->amount_paid_minor)->toBe(5000);
});

it('confirms a pending reservation once the deposit is paid', function () {
    $reservation = bookStay(['confirmImmediately' => false]);

    app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, Money::of('100', 'USD'));

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('ignores a repeated payment submission with the same key', function () {
    $reservation = bookStay();
    $service = app(PaymentService::class);

    $service->record($reservation->folio, PaymentMethod::Cash, Money::of('50', 'USD'), idempotencyKey: 'payment:abc');
    $service->record($reservation->folio, PaymentMethod::Cash, Money::of('50', 'USD'), idempotencyKey: 'payment:abc');

    expect(Payment::query()->count())->toBe(1)
        ->and($reservation->fresh()->amount_paid_minor)->toBe(5000);
});

it('refunds up to the amount paid using the original exchange rate', function () {
    ExchangeRate::factory()->rate('200')->create(['effective_at' => now()->subDay()]);
    $reservation = bookStay();
    $service = app(PaymentService::class);
    $payment = $service->record($reservation->folio, PaymentMethod::Cash, Money::of('10000', 'LRD'));
    ExchangeRate::factory()->rate('250')->create(['effective_at' => now()->subMinute()]);

    $refund = $service->refund($payment, Money::of('4000', 'LRD'), 'Overpayment', PaymentMethod::Cash);

    expect($refund->applied_minor)->toBe(2000)
        ->and($reservation->fresh()->amount_paid_minor)->toBe(3000)
        ->and($reservation->fresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and(fn () => $service->refund($payment, Money::of('6000.01', 'LRD'), 'Too much', PaymentMethod::Cash))
        ->toThrow(BookingException::class, 'At most LRD 6,000.00 can be refunded from this payment.');
});

it('posts a cancellation fee to the folio so prepaid money above it can be refunded', function () {
    CancellationPolicy::factory()->default()->create(['free_cancellation_hours' => 48]);
    $reservation = bookStay(['stay' => new StayPeriod('2026-11-02', '2026-11-04')]);
    app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, Money::of('200', 'USD'));

    app(ReservationService::class)->cancel($reservation, 'Late change of plans');

    $summary = app(FolioService::class)->summary($reservation->folio->fresh());
    expect($summary->charges->format())->toBe('USD 100.00')
        ->and($summary->balance->format())->toBe('USD -100.00')
        ->and($reservation->folio->charges()->sole()->category)->toBe(ChargeCategory::CancellationFee);
});

it('keeps folio lines immutable and reversible only once', function () {
    $reservation = bookStay();
    $folios = app(FolioService::class);
    $charge = $folios->post($reservation->folio, ChargeCategory::Minibar, 'Minibar', Money::of('12.50', 'USD'), 2);

    expect(fn () => $charge->update(['amount_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $charge->delete())->toThrow(LogicException::class);

    $reversal = $folios->reverse($charge->fresh(), 'Posted to wrong guest');

    expect($reversal->amount_minor)->toBe(-2500)
        ->and($folios->summary($reservation->folio)->charges->isZero())->toBeTrue()
        ->and(fn () => $folios->reverse($charge->fresh(), 'Again'))->toThrow(BookingException::class, 'This charge has already been reversed.')
        ->and(fn () => $folios->reverse($reversal, 'Undo'))->toThrow(BookingException::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'folio.charge_reversed']);
});

it('rejects charges in a currency other than the folio currency', function () {
    $reservation = bookStay();

    app(FolioService::class)->post($reservation->folio, ChargeCategory::Laundry, 'Laundry', Money::of('5', 'LRD'));
})->throws(BookingException::class, 'Charges on this folio must be in USD.');

it('posts each stay night only once however often it is requested', function () {
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '10', 'service_charge_rate' => '5']);
    $reservation = bookStay();
    $folios = app(FolioService::class);

    $folios->postStayNights($reservation, $reservation->check_out_date);
    $folios->postStayNights($reservation, $reservation->check_out_date);

    expect(FolioCharge::query()->count())->toBe(6)
        ->and($folios->summary($reservation->folio)->charges->minor)->toBe($reservation->fresh()->total_minor);
});
