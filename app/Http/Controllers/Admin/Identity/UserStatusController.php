<?php

namespace App\Http\Controllers\Admin\Identity;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\ProtectedAccountGuard;
use App\Services\Identity\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Activation is modelled as a sub-resource: POST activates, DELETE deactivates.
 */
class UserStatusController extends Controller
{
    public function store(User $user, UserAccountService $accounts): RedirectResponse
    {
        Gate::authorize('update', $user);

        $accounts->activate($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account activated.')]);

        return back();
    }

    public function destroy(Request $request, User $user, UserAccountService $accounts, ProtectedAccountGuard $guard): RedirectResponse
    {
        Gate::authorize('update', $user);

        $guard->ensureDeactivatable($user);

        Gate::authorize('deactivate', $user);

        $accounts->deactivate($user, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account deactivated.')]);

        return back();
    }
}
