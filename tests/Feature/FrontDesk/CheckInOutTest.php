<?php

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\HousekeepingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Events\GuestCheckedOut;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Billing\FolioService;
use App\Services\Billing\PaymentService;
use App\Services\Booking\BookingException;
use App\Services\Booking\NewReservation;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\FrontDesk\CheckInDetails;
use App\Services\FrontDesk\FrontDeskService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->travelTo('2026-11-10 15:00:00');
    app(SettingsRepository::class)->setMany('booking', [
        'tax_rate' => '10',
        'service_charge_rate' => '5',
        'deposit_percent' => '0',
        'require_inspection_before_ready' => true,
    ]);
});

function arrivingToday(array $overrides = [], ?Room $room = null): Reservation
{
    $room ??= Room::factory()->for(RoomType::factory()->rate(10000))->create();

    return app(ReservationService::class)->create(new NewReservation(...array_merge([
        'guest' => Guest::factory()->create(),
        'roomType' => $room->roomType,
        'stay' => new StayPeriod('2026-11-10', '2026-11-13'),
        'adults' => 2,
        'children' => 0,
        'currency' => Currency::USD,
        'source' => BookingSource::WalkIn,
        'roomId' => $room->id,
        'confirmImmediately' => true,
    ], $overrides)));
}

function frontDesk(): FrontDeskService
{
    return app(FrontDeskService::class);
}

it('checks a guest in, records companions and stores the ID number encrypted', function () {
    $reservation = arrivingToday();

    frontDesk()->checkIn($reservation, new CheckInDetails(
        additionalGuests: [['name' => 'Junior Kollie', 'age_category' => 'child']],
        identityType: 'passport',
        identityNumber: 'P1234567',
    ));

    $fresh = $reservation->fresh();
    expect($fresh->status)->toBe(ReservationStatus::CheckedIn)
        ->and($fresh->checked_in_at)->not->toBeNull()
        ->and($fresh->occupants()->where('is_primary', false)->value('full_name'))->toBe('Junior Kollie')
        ->and($fresh->guest->identity_number)->toBe('P1234567');
    $this->assertDatabaseMissing('guests', ['identity_number' => 'P1234567']);
});

it('refuses check-in before the arrival date', function () {
    $reservation = arrivingToday(['stay' => new StayPeriod('2026-11-12', '2026-11-14')]);

    frontDesk()->checkIn($reservation, new CheckInDetails);
})->throws(BookingException::class, 'Arrival is booked for 12 Nov 2026. Modify the reservation to arrive earlier.');

it('refuses check-in to a room that has not been inspected', function () {
    $room = Room::factory()->dirty()->for(RoomType::factory())->create(['number' => '305']);
    $reservation = arrivingToday(room: $room);

    frontDesk()->checkIn($reservation, new CheckInDetails);
})->throws(BookingException::class, 'Room 305 is not ready (Dirty).');

it('requires the deposit unless an authorised user waives it', function () {
    app(SettingsRepository::class)->set('booking.deposit_percent', '50');
    $reservation = arrivingToday();
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $manager = User::factory()->withRole(SystemRole::HotelManager)->create();

    expect(fn () => frontDesk()->checkIn($reservation, new CheckInDetails(waiveDeposit: true), $clerk))
        ->toThrow(BookingException::class, 'A deposit of USD 173.25 is still required before check-in.');

    frontDesk()->checkIn($reservation, new CheckInDetails(waiveDeposit: true), $manager);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::CheckedIn);
    $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.deposit_waived', 'auditable_id' => $reservation->id]);
});

