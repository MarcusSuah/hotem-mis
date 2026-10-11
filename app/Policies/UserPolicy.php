<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersCreate->value);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersUpdate->value)
            && $this->mayManage($actor, $target);
    }

    /**
     * Deactivation follows update rules but never applies to the protected
     * account or the actor's own account.
     */
    public function deactivate(User $actor, User $target): bool
    {
        return $this->update($actor, $target)
            && ! $target->is_protected
            && ! $actor->is($target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersDelete->value)
            && ! $target->is_protected
            && ! $actor->is($target)
            && $this->mayManage($actor, $target);
    }

    /**
     * Super Admin accounts can only be managed by Super Admins.
     */
    private function mayManage(User $actor, User $target): bool
    {
        return ! $target->isSuperAdmin() || $actor->isSuperAdmin();
    }
}
