<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'staff' => User::query()->withoutRole(SystemRole::Guest->value)->whereHas('roles')->count(),
                'guestAccounts' => User::query()->role(SystemRole::Guest->value)->count(),
                'inactiveAccounts' => User::query()->where('is_active', false)->count(),
            ],
        ]);
    }
}
