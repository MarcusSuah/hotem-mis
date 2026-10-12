<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

/**
 * Remembers the currency a visitor wants prices displayed in. Signed-in
 * users keep it on their profile; everyone else gets a cookie. Amounts
 * already agreed on bookings, folios and invoices are never changed.
 */
class CurrencyPreferenceController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $currency = Currency::from($request->validate([
            'currency' => ['required', Rule::in(Currency::codes())],
        ])['currency']);

        $request->user()?->forceFill(['preferred_currency' => $currency])->save();

        Cookie::queue(Cookie::make((string) config('hms.currency.cookie'), $currency->value, 60 * 24 * 365, httpOnly: false, sameSite: 'lax'));

        return back();
    }
}
