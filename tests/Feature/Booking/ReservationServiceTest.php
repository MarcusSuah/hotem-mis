<?php

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\ReservationStatus;
use App\Models\CancellationPolicy;
use App\Models\ExchangeRate;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Booking\BookingException;
use App\Services\Booking\NewReservation;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\Settings\SettingsRepository;

beforeEach(function () {
    $this->travelTo('2026-11-01 09:00:00');
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '0', 'service_charge_rate' => '0', 'deposit_percent' => '0']);
});

function booking(RoomType $type, string $checkIn, string $checkOut, array $overrides = []): NewReservation
{
    return new NewReservation(...array_merge([
        'guest' => Guest::factory()->create(),
        'roomType' => $type,
        'stay' => new StayPeriod($checkIn, $checkOut),
        'adults' => 2,
        'children' => 0,
        'currency' => Currency::USD,
        'source' => BookingSource::Phone,
        'confirmImmediately' => true,
    ], $overrides));
}

it('creates a confirmed reservation with a room, night records, history and audit entry', function () {
    $type = RoomType::factory()->rate(12000)->create();
    $room = Room::factory()->for($type)->create();

    $reservation = app(ReservationService::class)->create(booking($type, '2026-11-10', '2026-11-13'));

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->nights)->toBe(3)
        ->and($reservation->total()->format())->toBe('USD 360.00')
        ->and($reservation->reference)->toStartWith('MLX-')
        ->and($reservation->nightRecords()->pluck('night_date')->map->toDateString()->all())
        ->toBe(['2026-11-10', '2026-11-11', '2026-11-12'])
        ->and($reservation->activeAllocations()->sole()->room_id)->toBe($room->id);
    $this->assertDatabaseHas('reservation_status_histories', ['reservation_id' => $reservation->id, 'to_status' => 'confirmed']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.created', 'auditable_id' => $reservation->id]);
});

it('creates pending reservations with an expiry when not confirmed immediately', function () {
    $type = RoomType::factory()->create();
    Room::factory()->for($type)->create();

    $reservation = app(ReservationService::class)->create(booking($type, '2026-11-10', '2026-11-12', ['confirmImmediately' => false]));

    expect($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->expires_at->toDateTimeString())->toBe('2026-11-01 10:00:00');
});

it('prevents overlapping reservations for the same room', function () {
    $type = RoomType::factory()->create();
    $room = Room::factory()->for($type)->create();
    $service = app(ReservationService::class);
    $service->create(booking($type, '2026-11-10', '2026-11-13', ['roomId' => $room->id]));

    expect(fn () => $service->create(booking($type, '2026-11-12', '2026-11-14', ['roomId' => $room->id])))
        ->toThrow(BookingException::class, 'The selected room is no longer available for these dates.');
    expect($room->nights()->count())->toBe(3);
});

it('assigns another room of the type when the first is taken', function () {
    $type = RoomType::factory()->create();
    [$first, $second] = Room::factory()->count(2)->for($type)->sequence(['number' => '101', 'floor' => 1], ['number' => '102', 'floor' => 1])->create();
    $service = app(ReservationService::class);

    $one = $service->create(booking($type, '2026-11-10', '2026-11-12'));
    $two = $service->create(booking($type, '2026-11-11', '2026-11-12'));

    expect($one->activeAllocations()->sole()->room_id)->toBe($first->id)
        ->and($two->activeAllocations()->sole()->room_id)->toBe($second->id);
});

it('reports sold-out room types', function () {
    $type = RoomType::factory()->create();
    Room::factory()->for($type)->create();
    $service = app(ReservationService::class);
    $service->create(booking($type, '2026-11-10', '2026-11-12'));

    $service->create(booking($type, '2026-11-11', '2026-11-12'));
})->throws(BookingException::class, 'No rooms of this type are available for the selected dates.');

it('enforces room capacity', function (int $adults, int $children, string $message) {
    $type = RoomType::factory()->capacity(2, 1, 3)->create(['name' => 'Garden King']);
    Room::factory()->for($type)->create();

    expect(fn () => app(ReservationService::class)->create(booking($type, '2026-11-10', '2026-11-12', ['adults' => $adults, 'children' => $children])))
        ->toThrow(BookingException::class, $message);
    expect(Reservation::query()->count())->toBe(0);
})->with([
    'too many adults' => [3, 0, 'The Garden King accommodates at most 2 adults.'],
    'too many children' => [1, 2, 'The Garden King accommodates at most 1 children.'],
    'no adults' => [0, 1, 'At least one adult is required.'],
]);

it('returns the original reservation for a repeated submission', function () {
    $type = RoomType::factory()->create();
    Room::factory()->count(2)->for($type)->create();
    $service = app(ReservationService::class);
    $data = booking($type, '2026-11-10', '2026-11-12', ['idempotencyKey' => 'form-submission-123']);

    $first = $service->create($data);
    $second = $service->create($data);

    expect($second->id)->toBe($first->id)
        ->and(Reservation::query()->count())->toBe(1);
});

