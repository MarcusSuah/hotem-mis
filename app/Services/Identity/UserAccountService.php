<?php

namespace App\Services\Identity;

use App\Enums\SystemRole;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserAccountService
{
    public function __construct(
        private ProtectedAccountGuard $guard,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string|null, password: string}  $attributes
     * @param  list<string>  $roleNames
     */
    public function create(array $attributes, array $roleNames, User $actor): User
    {
        $this->ensureActorMayGrant($actor, $roleNames);

        return DB::transaction(function () use ($attributes, $roleNames): User {
            $user = new User($attributes);
            $user->email_verified_at = now();
            $user->save();

            $user->syncRoles($roleNames);

            $this->audit->record('user.created', $user, newValues: [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roleNames,
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name: string, email: string, phone: string|null, password: string|null}  $attributes
     * @param  list<string>  $roleNames
     */
    public function update(User $user, array $attributes, array $roleNames, User $actor): User
    {
        $this->guard->ensureRolesKeepProtection($user, $roleNames);
        $this->ensureActorMayChangeRoles($actor, $user, $roleNames);

        return DB::transaction(function () use ($user, $attributes, $roleNames): User {
            if (blank($attributes['password'] ?? null)) {
                unset($attributes['password']);
            }

            $user->fill($attributes);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
            $this->audit->recordChanges('user.updated', $user);

            $previousRoles = $user->getRoleNames()->sort()->values()->all();
            $user->syncRoles($roleNames);
            $currentRoles = collect($roleNames)->sort()->values()->all();

            if ($previousRoles !== $currentRoles) {
                $this->audit->record('user.roles_changed', $user, ['roles' => $previousRoles], ['roles' => $currentRoles]);
            }

            return $user;
        });
    }

    public function deactivate(User $user, User $actor): void
    {
        $this->guard->ensureDeactivatable($user);
        $this->ensureNotSelf($actor, $user, 'deactivate');

        $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();

        $this->audit->record('user.deactivated', $user);
    }

    public function activate(User $user): void
    {
        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();

        $this->audit->record('user.activated', $user);
    }

    public function delete(User $user, User $actor): void
    {
        $this->guard->ensureDeletable($user);
        $this->ensureNotSelf($actor, $user, 'delete');

        $user->delete();

        $this->audit->record('user.deleted', $user, ['email' => $user->email]);
    }

    /**
     * Delete many users, silently excluding the protected account, the actor and
     * any account the actor may not manage. Returns the number deleted.
     *
     * @param  list<int>  $userIds
     */
    public function bulkDelete(array $userIds, User $actor): int
    {
        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('is_protected', false)
            ->whereKeyNot($actor->id)
            ->get();

        $deletable = $users->filter(fn (User $user): bool => $actor->can('delete', $user));

        DB::transaction(fn () => $deletable->each(fn (User $user) => $this->delete($user, $actor)));

        return $deletable->count();
    }

    /**
     * Prevent privilege escalation: an actor may only grant roles whose
     * permissions they hold themselves. Only Super Admins grant Super Admin.
     *
     * @param  list<string>  $roleNames
     */
    public function ensureActorMayGrant(User $actor, array $roleNames): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if (in_array(SystemRole::SuperAdmin->value, $roleNames, true)) {
            throw ValidationException::withMessages([
                'roles' => 'Only a Super Admin can assign the Super Admin role.',
            ]);
        }

        $actorPermissions = $actor->getAllPermissions()->pluck('name');

        $exceeding = Role::query()
            ->whereIn('name', $roleNames)
            ->with('permissions:id,name')
            ->get()
            ->filter(fn (Role $role): bool => $role->permissions->pluck('name')->diff($actorPermissions)->isNotEmpty())
            ->pluck('name');

        if ($exceeding->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'You cannot assign roles with permissions you do not hold: '.$exceeding->join(', ').'.',
            ]);
        }
    }

    /**
     * @param  list<string>  $roleNames
     */
    private function ensureActorMayChangeRoles(User $actor, User $target, array $roleNames): void
    {
        $added = array_values(array_diff($roleNames, $target->getRoleNames()->all()));
        $removed = array_values(array_diff($target->getRoleNames()->all(), $roleNames));

        $this->ensureActorMayGrant($actor, $added);

        if ($removed !== [] && $actor->is($target) && in_array(SystemRole::SuperAdmin->value, $removed, true)) {
            throw ValidationException::withMessages(['roles' => 'You cannot remove your own Super Admin role.']);
        }

        if (! $actor->isSuperAdmin() && in_array(SystemRole::SuperAdmin->value, $removed, true)) {
            throw ValidationException::withMessages(['roles' => 'Only a Super Admin can remove the Super Admin role.']);
        }
    }

    private function ensureNotSelf(User $actor, User $target, string $operation): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages(['user' => "You cannot {$operation} your own account."]);
        }
    }
}
