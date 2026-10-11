<?php

namespace App\Services\Identity;

use App\Enums\SystemRole;
use App\Exceptions\ProtectedAccountException;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Spatie\Permission\Models\Role;

/**
 * Central checks that protect the Super Admin account and role. Every
 * destructive identity workflow calls into this guard before acting, and
 * every blocked attempt is audited.
 */
class ProtectedAccountGuard
{
    public function __construct(private AuditLogger $audit) {}

    public function ensureDeletable(User $target): void
    {
        if ($target->is_protected) {
            $this->block($target, 'delete', ProtectedAccountException::cannotDelete());
        }
    }

    public function ensureDeactivatable(User $target): void
    {
        if ($target->is_protected) {
            $this->block($target, 'deactivate', ProtectedAccountException::cannotDeactivate());
        }
    }

    /**
     * @param  list<string>  $roleNames
     */
    public function ensureRolesKeepProtection(User $target, array $roleNames): void
    {
        if ($target->is_protected && ! in_array(SystemRole::SuperAdmin->value, $roleNames, true)) {
            $this->block($target, 'demote', ProtectedAccountException::cannotDemote());
        }
    }

    public function ensureRoleModifiable(Role $role): void
    {
        if ($role->name === SystemRole::SuperAdmin->value) {
            $this->audit->record('super_admin.protection_triggered', $role, newValues: ['attempt' => 'modify_role']);

            throw ProtectedAccountException::cannotModifySuperAdminRole();
        }
    }

    private function block(User $target, string $attempt, ProtectedAccountException $exception): never
    {
        $this->audit->record('super_admin.protection_triggered', $target, newValues: ['attempt' => $attempt]);

        throw $exception;
    }
}
