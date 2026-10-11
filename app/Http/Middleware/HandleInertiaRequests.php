<?php

namespace App\Http\Middleware;

use App\Enums\Currency;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private SettingsRepository $settings) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $hotel = $this->settings->group('hotel');

        return [
            ...parent::share($request),
            'name' => $hotel['name'] ?? config('app.name'),
            'hotel' => [
                'name' => $hotel['name'] ?? config('app.name'),
                'tagline' => $hotel['tagline'] ?? null,
                'email' => $hotel['email'] ?? null,
                'phone' => $hotel['phone'] ?? null,
                'address' => $hotel['address'] ?? null,
                'logo_url' => filled($hotel['logo_path'] ?? null) ? Storage::disk('public')->url($hotel['logo_path']) : null,
                'social' => $this->settings->group('social'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar_url,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
                    'preferred_currency' => $user->preferred_currency?->value,
                    'is_staff' => $user->isStaff(),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ] : null,
                'roles' => $user?->getRoleNames()->values() ?? [],
                'permissions' => $user?->getAllPermissions()->pluck('name')->values() ?? [],
            ],
            'currency' => [
                'base' => $this->settings->get('currency.base'),
                'display' => $this->displayCurrency($request),
                'available' => Currency::codes(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The guest's display preference: saved profile value, then cookie, then the hotel default.
     */
    private function displayCurrency(Request $request): string
    {
        $saved = $request->user()?->preferred_currency;

        if ($saved instanceof Currency) {
            return $saved->value;
        }

        $fromCookie = Currency::tryFrom($request->cookies->getString((string) config('hms.currency.cookie')));

        return $fromCookie->value ?? (string) $this->settings->get('currency.default_display', 'USD');
    }
}
