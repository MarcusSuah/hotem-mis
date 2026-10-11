<?php

use App\Enums\ReservationStatus;
use App\Enums\SystemRole;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-01 09:00:00');
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '10', 'service_charge_rate' => '0', 'deposit_percent' => '0']);
});

function reservationPayload(RoomType $type, array $overrides = []): array
{
    return array_merge([
        'check_in_date' => '2026-11-10',
        'check_out_date' => '2026-11-12',
        'adults' => 2,
        'children' => 0,
        'room_type_id' => $type->id,
        'currency' => 'USD',
        'source' => 'phone',
        'first_name' => 'Amara',
        'last_name' => 'Kollie',
        'email' => 'amara@example.test',
        'phone' => '+231770000123',
        'confirm' => true,
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides);
}

it('forbids reservation screens to staff without booking access', function () {
    $housekeeper = User::factory()->withRole(SystemRole::HousekeepingStaff)->create();

    $this->actingAs($housekeeper)->get(route('admin.reservations.index'))->assertForbidden();
    $this->actingAs($housekeeper)->get(route('admin.availability.index'))->assertForbidden();
});

it('shows availability and quotes on the booking page', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->rate(10000)->create(['name' => 'Garden King']);
    Room::factory()->count(2)->for($type)->create();

    $this->actingAs($clerk)
        ->get(route('admin.reservations.create', ['check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-12', 'adults' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reservations/create')
            ->where('availability.nights', 2)
            ->where('availability.types.0.name', 'Garden King')
            ->where('availability.types.0.available', 2)
            ->where('availability.types.0.quote.total.formatted', 'USD 220.00'));
});

it('creates a reservation for a new guest and redirects to it', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->rate(10000)->create();
    Room::factory()->for($type)->create();

    $response = $this->actingAs($clerk)->post(route('admin.reservations.store'), reservationPayload($type));

    $reservation = Reservation::query()->sole();
    $response->assertRedirect(route('admin.reservations.show', $reservation))
        ->assertInertiaFlash('toast.type', 'success');
    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->total_minor)->toBe(22000)
        ->and($reservation->booked_by)->toBe($clerk->id)
        ->and($reservation->guest->email)->toBe('amara@example.test');
});

it('reuses the existing guest profile with the same email', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->create();
    Room::factory()->for($type)->create();
    $guest = Guest::factory()->create(['email' => 'amara@example.test']);

    $this->actingAs($clerk)->post(route('admin.reservations.store'), reservationPayload($type));

    expect(Reservation::query()->sole()->guest_id)->toBe($guest->id)
        ->and(Guest::query()->count())->toBe(1);
});

it('creates only one reservation when the same form is submitted twice', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->create();
    Room::factory()->count(2)->for($type)->create();
    $payload = reservationPayload($type);

    $this->actingAs($clerk)->post(route('admin.reservations.store'), $payload);
    $this->actingAs($clerk)->post(route('admin.reservations.store'), $payload);

    expect(Reservation::query()->count())->toBe(1);
});

it('keeps the user on the booking page with field errors when validation fails', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->create();

    $this->actingAs($clerk)
        ->from(route('admin.reservations.create'))
        ->post(route('admin.reservations.store'), reservationPayload($type, [
            'check_in_date' => '2026-10-20',
            'check_out_date' => '2026-10-19',
            'first_name' => '',
        ]))
        ->assertRedirect(route('admin.reservations.create'))
        ->assertSessionHasErrors([
            'check_in_date' => 'Check-in cannot be in the past.',
            'check_out_date' => 'Check-out must be after check-in.',
            'first_name' => "Select an existing guest or enter the guest's first name.",
        ])
        ->assertSessionHasInput('last_name', 'Kollie');
});

it('reports sold-out dates as a field error without creating anything', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->create();
    $room = Room::factory()->for($type)->create();
    Reservation::factory()->between('2026-11-09', '2026-11-11')->inRoom($room)->create();

    $this->actingAs($clerk)
        ->from(route('admin.reservations.create'))
        ->post(route('admin.reservations.store'), reservationPayload($type))
        ->assertRedirect(route('admin.reservations.create'))
        ->assertSessionHasErrors(['room_type_id' => 'No rooms of this type are available for the selected dates.']);

    expect(Reservation::query()->count())->toBe(1);
});

