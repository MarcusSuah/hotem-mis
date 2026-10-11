<?php

namespace App\Http\Requests\Admin\Identity;

use App\Enums\Permission;
use App\Services\Identity\ProtectedAccountGuard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Shared by role creation and update; authorization is resolved per route.
 */
class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        if ($role === null) {
            return $this->user()->can('create', Role::class);
        }

        if ($this->user()->can(Permission::RolesUpdate->value)) {
            // Explain (and audit) attempts to edit the Super Admin role.
            app(ProtectedAccountGuard::class)->ensureRoleModifiable($role);
        }

        return $this->user()->can('update', $role);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role?->id),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_values($this->validated('permissions', []));
    }
}
