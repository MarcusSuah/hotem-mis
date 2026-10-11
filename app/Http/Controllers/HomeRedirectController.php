<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends authenticated users to the area that matches their account type.
 */
class HomeRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return $request->user()->isStaff()
            ? to_route('admin.dashboard')
            : to_route('account.dashboard');
    }
}
