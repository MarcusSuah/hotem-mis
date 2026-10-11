<?php

namespace App\Http\Controllers\Admin\Identity;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Identity\StoreUserRequest;
use App\Http\Requests\Admin\Identity\UpdateUserRequest;
use App\Models\User;
use App\Services\Identity\ProtectedAccountGuard;
use App\Services\Identity\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $actor = $request->user();

        $users = User::query()
            ->with('roles:id,name')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(
                fn ($inner) => $inner->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%")->orWhereLike('phone', "%{$search}%"),
            ))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->role($role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'is_active' => $user->is_active,
                'is_protected' => $user->is_protected,
                'roles' => $user->roles->pluck('name'),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'can' => [
                    'update' => $actor->can('update', $user),
                    'delete' => $actor->can('delete', $user),
                    'deactivate' => $actor->can('deactivate', $user),
                ],
            ]);

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
            ],
            'roles' => Role::query()->orderBy('name')->pluck('name')
                ->reject(fn (string $name): bool => $name === SystemRole::SuperAdmin->value && ! $actor->isSuperAdmin())
                ->values(),
            'can' => [
                'create' => $actor->can('create', User::class),
                'bulkDelete' => $actor->can(Permission::UsersDelete->value),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, UserAccountService $accounts): RedirectResponse
    {
        $accounts->create(
            $request->accountAttributes(),
            $request->roleNames(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user, UserAccountService $accounts): RedirectResponse
    {
        $accounts->update(
            $user,
            $request->accountAttributes(),
            $request->roleNames(),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }

    public function destroy(Request $request, User $user, UserAccountService $accounts, ProtectedAccountGuard $guard): RedirectResponse
    {
        Gate::authorize(Permission::UsersDelete->value);

        // Explain (and audit) attempts against the protected account before the generic policy denial.
        $guard->ensureDeletable($user);

        Gate::authorize('delete', $user);

        $accounts->delete($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return back();
    }
}
