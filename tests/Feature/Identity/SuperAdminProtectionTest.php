<?php

use App\Enums\SystemRole;
use App\Exceptions\ProtectedAccountException;
use App\Models\User;
use Spatie\Permission\Models\Role;

function protectionTriggered(User|Role $subject, string $attempt): void
{
    test()->assertDatabaseHas('audit_logs', [
        'action' => 'super_admin.protection_triggered',
        'auditable_type' => $subject->getMorphClass(),
        'auditable_id' => $subject->getKey(),
        'new_values->attempt' => $attempt,
    ]);
}

it('refuses to delete the protected account through user management', function () {
    $protected = User::factory()->protectedSuperAdmin()->create();
    $admin = User::factory()->withRole(SystemRole::SuperAdmin)->create();

    $response = $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.destroy', $protected));

    $response->assertRedirect(route('admin.users.index'))
        ->assertInertiaFlash('toast.message', 'The protected Super Admin account cannot be deleted.');
    $this->assertNotSoftDeleted($protected);
    protectionTriggered($protected, 'delete');
});

it('refuses to deactivate the protected account', function () {
    $protected = User::factory()->protectedSuperAdmin()->create();
    $admin = User::factory()->withRole(SystemRole::SuperAdmin)->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.deactivate', $protected))
        ->assertRedirect(route('admin.users.index'));

    expect($protected->fresh()->is_active)->toBeTrue();
    protectionTriggered($protected, 'deactivate');
});

it('refuses to remove the Super Admin role from the protected account', function () {
    $protected = User::factory()->protectedSuperAdmin()->create();
    $admin = User::factory()->withRole(SystemRole::SuperAdmin)->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.update', $protected), [
            'name' => $protected->name,
            'email' => $protected->email,
            'roles' => [SystemRole::Receptionist->value],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($protected->fresh()->hasRole(SystemRole::SuperAdmin->value))->toBeTrue();
    protectionTriggered($protected, 'demote');
});

it('skips the protected account during bulk deletion', function () {
    $protected = User::factory()->protectedSuperAdmin()->create();
    $admin = User::factory()->withRole(SystemRole::SuperAdmin)->create();
    $guest = User::factory()->withRole(SystemRole::Guest)->create();

    $this->actingAs($admin)
        ->post(route('admin.users.bulk-delete'), ['ids' => [$protected->id, $guest->id]])
        ->assertRedirect()
        ->assertInertiaFlash('toast.type', 'warning');

    $this->assertNotSoftDeleted($protected);
    $this->assertSoftDeleted($guest);
});

it('prevents the protected account from deleting itself from profile settings', function () {
    $protected = User::factory()->protectedSuperAdmin()->create();

    $this->actingAs($protected)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('profile.edit'));

    $this->assertAuthenticatedAs($protected);
    $this->assertNotSoftDeleted($protected);
});

it('blocks deletion and deactivation at the model level', function (Closure $operation) {
    $protected = User::factory()->protectedSuperAdmin()->create();

    expect(fn () => $operation($protected))->toThrow(ProtectedAccountException::class);
    expect($protected->fresh())
        ->not->toBeNull()
        ->is_active->toBeTrue()
        ->is_protected->toBeTrue();
})->with([
    'soft delete' => [fn (User $user) => $user->delete()],
    'force delete' => [fn (User $user) => $user->forceDelete()],
    'deactivate' => [fn (User $user) => $user->forceFill(['is_active' => false])->save()],
    'remove protection' => [fn (User $user) => $user->forceFill(['is_protected' => false])->save()],
]);

it('refuses to modify or delete the Super Admin role', function () {
    $admin = User::factory()->withRole(SystemRole::SuperAdmin)->create();
    $role = Role::findByName(SystemRole::SuperAdmin->value);
    $permissionCount = $role->permissions()->count();

    $this->actingAs($admin)
        ->from(route('admin.roles.index'))
        ->put(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => []])
        ->assertRedirect(route('admin.roles.index'));

    $this->actingAs($admin)
        ->from(route('admin.roles.index'))
        ->delete(route('admin.roles.destroy', $role))
        ->assertRedirect(route('admin.roles.index'));

    expect($role->fresh()->permissions()->count())->toBe($permissionCount);
    protectionTriggered($role, 'modify_role');
});

it('provisions the protected Super Admin without storing a password in configuration', function () {
    config(['hms.super_admin.email' => 'owner@hotel.test']);

    $this->artisan('hms:provision-super-admin', ['--generate-password' => true])
        ->expectsOutputToContain('Protected Super Admin ready: owner@hotel.test')
        ->assertSuccessful();

    $user = User::query()->where('email', 'owner@hotel.test')->sole();
    expect($user->is_protected)->toBeTrue()
        ->and($user->hasRole(SystemRole::SuperAdmin->value))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('refuses to provision a second protected account', function () {
    User::factory()->protectedSuperAdmin()->create(['email' => 'first@hotel.test']);

    $this->artisan('hms:provision-super-admin', ['--email' => 'second@hotel.test', '--generate-password' => true])
        ->assertFailed();

    $this->assertDatabaseMissing('users', ['email' => 'second@hotel.test']);
});
