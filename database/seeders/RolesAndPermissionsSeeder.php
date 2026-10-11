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
 * Default roles only receive missing defaults, so permission changes made by
 * administrators are preserved on rerun. The Super Admin role is always
 * synced to the complete permission set.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::names() as $name) {
            PermissionModel::findOrCreate($name, 'web');
        }

        foreach (SystemRole::cases() as $systemRole) {
            $role = Role::findOrCreate($systemRole->value, 'web');

            if ($systemRole === SystemRole::SuperAdmin) {
                $role->syncPermissions(PermissionModel::all());

                continue;
            }

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions(array_map(
                    fn (Permission $permission): string => $permission->value,
                    $systemRole->defaultPermissions(),
                ));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