it('checks out a paid stay: posts every night, issues the invoice, closes the folio and dirties the room', function () {
    Event::fake([GuestCheckedOut::class]);
    $reservation = arrivingToday();
    frontDesk()->checkIn($reservation, new CheckInDetails);
    app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, $reservation->fresh()->total());
    $this->travelTo('2026-11-13 10:00:00');

    $result = frontDesk()->checkOut($reservation->fresh());

    $invoice = $result['invoice'];
    expect($result['reservation']->status)->toBe(ReservationStatus::CheckedOut)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->total_minor)->toBe($reservation->total_minor)
        ->and($invoice->money($invoice->total_minor)->format())->toBe('USD 346.50')
        ->and($invoice->balance_minor)->toBe(0)
        ->and($invoice->items)->toHaveCount(9)
        ->and($reservation->folio->fresh()->status)->toBe('closed')
        ->and($reservation->activeAllocations()->first()->room->housekeeping_status)->toBe(HousekeepingStatus::Dirty);
    Event::assertDispatched(GuestCheckedOut::class);
});

it('refuses to check out while a balance is owed', function () {
    $reservation = arrivingToday();
    frontDesk()->checkIn($reservation, new CheckInDetails);
    $this->travelTo('2026-11-13 10:00:00');

    expect(fn () => frontDesk()->checkOut($reservation->fresh()))
        ->toThrow(BookingException::class, 'The guest still owes USD 346.50.');
    expect($reservation->fresh()->status)->toBe(ReservationStatus::CheckedIn)
        ->and($reservation->folio->charges()->count())->toBe(0);
});

it('lets authorised staff check out with a balance that stays open as a receivable', function () {
    $reservation = arrivingToday();
    frontDesk()->checkIn($reservation, new CheckInDetails);
    $this->travelTo('2026-11-13 10:00:00');
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $manager = User::factory()->withRole(SystemRole::HotelManager)->create();

    expect(fn () => frontDesk()->checkOut($reservation->fresh(), $clerk, settleLater: true))
        ->toThrow(BookingException::class, 'You are not allowed to check out guests with an unpaid balance.');

    $result = frontDesk()->checkOut($reservation->fresh(), $manager, settleLater: true, settleLaterReason: 'Company will pay by transfer');

    expect($result['invoice']->balance_minor)->toBe(34650)
        ->and($reservation->folio->fresh()->status)->toBe('open')
        ->and(app(FolioService::class)->summary($reservation->folio)->balance->format())->toBe('USD 346.50');
});

it('releases unused nights when a guest leaves early', function () {
    $room = Room::factory()->for(RoomType::factory()->rate(10000))->create();
    $reservation = arrivingToday(room: $room);
    frontDesk()->checkIn($reservation, new CheckInDetails);
    $this->travelTo('2026-11-11 09:00:00');
    app(PaymentService::class)->record($reservation->folio, PaymentMethod::Cash, Money::of('115.50', 'USD'));

    $result = frontDesk()->checkOut($reservation->fresh());

    expect($result['reservation']->nights)->toBe(1)
        ->and($result['reservation']->check_out_date->toDateString())->toBe('2026-11-11')
        ->and($result['reservation']->total_minor)->toBe(11550)
        ->and($room->nights()->pluck('night_date')->map->toDateString()->all())->toBe(['2026-11-10'])
        ->and($result['invoice']->balance_minor)->toBe(0);
});

it('extends an in-house stay in the same room', function () {
    $room = Room::factory()->for(RoomType::factory()->rate(10000))->create();
    $reservation = arrivingToday(room: $room);
    frontDesk()->checkIn($reservation, new CheckInDetails);

    app(ReservationService::class)->extendStay($reservation->fresh(), CarbonImmutable::parse('2026-11-15'));

    expect($reservation->fresh())
        ->check_out_date->toDateString()->toBe('2026-11-15')
        ->nights->toBe(5)
        ->total_minor->toBe(57750);
    expect($room->nights()->count())->toBe(5);
});

it('refuses an extension when the room is booked by someone else', function () {
    $room = Room::factory()->for(RoomType::factory())->create();
    $reservation = arrivingToday(room: $room);
    frontDesk()->checkIn($reservation, new CheckInDetails);
    Reservation::factory()->between('2026-11-13', '2026-11-15')->inRoom($room)->create();

    app(ReservationService::class)->extendStay($reservation->fresh(), CarbonImmutable::parse('2026-11-15'));
})->throws(BookingException::class);
