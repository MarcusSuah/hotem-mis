<?php

namespace App\Http\Controllers\Admin\Identity;

use App\Enums\Permission as PermissionName;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Identity\SaveRoleRequest;
use App\Services\Identity\ProtectedAccountGuard;
use App\Services\Identity\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Role::class);

        $actor = $request->user();

        $roles = Role::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'is_system' => in_array($role->name, SystemRole::names(), true),
                'is_super_admin' => $role->name === SystemRole::SuperAdmin->value,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->values(),
                'can' => [
                    'update' => $actor->can('update', $role),
                    'delete' => $actor->can('delete', $role),
                ],
            ]);

        $permissions = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (string $name): array => [
                'name' => $name,
                'group' => PermissionName::tryFrom($name)?->group() ?? 'Custom',
            ])
            ->groupBy('group')
            ->map(fn ($group) => $group->pluck('name')->values());

        return Inertia::render('admin/roles/index', [
            'roles' => $roles,
            'permissionGroups' => $permissions,
            'can' => [
                'create' => $actor->can('create', Role::class),
                'createPermission' => $actor->can(PermissionName::PermissionsAssign->value),
            ],
        ]);
    }

    public function store(SaveRoleRequest $request, RoleService $roles): RedirectResponse
    {
        $roles->create($request->validated('name'), $request->permissionNames(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role created.')]);

        return back();
    }

    public function update(SaveRoleRequest $request, Role $role, RoleService $roles): RedirectResponse
    {
        $roles->update($role, $request->validated('name'), $request->permissionNames(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role updated.')]);

        return back();
    }

    public function destroy(Role $role, RoleService $roles, ProtectedAccountGuard $guard): RedirectResponse
    {
        Gate::authorize(PermissionName::RolesDelete->value);

        $guard->ensureRoleModifiable($role);

        Gate::authorize('delete', $role);

        $roles->delete($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Role deleted.')]);

        return back();
    }
}
