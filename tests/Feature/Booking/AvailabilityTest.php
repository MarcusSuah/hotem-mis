<?php

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\StayPeriod;
use Illuminate\Database\UniqueConstraintViolationException;

function isFree(Room $room, string $checkIn, string $checkOut): bool
{
    return app(AvailabilityService::class)->isRoomAvailable($room, new StayPeriod($checkIn, $checkOut));
}

it('keeps a room bookable outside the dates of an existing reservation', function () {
    $room = Room::factory()->create();
    Reservation::factory()->between('2026-11-10', '2026-11-13')->inRoom($room)->create();

    expect(isFree($room, '2026-11-01', '2026-11-05'))->toBeTrue()
        ->and(isFree($room, '2026-11-20', '2026-11-22'))->toBeTrue()
        ->and(isFree($room, '2026-11-12', '2026-11-15'))->toBeFalse()
        ->and(isFree($room, '2026-11-08', '2026-11-11'))->toBeFalse();
});

it('allows a new arrival on the departure date of the previous guest', function () {
    $room = Room::factory()->create();
    Reservation::factory()->between('2026-11-10', '2026-11-13')->inRoom($room)->create();

    expect(isFree($room, '2026-11-13', '2026-11-15'))->toBeTrue()
        ->and(isFree($room, '2026-11-07', '2026-11-10'))->toBeTrue();
});

it('frees the room when a reservation is cancelled', function () {
    $room = Room::factory()->create();
    Reservation::factory()->cancelled()->between('2026-11-10', '2026-11-13')->inRoom($room)->create();

    expect(isFree($room, '2026-11-10', '2026-11-13'))->toBeTrue();
});

it('excludes rooms blocked for maintenance during the overlapping dates only', function () {
    $room = Room::factory()->create();
    RoomBlock::factory()->for($room)->between('2026-11-10', '2026-11-12')->create();

    expect(isFree($room, '2026-11-11', '2026-11-14'))->toBeFalse()
        ->and(isFree($room, '2026-11-12', '2026-11-14'))->toBeTrue();
});

it('ignores released maintenance blocks', function () {
    $room = Room::factory()->create();
    RoomBlock::factory()->for($room)->between('2026-11-10', '2026-11-12')->released()->create();

    expect(isFree($room, '2026-11-10', '2026-11-12'))->toBeTrue();
});

it('never offers rooms that are out of service', function () {
    $room = Room::factory()->outOfService()->create();

    expect(isFree($room, '2026-11-10', '2026-11-12'))->toBeFalse();
});

it('counts available rooms per room type and filters by party size', function () {
    $twin = RoomType::factory()->capacity(2, 0)->create();
    $family = RoomType::factory()->capacity(2, 2)->create();
    $twinRooms = Room::factory()->count(2)->for($twin)->create();
    Room::factory()->for($family)->create();
    Reservation::factory()->between('2026-11-10', '2026-11-12')->inRoom($twinRooms[0])->create();

    $result = app(AvailabilityService::class)->roomTypesFor(new StayPeriod('2026-11-10', '2026-11-12'), 2, 0);
    $withChildren = app(AvailabilityService::class)->roomTypesFor(new StayPeriod('2026-11-10', '2026-11-12'), 2, 1);

    expect($result->pluck('available', 'room_type.id')->all())->toEqual([$twin->id => 1, $family->id => 1])
        ->and($withChildren->pluck('room_type.id')->all())->toBe([$family->id]);
});

it('rejects a second night record for the same room and date at the database level', function () {
    $room = Room::factory()->create();
    $first = Reservation::factory()->between('2026-11-10', '2026-11-11')->inRoom($room)->create();
    $second = Reservation::factory()->between('2026-11-10', '2026-11-11')->create();
    $allocation = $first->allocations()->sole();

    expect(fn () => $allocation->nights()->create([
        'reservation_id' => $second->id,
        'room_id' => $room->id,
        'room_type_id' => $room->room_type_id,
        'night_date' => '2026-11-10',
        'rate_minor' => 1,
        'base_rate_minor' => 1,
    ]))->toThrow(UniqueConstraintViolationException::class);
});
