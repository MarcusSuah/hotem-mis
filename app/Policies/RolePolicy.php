<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::RolesView->value);
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RolesView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::RolesCreate->value);
    }

    /**
     * The Super Admin role always holds every permission and is never editable.
     */
    public function update(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RolesUpdate->value)
            && $role->name !== SystemRole::SuperAdmin->value;
    }

    /**
     * Built-in roles cannot be deleted, nor can roles still assigned to users.
     */
    public function delete(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RolesDelete->value)
            && ! in_array($role->name, SystemRole::names(), true);
    }
}
