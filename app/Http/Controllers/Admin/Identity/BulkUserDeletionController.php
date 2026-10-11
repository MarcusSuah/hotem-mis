<?php

namespace App\Http\Controllers\Admin\Identity;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\Identity\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BulkUserDeletionController extends Controller
{
    /**
     * Delete the selected users. The protected Super Admin, the acting user and
     * any account the actor may not manage are always excluded.
     */
    public function __invoke(Request $request, UserAccountService $accounts): RedirectResponse
    {
        Gate::authorize(Permission::UsersDelete->value);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $requested = count($validated['ids']);
        $deleted = $accounts->bulkDelete(array_values(array_map('intval', $validated['ids'])), $request->user());
        $skipped = $requested - $deleted;

        Inertia::flash('toast', [
            'type' => $skipped > 0 ? 'warning' : 'success',
            'message' => $skipped > 0
                ? trans_choice('{1} Deleted :deleted user; :skipped protected or restricted account was skipped.|[2,*] Deleted :deleted users; :skipped protected or restricted accounts were skipped.', $skipped, ['deleted' => $deleted, 'skipped' => $skipped])
                : trans_choice('{1} Deleted :count user.|[2,*] Deleted :count users.', $deleted, ['count' => $deleted]),
        ]);

        return back();
    }
}