it('enforces the rate plan minimum stay and the maximum stay length', function () {
    $type = RoomType::factory()->create();
    Room::factory()->for($type)->create();
    $plan = RatePlan::factory()->create(['min_nights' => 3, 'name' => 'Long Weekend']);
    $service = app(ReservationService::class);

    expect(fn () => $service->create(booking($type, '2026-11-10', '2026-11-12', ['ratePlan' => $plan])))
        ->toThrow(BookingException::class, 'The Long Weekend rate requires a minimum stay of 3 nights.')
        ->and(fn () => $service->create(booking($type, '2026-11-10', '2026-12-15')))
        ->toThrow(BookingException::class, 'Stays are limited to 30 nights.');
});

it('stores the exchange rate snapshot for bookings charged in Liberian dollars', function () {
    $rate = ExchangeRate::factory()->rate('190')->create(['effective_at' => now()->subDay()]);
    $type = RoomType::factory()->rate(10000)->create();
    Room::factory()->for($type)->create();

    $reservation = app(ReservationService::class)->create(booking($type, '2026-11-10', '2026-11-11', ['currency' => Currency::LRD]));

    expect($reservation->currency)->toBe(Currency::LRD)
        ->and($reservation->exchange_rate_id)->toBe($rate->id)
        ->and($reservation->exchange_rate)->toBe('190.00000000')
        ->and($reservation->total()->format())->toBe('LRD 19,000.00')
        ->and($reservation->base_total_minor)->toBe(10000);
});

it('cancels within the free window without a fee and releases the room', function () {
    $type = RoomType::factory()->create();
    $room = Room::factory()->for($type)->create();
    $policy = CancellationPolicy::factory()->create(['free_cancellation_hours' => 48]);
    $plan = RatePlan::factory()->for($policy)->create();
    $service = app(ReservationService::class);
    $reservation = $service->create(booking($type, '2026-11-10', '2026-11-12', ['ratePlan' => $plan]));

    $service->cancel($reservation, 'Change of plans');

    expect($reservation->fresh())
        ->status->toBe(ReservationStatus::Cancelled)
        ->cancellation_fee_minor->toBe(0)
        ->cancellation_reason->toBe('Change of plans');
    expect($room->nights()->count())->toBe(0)
        ->and($reservation->allocations()->whereNotNull('released_at')->count())->toBe(1);
});

it('charges the policy penalty when cancelling late', function (CancellationPolicy $policy, int $expectedFee) {
    $type = RoomType::factory()->rate(10000)->create();
    Room::factory()->for($type)->create();
    $plan = RatePlan::factory()->for($policy)->create();
    $service = app(ReservationService::class);
    $reservation = $service->create(booking($type, '2026-11-02', '2026-11-05', ['ratePlan' => $plan]));

    $service->cancel($reservation, 'Late cancellation');

    expect($reservation->fresh()->cancellation_fee_minor)->toBe($expectedFee);
})->with([
    'first night' => [fn () => CancellationPolicy::factory()->create(['free_cancellation_hours' => 48]), 10000],
    'fifty percent' => [fn () => CancellationPolicy::factory()->percentage('50')->create(), 15000],
    'non-refundable' => [fn () => CancellationPolicy::factory()->nonRefundable()->create(), 30000],
]);

it('keeps the agreed cancellation terms when the policy is edited later', function () {
    $type = RoomType::factory()->rate(10000)->create();
    Room::factory()->for($type)->create();
    $policy = CancellationPolicy::factory()->nonRefundable()->create();
    $plan = RatePlan::factory()->for($policy)->create();
    $service = app(ReservationService::class);
    $reservation = $service->create(booking($type, '2026-11-20', '2026-11-22', ['ratePlan' => $plan]));

    $policy->update(['penalty_type' => 'none']);
    $service->cancel($reservation, 'Changed mind');

    expect($reservation->fresh()->cancellation_fee_minor)->toBe(20000);
});

it('refuses invalid status transitions', function () {
    $room = Room::factory()->create();
    $reservation = Reservation::factory()->checkedOut()->between('2026-10-20', '2026-10-22')->inRoom($room)->create();

    app(ReservationService::class)->cancel($reservation, 'Too late');
})->throws(BookingException::class, 'A Checked Out reservation cannot be changed to Cancelled.');

it('marks no-shows on or after the arrival date and charges the first night', function () {
    $room = Room::factory()->create();
    $reservation = Reservation::factory()->confirmed()->between('2026-10-31', '2026-11-03')->inRoom($room)->create();

    app(ReservationService::class)->markNoShow($reservation);

    expect($reservation->fresh())
        ->status->toBe(ReservationStatus::NoShow)
        ->cancellation_fee_minor->toBe(15000);
    expect($room->nights()->count())->toBe(0);
});

