<?php

use App\Enums\OperationalStatus;
use App\Enums\SystemRole;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\StayPeriod;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->travelTo('2026-11-01 09:00:00'));

it('creates a room with a unique number', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $type = RoomType::factory()->create();
    Room::factory()->for($type)->create(['number' => '101']);

    $this->actingAs($admin)
        ->post(route('admin.rooms.store'), ['number' => '101', 'room_type_id' => $type->id, 'floor' => 1, 'operational_status' => 'in_service'])
        ->assertSessionHasErrors(['number' => 'Another room already uses this number.']);

    $this->actingAs($admin)
        ->post(route('admin.rooms.store'), ['number' => '102', 'room_type_id' => $type->id, 'floor' => 1, 'operational_status' => 'in_service'])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('rooms', ['number' => '102', 'room_type_id' => $type->id, 'housekeeping_status' => 'clean']);
});

it('refuses to take a room with upcoming reservations out of service', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $room = Room::factory()->create();
    Reservation::factory()->between('2026-11-05', '2026-11-07')->inRoom($room)->create();

    $this->actingAs($admin)
        ->put(route('admin.rooms.update', $room), [
            'number' => $room->number,
            'room_type_id' => $room->room_type_id,
            'floor' => $room->floor,
            'operational_status' => 'out_of_service',
        ])
        ->assertSessionHasErrors('operational_status');

    expect($room->fresh()->operational_status)->toBe(OperationalStatus::InService);
});

it('blocks a room for maintenance and removes it from availability for those dates', function () {
    $manager = User::factory()->withRole(SystemRole::HotelManager)->create();
    $room = Room::factory()->create();

    $this->actingAs($manager)
        ->post(route('admin.rooms.blocks.store', $room), [
            'type' => 'maintenance',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-12',
            'reason' => 'Replace compressor',
        ])
        ->assertSessionHasNoErrors();

    $availability = app(AvailabilityService::class);
    expect($availability->isRoomAvailable($room, new StayPeriod('2026-11-11', '2026-11-13')))->toBeFalse()
        ->and($availability->isRoomAvailable($room, new StayPeriod('2026-11-12', '2026-11-13')))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'room.blocked', 'auditable_id' => $room->id]);
});

it('refuses a block that overlaps a reservation and names the booking', function () {
    $manager = User::factory()->withRole(SystemRole::HotelManager)->create();
    $room = Room::factory()->create();
    $reservation = Reservation::factory()->between('2026-11-10', '2026-11-12')->inRoom($room)->create();

    $this->actingAs($manager)
        ->post(route('admin.rooms.blocks.store', $room), [
            'type' => 'out_of_service',
            'start_date' => '2026-11-11',
            'end_date' => '2026-11-15',
            'reason' => 'Flooding',
        ])
        ->assertSessionHasErrors(['start_date' => "Room {$room->number} is reserved during this period ({$reservation->reference}). Reassign those reservations before blocking the room."]);

    expect(RoomBlock::query()->count())->toBe(0);
});

it('releases a block so the room can be sold again', function () {
    $manager = User::factory()->withRole(SystemRole::HotelManager)->create();
    $block = RoomBlock::factory()->between('2026-11-10', '2026-11-12')->create();

    $this->actingAs($manager)->delete(route('admin.room-blocks.destroy', $block))->assertSessionHasNoErrors();

    expect($block->fresh()->released_at)->not->toBeNull()
        ->and(app(AvailabilityService::class)->isRoomAvailable($block->room, new StayPeriod('2026-11-10', '2026-11-12')))->toBeTrue();
});

it('refuses to delete a room with upcoming reservations', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $room = Room::factory()->create();
    Reservation::factory()->between('2026-11-05', '2026-11-07')->inRoom($room)->create();

    $this->actingAs($admin)->delete(route('admin.rooms.destroy', $room))->assertSessionHasErrors('room');

    $this->assertNotSoftDeleted($room);
});

it('shows each room in its derived state for today', function () {
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->create();
    $occupied = Room::factory()->for($type)->create(['number' => '201', 'floor' => 2]);
    $blocked = Room::factory()->for($type)->create(['number' => '202', 'floor' => 2]);
    Room::factory()->for($type)->create(['number' => '203', 'floor' => 2]);
    Reservation::factory()->checkedIn()->inRoom($occupied)->create();
    RoomBlock::factory()->for($blocked)->between('2026-10-30', '2026-11-03')->create();

    $this->actingAs($receptionist)
        ->get(route('admin.rooms.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rooms/index')
            ->where('rooms.data.0.state.state', 'occupied')
            ->where('rooms.data.1.state.state', 'maintenance')
            ->where('rooms.data.2.state.state', 'available'));
});
