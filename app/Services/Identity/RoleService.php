<?php

namespace App\Services\Identity;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        private ProtectedAccountGuard $guard,
        private AuditLogger $audit,
        private PermissionRegistrar $registrar,
    ) {}

    /**
     * @param  list<string>  $permissionNames
     */
    public function create(string $name, array $permissionNames, User $actor): Role
    {
        $this->ensureActorHoldsPermissions($actor, $permissionNames);

        return DB::transaction(function () use ($name, $permissionNames): Role {
            // Uniqueness is validated by the form request; save() keeps the concrete model type.
            $role = new Role(['name' => $name, 'guard_name' => 'web']);
            $role->save();
            $role->syncPermissions($permissionNames);

            $this->audit->record('role.created', $role, newValues: ['name' => $name, 'permissions' => $permissionNames]);

            return $role;
        });
    }

    /**
     * @param  list<string>  $permissionNames
     */
    public function update(Role $role, string $name, array $permissionNames, User $actor): Role
    {
        $this->guard->ensureRoleModifiable($role);

        $current = $role->permissions->pluck('name')->all();
        $this->ensureActorHoldsPermissions($actor, array_values(array_diff($permissionNames, $current)));

        if ($role->name !== $name && in_array($role->name, SystemRole::names(), true)) {
            throw ValidationException::withMessages(['name' => 'Built-in roles cannot be renamed.']);
        }

        return DB::transaction(function () use ($role, $name, $permissionNames, $current): Role {
            $previousName = $role->name;
            $role->update(['name' => $name]);
            $role->syncPermissions($permissionNames);

            $this->audit->record(
                'role.updated',
                $role,
                ['name' => $previousName, 'permissions' => $current],
                ['name' => $name, 'permissions' => $permissionNames],
            );

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        $this->guard->ensureRoleModifiable($role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'Reassign the users holding this role before deleting it.']);
        }

        $this->audit->record('role.deleted', $role, ['name' => $role->name]);

        $role->delete();
    }

    /**
     * Create a custom permission. The Super Admin role always receives it.
     */
    public function createPermission(string $name): Permission
    {
        return DB::transaction(function () use ($name): Permission {
            $permission = new Permission(['name' => $name, 'guard_name' => 'web']);
            $permission->save();

            Role::findByName(SystemRole::SuperAdmin->value, 'web')->givePermissionTo($permission);
            $this->registrar->forgetCachedPermissions();

            $this->audit->record('permission.created', $permission, newValues: ['name' => $name]);

            return $permission;
        });
    }

    /**
     * Actors may not hand out permissions they do not hold themselves.
     *
     * @param  list<string>  $permissionNames
     */
    private function ensureActorHoldsPermissions(User $actor, array $permissionNames): void
    {
        if ($actor->isSuperAdmin() || $permissionNames === []) {
            return;
        }

        $missing = collect($permissionNames)->diff($actor->getAllPermissions()->pluck('name'));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot grant permissions you do not hold: '.$missing->join(', ').'.',
            ]);
        }
    }
}