it('rejects no-shows before the arrival date', function () {
    $room = Room::factory()->create();
    $reservation = Reservation::factory()->confirmed()->between('2026-11-05', '2026-11-07')->inRoom($room)->create();

    app(ReservationService::class)->markNoShow($reservation);
})->throws(BookingException::class, 'A reservation can only be marked as a no-show on or after its arrival date.');

it('keeps the room and re-prices when dates change and the room is still free', function () {
    $type = RoomType::factory()->rate(10000)->create();
    $room = Room::factory()->for($type)->create();
    $service = app(ReservationService::class);
    $reservation = $service->create(booking($type, '2026-11-10', '2026-11-12'));

    $service->modify($reservation, new StayPeriod('2026-11-11', '2026-11-15'), 2, 0);

    $fresh = $reservation->fresh();
    expect($fresh->nights)->toBe(4)
        ->and($fresh->total_minor)->toBe(40000)
        ->and($fresh->activeAllocations()->sole()->room_id)->toBe($room->id)
        ->and($room->nights()->pluck('night_date')->map->toDateString()->all())
        ->toBe(['2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.modified', 'auditable_id' => $reservation->id]);
});

it('moves a modified stay to another room when its room is taken on the new dates', function () {
    $type = RoomType::factory()->create();
    [$first, $second] = Room::factory()->count(2)->for($type)->sequence(['number' => '201', 'floor' => 2], ['number' => '202', 'floor' => 2])->create();
    $service = app(ReservationService::class);
    $reservation = $service->create(booking($type, '2026-11-10', '2026-11-12'));
    Reservation::factory()->between('2026-11-12', '2026-11-14')->inRoom($first)->create();

    $service->modify($reservation, new StayPeriod('2026-11-10', '2026-11-14'), 2, 0);

    expect($reservation->fresh()->activeAllocations()->sole()->room_id)->toBe($second->id);
});

it('does not modify dates into a period with no availability', function () {
    $type = RoomType::factory()->create();
    $room = Room::factory()->for($type)->create();
    $service = app(ReservationService::class);
    $reservation = $service->create(booking($type, '2026-11-10', '2026-11-12'));
    Reservation::factory()->between('2026-11-12', '2026-11-14')->inRoom($room)->create();

    expect(fn () => $service->modify($reservation, new StayPeriod('2026-11-10', '2026-11-14'), 2, 0))
        ->toThrow(BookingException::class);
    expect($reservation->fresh()->check_out_date->toDateString())->toBe('2026-11-12')
        ->and($room->nights()->where('reservation_id', $reservation->id)->count())->toBe(2);
});

it('reassigns the whole stay for an upcoming reservation without changing the price', function () {
    $type = RoomType::factory()->create();
    [$first, $second] = Room::factory()->count(2)->for($type)->create();
    $service = app(ReservationService::class);
    $reservation = $service->create(booking($type, '2026-11-10', '2026-11-12', ['roomId' => $first->id]));

    $service->reassignRoom($reservation, $second, reason: 'Guest requested a quieter room');

    $fresh = $reservation->fresh();
    expect($fresh->activeAllocations()->sole()->room_id)->toBe($second->id)
        ->and($fresh->total_minor)->toBe($reservation->total_minor)
        ->and($first->nights()->count())->toBe(0)
        ->and($second->nights()->count())->toBe(2);
});

it('moves only the remaining nights for an in-house guest', function () {
    $type = RoomType::factory()->create();
    [$first, $second] = Room::factory()->count(2)->for($type)->create();
    $reservation = Reservation::factory()->checkedIn()->inRoom($first)->create();

    app(ReservationService::class)->reassignRoom($reservation, $second);

    expect($first->nights()->pluck('night_date')->map->toDateString()->all())->toBe(['2026-10-31'])
        ->and($second->nights()->pluck('night_date')->map->toDateString()->all())->toBe(['2026-11-01', '2026-11-02'])
        ->and($reservation->activeAllocations()->count())->toBe(2);
});

it('refuses to reassign to a room that is occupied on those dates', function () {
    $type = RoomType::factory()->create();
    [$first, $second] = Room::factory()->count(2)->for($type)->create();
    $service = app(ReservationService::class);
    $reservation = $service->create(booking($type, '2026-11-10', '2026-11-12', ['roomId' => $first->id]));
    Reservation::factory()->between('2026-11-11', '2026-11-13')->inRoom($second)->create();

    $service->reassignRoom($reservation, $second);
})->throws(BookingException::class, 'The selected room is no longer available for these dates.');

it('expires pending reservations whose hold has lapsed', function () {
    $type = RoomType::factory()->create();
    $room = Room::factory()->for($type)->create();
    $service = app(ReservationService::class);
    $stale = $service->create(booking($type, '2026-11-10', '2026-11-12', ['confirmImmediately' => false]));

    $this->travel(2)->hours();
    $this->artisan('hms:expire-pending-reservations')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($room->nights()->count())->toBe(0);
});
