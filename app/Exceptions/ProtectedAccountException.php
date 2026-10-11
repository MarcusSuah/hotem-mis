<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

/**
 * Raised whenever an operation would delete, deactivate, demote or otherwise
 * weaken the protected Super Admin account.
 */
class ProtectedAccountException extends RuntimeException
{
    public static function cannotDelete(): self
    {
        return new self('The protected Super Admin account cannot be deleted.');
    }

    public static function cannotDeactivate(): self
    {
        return new self('The protected Super Admin account cannot be deactivated.');
    }

    public static function cannotDemote(): self
    {
        return new self('The protected Super Admin account must keep the Super Admin role.');
    }

    public static function cannotUnprotect(): self
    {
        return new self('The Super Admin protection flag cannot be removed.');
    }

    public static function cannotModifySuperAdminRole(): self
    {
        return new self('The Super Admin role always holds every permission and cannot be modified or deleted.');
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $this->getMessage()], 403);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $this->getMessage()]);

        return back(303);
    }
}
