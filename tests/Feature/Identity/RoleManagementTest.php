<?php

use App\Enums\SystemRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('creates a role with the selected permissions', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();

    $this->actingAs($admin)
        ->post(route('admin.roles.store'), [
            'name' => 'Night Auditor',
            'permissions' => ['bookings.view', 'night-audit.run'],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $role = Role::findByName('Night Auditor');
    expect($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['bookings.view', 'night-audit.run']);
});

it('prevents granting permissions the actor does not hold', function () {
    $manager = User::factory()->withRole(SystemRole::HotelManager)->create();
    $manager->givePermissionTo('roles.create');

    $this->actingAs($manager)
        ->post(route('admin.roles.store'), [
            'name' => 'Shadow Admin',
            'permissions' => ['settings.manage'],
        ])
        ->assertSessionHasErrors(['permissions' => 'You cannot grant permissions you do not hold: settings.manage.']);

    expect(Role::query()->where('name', 'Shadow Admin')->exists())->toBeFalse();
});

it('does not delete built-in roles', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $role = Role::findByName(SystemRole::Receptionist->value);

    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role))->assertForbidden();

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('requires users to be reassigned before a custom role is deleted', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $role = Role::create(['name' => 'Concierge', 'guard_name' => 'web']);
    User::factory()->withRole('Concierge')->create();

    $this->actingAs($admin)
        ->delete(route('admin.roles.destroy', $role))
        ->assertSessionHasErrors(['role' => 'Reassign the users holding this role before deleting it.']);

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});

it('grants new custom permissions to the Super Admin role automatically', function () {
    $superAdmin = User::factory()->withRole(SystemRole::SuperAdmin)->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.permissions.store'), ['name' => 'spa.manage'])
        ->assertSessionHasNoErrors();

    expect(Role::findByName(SystemRole::SuperAdmin->value)->hasPermissionTo('spa.manage'))->toBeTrue();
});

it('validates the custom permission name format', function () {
    $superAdmin = User::factory()->withRole(SystemRole::SuperAdmin)->create();

    $this->actingAs($superAdmin)
        ->post(route('admin.permissions.store'), ['name' => 'Spa Manage'])
        ->assertSessionHasErrors(['name' => 'Use the "area.action" format with lowercase letters, numbers and dashes.']);
});
