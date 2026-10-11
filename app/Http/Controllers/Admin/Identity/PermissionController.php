<?php

namespace App\Http\Controllers\Admin\Identity;

use App\Enums\Permission as PermissionName;
use App\Http\Controllers\Controller;
use App\Services\Identity\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PermissionController extends Controller
{
    public function store(Request $request, RoleService $roles): RedirectResponse
    {
        Gate::authorize(PermissionName::PermissionsAssign->value);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9-]+\.[a-z0-9-]+$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ], [
            'name.regex' => 'Use the "area.action" format with lowercase letters, numbers and dashes.',
        ]);

        $roles->createPermission($validated['name']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permission created.')]);

        return back();
    }
}
