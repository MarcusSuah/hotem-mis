<?php

use App\Enums\InventoryMovementType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\SystemRole;
use App\Models\Complaint;
use App\Models\Guest;
use App\Models\InventoryItem;
use App\Models\LeaveRequest;
use App\Models\MenuItem;
use App\Models\RestaurantOrder;
use App\Models\Room;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '10', 'service_charge_rate' => '0', 'deposit_percent' => '0']);
});

it('opens each operations page only for roles that run it', function (string $route, SystemRole $allowed, SystemRole $denied) {
    $this->actingAs(User::factory()->withRole($denied)->create())->get(route($route))->assertForbidden();
    $this->actingAs(User::factory()->withRole($allowed)->create())->get(route($route))->assertOk();
})->with([
    'housekeeping' => ['admin.housekeeping.index', SystemRole::HousekeepingStaff, SystemRole::Accountant],
    'maintenance' => ['admin.maintenance.index', SystemRole::MaintenanceStaff, SystemRole::Accountant],
    'inventory' => ['admin.inventory.index', SystemRole::InventoryManager, SystemRole::Receptionist],
    'outlets' => ['admin.outlets.index', SystemRole::Receptionist, SystemRole::InventoryManager],
    'catalog' => ['admin.catalog.index', SystemRole::HotelManager, SystemRole::Receptionist],
    'staff' => ['admin.staff.index', SystemRole::HotelManager, SystemRole::Receptionist],
    'complaints' => ['admin.complaints.index', SystemRole::Receptionist, SystemRole::InventoryManager],
]);

it('takes a room out of service when an issue is reported with a block', function () {
    $room = Room::factory()->create();

    $this->actingAs(User::factory()->withRole(SystemRole::HotelManager)->create())
        ->post(route('admin.maintenance.store'), [
            'title' => 'Ceiling leak',
            'category' => 'plumbing',
            'priority' => 'urgent',
            'room_id' => $room->id,
            'block_room' => true,
            'block_from' => '2026-11-10',
            'block_until' => '2026-11-12',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($room->blocks()->whereNull('released_at')->count())->toBe(1);
});

it('requires a location when the issue is not in a guest room', function () {
    $this->actingAs(User::factory()->withRole(SystemRole::HousekeepingStaff)->create())
        ->post(route('admin.maintenance.store'), ['title' => 'Pool pump noise', 'category' => 'other', 'priority' => 'normal'])
        ->assertSessionHasErrors('location');
});

it('refuses to issue more stock than is on hand', function () {
    $manager = User::factory()->withRole(SystemRole::InventoryManager)->create();
    $item = InventoryItem::factory()->create(['unit' => 'each']);
    app(InventoryService::class)->record($item, InventoryMovementType::Receipt, '3', $manager, 100);

    $this->actingAs($manager)
        ->post(route('admin.inventory.items.movements', $item), ['type' => 'issue', 'quantity' => '5'])
        ->assertSessionHasErrors('quantity');

    expect($item->fresh()->quantity)->toBe('3.000');
});

it('creates a restaurant order once even when submitted twice', function () {
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();
    $item = MenuItem::factory()->create(['price_minor' => 1200]);
    $payload = ['type' => 'restaurant', 'customer_name' => 'Walk-in', 'lines' => [['menu_item_id' => $item->id, 'quantity' => 2]], 'idempotency_key' => (string) Str::uuid()];

    $this->actingAs($receptionist)->post(route('admin.outlets.orders.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($receptionist)->post(route('admin.outlets.orders.store'), $payload)->assertSessionHasNoErrors();

    expect(RestaurantOrder::query()->count())->toBe(1)
        ->and(RestaurantOrder::query()->value('total_minor'))->toBe(2640);
});

it('rejects a leave decision once the request has been decided', function () {
    $leave = LeaveRequest::create([
        'staff_profile_id' => StaffProfile::factory()->create()->id,
        'type' => LeaveType::cases()[0],
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-22',
        'status' => LeaveStatus::Approved,
    ]);

    $this->actingAs(User::factory()->withRole(SystemRole::HotelManager)->create())
        ->post(route('admin.staff.leave.decide', $leave), ['status' => 'rejected', 'decision_note' => 'Peak season'])
        ->assertSessionHasErrors('status');

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved);
});

it('requires a resolution before a complaint is resolved', function () {
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();
    $this->actingAs($receptionist)->post(route('admin.complaints.store'), [
        'category' => 'noise', 'severity' => 'high', 'subject' => 'Loud music', 'description' => 'Music from the bar after midnight.',
    ])->assertSessionHasNoErrors();
    $complaint = Complaint::query()->sole();

    $this->actingAs($receptionist)
        ->put(route('admin.complaints.update', $complaint), ['status' => 'resolved'])
        ->assertSessionHasErrors('resolution');

    $this->actingAs($receptionist)
        ->put(route('admin.complaints.update', $complaint), ['status' => 'resolved', 'resolution' => 'Bar closes at 23:00 on weekdays.'])
        ->assertSessionHasNoErrors();

    expect($complaint->fresh()->resolved_at)->not->toBeNull();
});

it('lets front office adjust loyalty points and shows them on the guest profile', function () {
    $guest = Guest::factory()->create();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();

    $this->actingAs(User::factory()->withRole(SystemRole::HousekeepingStaff)->create())
        ->post(route('admin.guests.loyalty.store', $guest), ['points' => 50, 'reason' => 'Goodwill'])
        ->assertForbidden();

    $this->actingAs($receptionist)
        ->post(route('admin.guests.loyalty.store', $guest), ['points' => 50, 'reason' => 'Goodwill'])
        ->assertSessionHasNoErrors();
    $this->actingAs($receptionist)
        ->post(route('admin.guests.preferences.store', $guest), ['category' => 'room', 'preference' => 'High floor'])
        ->assertSessionHasNoErrors();

    $this->actingAs($receptionist)
        ->get(route('admin.guests.show', $guest))
        ->assertInertia(fn (Assert $page) => $page
            ->where('loyalty.balance', 50)
            ->where('preferences.0.preference', 'High floor'));
});

it('follows only internal links when a notification is opened', function (string $url, string $expected) {
    $user = User::factory()->withRole(SystemRole::InventoryManager)->create();
    $notification = DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => ['title' => 'Low stock', 'message' => 'Towels', 'url' => $url],
    ]);

    $this->actingAs($user)
        ->from('/admin/notifications')
        ->post(route('admin.notifications.read', $notification->id))
        ->assertRedirect($expected);

    expect($notification->fresh()->read_at)->not->toBeNull();
})->with([
    'internal' => ['/admin/inventory', '/admin/inventory'],
    'external' => ['https://evil.example/phish', '/admin/notifications'],
]);