it('rejects party sizes larger than the room type allows', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->capacity(2, 0)->create(['name' => 'Classic Double']);
    Room::factory()->for($type)->create();

    $this->actingAs($clerk)
        ->post(route('admin.reservations.store'), reservationPayload($type, ['adults' => 3]))
        ->assertSessionHasErrors(['adults' => 'The Classic Double accommodates at most 2 adults.']);
});

it('cancels a reservation with a reason', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $reservation = Reservation::factory()->confirmed()->between('2026-11-20', '2026-11-22')->inRoom(Room::factory()->create())->create();

    $this->actingAs($clerk)
        ->post(route('admin.reservations.cancel', $reservation), ['reason' => 'Flight cancelled'])
        ->assertSessionHasNoErrors();

    expect($reservation->fresh())
        ->status->toBe(ReservationStatus::Cancelled)
        ->cancellation_reason->toBe('Flight cancelled');
});

it('only lets finance staff waive cancellation fees', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $reservation = Reservation::factory()->confirmed()->between('2026-11-20', '2026-11-22')->inRoom(Room::factory()->create())->create();

    $this->actingAs($clerk)
        ->post(route('admin.reservations.cancel', $reservation), ['reason' => 'Goodwill', 'waive_fee' => true])
        ->assertSessionHasErrors(['waive_fee' => 'You are not allowed to waive cancellation fees.']);

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('moves a reservation to another free room', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->create();
    [$first, $second] = Room::factory()->count(2)->for($type)->create();
    $reservation = Reservation::factory()->confirmed()->between('2026-11-20', '2026-11-22')->inRoom($first)->create();

    $this->actingAs($clerk)
        ->put(route('admin.reservations.room.update', $reservation), ['room_id' => $second->id, 'reason' => 'Upgrade'])
        ->assertSessionHasNoErrors();

    expect($reservation->activeAllocations()->sole()->room_id)->toBe($second->id);
});

it('modifies stay dates through the reservation page', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $type = RoomType::factory()->rate(10000)->create();
    $room = Room::factory()->for($type)->create();
    $this->actingAs($clerk)->post(route('admin.reservations.store'), reservationPayload($type));
    $reservation = Reservation::query()->sole();

    $this->actingAs($clerk)
        ->put(route('admin.reservations.update', $reservation), ['check_in_date' => '2026-11-10', 'check_out_date' => '2026-11-13', 'adults' => 2, 'children' => 0])
        ->assertSessionHasNoErrors();

    expect($reservation->fresh()->total_minor)->toBe(33000)
        ->and($room->nights()->count())->toBe(3);
});

it('lists reservations filtered by status and search', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $guest = Guest::factory()->create(['first_name' => 'Musu', 'last_name' => 'Doe']);
    Reservation::factory()->for($guest)->confirmed()->create();
    Reservation::factory()->cancelled()->create();

    $this->actingAs($clerk)
        ->get(route('admin.reservations.index', ['search' => 'Musu', 'status' => 'confirmed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservations.data', 1)
            ->where('reservations.data.0.guest.name', 'Musu Doe'));
});

it('draws held nights on the availability calendar', function () {
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();
    $room = Room::factory()->create();
    $reservation = Reservation::factory()->confirmed()->between('2026-11-03', '2026-11-06')->inRoom($room)->create();
    Reservation::factory()->cancelled()->between('2026-11-03', '2026-11-06')->inRoom(Room::factory()->create())->create();

    $this->actingAs($clerk)
        ->get(route('admin.availability.index', ['start' => '2026-11-01', 'days' => 7]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('segments', 1)
            ->where('segments.0.reference', $reservation->reference)
            ->where('segments.0.start', '2026-11-03')
            ->where('segments.0.end', '2026-11-06'));
});
