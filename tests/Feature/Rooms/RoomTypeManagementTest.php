<?php

use App\Enums\SystemRole;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function roomTypePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Deluxe Ocean King',
        'max_adults' => 2,
        'max_children' => 1,
        'max_occupancy' => 3,
        'included_adults' => 2,
        'included_children' => 0,
        'bed_type' => 'King',
        'bed_count' => 1,
        'base_rate' => '185.50',
        'weekend_rate' => '210',
        'extra_adult_rate' => '40',
        'extra_child_rate' => '20',
        'is_active' => '1',
        'display_order' => 1,
    ], $overrides);
}

beforeEach(fn () => Storage::fake('public'));

it('creates a room type with rates in minor units and uploaded images', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();

    $this->actingAs($admin)
        ->post(route('admin.room-types.store'), roomTypePayload([
            'featured_image' => UploadedFile::fake()->image('suite.jpg', 1200, 800),
            'gallery' => [UploadedFile::fake()->image('bath.jpg', 800, 600)],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $type = RoomType::query()->sole();
    expect($type->slug)->toBe('deluxe-ocean-king')
        ->and($type->base_rate_minor)->toBe(18550)
        ->and($type->weekend_rate_minor)->toBe(21000)
        ->and($type->images)->toHaveCount(1);
    Storage::disk('public')->assertExists($type->featured_image_path);
    Storage::disk('public')->assertExists($type->images->first()->path);
});

it('replaces the featured image and deletes the old file only after saving', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $this->actingAs($admin)->post(route('admin.room-types.store'), roomTypePayload([
        'featured_image' => UploadedFile::fake()->image('first.jpg', 800, 600),
    ]));
    $type = RoomType::query()->sole();
    $oldPath = $type->featured_image_path;

    $this->actingAs($admin)
        ->post(route('admin.room-types.update', $type), roomTypePayload([
            '_method' => 'put',
            'featured_image' => UploadedFile::fake()->image('second.jpg', 800, 600),
        ]))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($type->fresh()->featured_image_path);
});

it('validates capacity, rates and image types', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();

    $this->actingAs($admin)
        ->from(route('admin.room-types.index'))
        ->post(route('admin.room-types.store'), roomTypePayload([
            'max_occupancy' => 6,
            'base_rate' => '12.345',
            'featured_image' => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
        ]))
        ->assertRedirect(route('admin.room-types.index'))
        ->assertSessionHasErrors([
            'max_occupancy' => 'Maximum occupancy cannot exceed maximum adults plus maximum children.',
            'base_rate' => 'Enter an amount with up to two decimal places.',
            'featured_image',
        ]);

    expect(RoomType::query()->count())->toBe(0);
});

it('forbids managing room types without the permission', function () {
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();

    $this->actingAs($receptionist)->post(route('admin.room-types.store'), roomTypePayload())->assertForbidden();

    expect(RoomType::query()->count())->toBe(0);
});

it('refuses to delete a room type that still has rooms', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $type = RoomType::factory()->create();
    Room::factory()->for($type)->create();

    $this->actingAs($admin)
        ->delete(route('admin.room-types.destroy', $type))
        ->assertSessionHasErrors(['room_type' => 'Move or delete the rooms of this type before deleting it.']);

    $this->assertNotSoftDeleted($type);
});

it('lists room types for staff who can view rooms', function () {
    $housekeeper = User::factory()->withRole(SystemRole::HousekeepingStaff)->create();
    RoomType::factory()->create(['name' => 'Garden Twin']);

    $this->actingAs($housekeeper)
        ->get(route('admin.room-types.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/room-types/index')
            ->where('roomTypes.0.name', 'Garden Twin')
            ->where('can.manage', false));
});
