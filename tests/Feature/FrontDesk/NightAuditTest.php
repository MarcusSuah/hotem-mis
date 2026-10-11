<?php

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Models\FolioCharge;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Billing\PaymentService;
use App\Services\Booking\BookingException;
use App\Services\Booking\NewReservation;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\FrontDesk\CheckInDetails;
use App\Services\FrontDesk\FrontDeskService;
use App\Services\FrontDesk\NightAuditService;
use App\Services\Settings\SettingsRepository;

beforeEach(function () {
    $this->travelTo('2026-11-10 23:30:00');
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '10', 'service_charge_rate' => '0', 'deposit_percent' => '0']);
});

function inHouseGuest(): Reservation
{
    $room = Room::factory()->for(RoomType::factory()->rate(10000))->create();
    $reservation = app(ReservationService::class)->create(new NewReservation(
        guest: Guest::factory()->create(),
        roomType: $room->roomType,
        stay: new StayPeriod('2026-11-10', '2026-11-12'),
        adults: 2,
        children: 0,
        currency: Currency::USD,
        source: BookingSource::WalkIn,
        roomId: $room->id,
        confirmImmediately: true,
    ));
    app(FrontDeskService::class)->checkIn($reservation, new CheckInDetails);

    return $reservation->fresh();
}

it("posts tonight's room and tax charges and advances the business date", function () {
    $reservation = inHouseGuest();

    $audit = app(NightAuditService::class)->run();

    expect(FolioCharge::query()->pluck('amount_minor')->all())->toBe([10000, 1000])
        ->and($audit->summary['rooms_occupied'])->toBe(1)
        ->and(app(NightAuditService::class)->businessDate()->toDateString())->toBe('2026-11-11');
    $this->assertDatabaseHas('audit_logs', ['action' => 'night_audit.completed']);
});

it('never posts the same night twice when check-out follows the audit', function () {
    $reservation = inHouseGuest();
    app(NightAuditService::class)->run();
    $this->travelTo('2026-11-12 10:00:00');
    app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, $reservation->total());

    app(FrontDeskService::class)->checkOut($reservation->fresh());

    expect(FolioCharge::query()->count())->toBe(4)
        ->and((int) FolioCharge::query()->sum('amount_minor'))->toBe($reservation->total_minor);
});

it('closes each business day only once and never ahead of time', function () {
    inHouseGuest();
    $audits = app(NightAuditService::class);
    $audits->run();

    expect(fn () => $audits->run())->toThrow(BookingException::class, 'The audit for 11 Nov 2026 can only run on or after that date.');

    app(SettingsRepository::class)->set('operations.business_date', '2026-11-10');
    expect(fn () => $audits->run())->toThrow(BookingException::class, 'The business day 10 Nov 2026 has already been closed.');
});

it('optionally marks guests who never arrived as no-shows', function () {
    $room = Room::factory()->create();
    $absent = Reservation::factory()->confirmed()->between('2026-11-10', '2026-11-12')->inRoom($room)->create();

    $audit = app(NightAuditService::class)->run(markNoShows: true);

    expect($absent->fresh()->status)->toBe(ReservationStatus::NoShow)
        ->and($audit->summary['no_shows'])->toBe([$absent->reference])
        ->and($room->nights()->count())->toBe(0);
});
