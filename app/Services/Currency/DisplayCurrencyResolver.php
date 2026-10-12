<?php

namespace App\Services\Currency;

use App\Enums\Currency;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Request;

/**
 * Decides which currency prices are shown in: the signed-in user's saved
 * preference, then the visitor's cookie, then the hotel default.
 */
class DisplayCurrencyResolver
{
    public function __construct(private SettingsRepository $settings) {}

    public function resolve(Request $request): Currency
    {
        $saved = $request->user()?->preferred_currency;

        if ($saved instanceof Currency) {
            return $saved;
        }

        return Currency::tryFrom($request->cookies->getString((string) config('hms.currency.cookie')))
            ?? Currency::tryFrom((string) $this->settings->get('currency.default_display', 'USD'))
            ?? Currency::USD;
    }
}
