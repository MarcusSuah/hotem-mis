<?php

use App\Enums\SystemRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lists users for staff with the users.view permission', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    User::factory()->withRole(SystemRole::Guest)->create(['name' => 'Ada Guest']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'Ada']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Ada Guest'));
});

it('forbids user management to roles without the permission', function (SystemRole $role) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
})->with([
    'receptionist' => SystemRole::Receptionist,
    'housekeeping staff' => SystemRole::HousekeepingStaff,
    'accountant' => SystemRole::Accountant,
]);

it('creates a staff account with roles and records an audit entry', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Front Desk',
            'email' => 'frontdesk@hotel.test',
            'phone' => '+231770000001',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'roles' => [SystemRole::Receptionist->value],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    $created = User::query()->where('email', 'frontdesk@hotel.test')->sole();
    expect($created->hasRole(SystemRole::Receptionist->value))->toBeTrue();
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'user.created',
        'auditable_id' => $created->id,
        'user_id' => $admin->id,
    ]);
});

it('returns field errors and keeps the user on the page when validation fails', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    User::factory()->create(['email' => 'taken@hotel.test']);

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [
            'name' => '',
            'email' => 'taken@hotel.test',
            'password' => 'one',
            'password_confirmation' => 'two',
            'roles' => [],
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'email' => 'The email has already been taken.',
            'password' => 'The password field confirmation does not match.',
            'roles' => 'The roles field is required.',
        ]);

    expect(User::query()->count())->toBe(2);
});

it('prevents administrators from granting the Super Admin role', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Escalated',
            'email' => 'escalated@hotel.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'roles' => [SystemRole::SuperAdmin->value],
        ])
        ->assertSessionHasErrors(['roles' => 'Only a Super Admin can assign the Super Admin role.']);

    $this->assertDatabaseMissing('users', ['email' => 'escalated@hotel.test']);
});

it('prevents staff from assigning roles with permissions they do not hold', function () {
    $manager = User::factory()->withRole(SystemRole::HotelManager)->create();
    $manager->givePermissionTo('users.create');

    $this->actingAs($manager)
        ->post(route('admin.users.store'), [
            'name' => 'Escalated',
            'email' => 'escalated@hotel.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'roles' => [SystemRole::HotelAdministrator->value],
        ])
        ->assertSessionHasErrors('roles');

    $this->assertDatabaseMissing('users', ['email' => 'escalated@hotel.test']);
});

it('prevents administrators from editing Super Admin accounts', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $superAdmin = User::factory()->withRole(SystemRole::SuperAdmin)->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $superAdmin), [
            'name' => 'Renamed',
            'email' => $superAdmin->email,
            'roles' => [SystemRole::SuperAdmin->value],
        ])
        ->assertForbidden();

    expect($superAdmin->fresh()->name)->not->toBe('Renamed');
});

it('prevents users from deleting their own account through user management', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();

    $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertForbidden();

    $this->assertNotSoftDeleted($admin);
});

it('deactivates an account, ends its sessions and blocks future sign-ins', function () {
    $admin = User::factory()->withRole(SystemRole::HotelAdministrator)->create();
    $clerk = User::factory()->withRole(SystemRole::Receptionist)->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.deactivate', $clerk))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Account deactivated.');

    expect($clerk->fresh()->is_active)->toBeFalse();

    auth()->logout();
    $this->post(route('login.store'), ['email' => $clerk->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account has been deactivated. Please contact the hotel administrator.']);
    $this->assertGuest();
});

it('signs out a deactivated user who still has an open session', function () {
    $user = User::factory()->withRole(SystemRole::Guest)->inactive()->create();

    $this->actingAs($user)
        ->get(route('account.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('assigns only the Guest role on public registration', function () {
    $this->post(route('register.store'), [
        'name' => 'New Guest',
        'email' => 'guest@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roles' => [SystemRole::SuperAdmin->value],
    ]);

    $user = User::query()->where('email', 'guest@example.test')->sole();
    expect($user->getRoleNames()->all())->toBe([SystemRole::Guest->value]);
});
