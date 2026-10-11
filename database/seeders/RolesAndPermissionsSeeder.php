<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\SystemRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotently provisions every permission and the default roles.
 *
 * - New roles receive their full default permission set.
 * - Existing roles keep any changes administrators made, and only receive
 *   permissions introduced since the last run that belong to their defaults.
 * - The Super Admin role is always synced to the complete permission set.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $introduced = [];

        foreach (Permission::names() as $name) {
            $permission = PermissionModel::findOrCreate($name, 'web');

            if ($permission->wasRecentlyCreated) {
                $introduced[] = $name;
            }
        }

        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::findOrCreate($systemRole->value, 'web');

            if ($systemRole === SystemRole::SuperAdmin) {
                $role->syncPermissions(PermissionModel::all());

                continue;
            }

            $defaults = array_map(fn (Permission $permission): string => $permission->value, $systemRole->defaultPermissions());

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($defaults);

                continue;
            }

            $newDefaults = array_values(array_intersect($defaults, $introduced));

            if ($newDefaults !== []) {
                $role->givePermissionTo($newDefaults);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
