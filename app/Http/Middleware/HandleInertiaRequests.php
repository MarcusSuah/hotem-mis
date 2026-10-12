<?php

namespace App\Http\Middleware;

use App\Enums\Currency;
use App\Models\Page;
use App\Services\Currency\DisplayCurrencyResolver;
use App\Services\FrontDesk\BackOfficeSummary;
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

    public function __construct(
        private SettingsRepository $settings,
        private DisplayCurrencyResolver $displayCurrency,
    ) {}

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
                'url' => rtrim((string) config('app.url'), '/'),
                'hero_image_url' => filled($this->settings->get('website.hero_image_path')) ? Storage::disk(config('hms.uploads.public_disk'))->url((string) $this->settings->get('website.hero_image_path')) : null,
            ],
            'backOffice' => fn () => $user !== null && $user->isStaff() && $request->routeIs('admin.*') ? app(BackOfficeSummary::class)->for($user) : null,
            'sitePages' => fn () => $request->routeIs('admin.*') ? [] : Page::query()->published()->where('show_in_footer', true)->orderBy('display_order')->get(['title', 'slug']),
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
                'display' => $this->displayCurrency->resolve($request)->value,
                'available' => Currency::codes(),
            ],
            'notifications' => fn () => $user?->isStaff() ? [
                'unread' => $user->unreadNotifications()->count(),
                'recent' => $user->unreadNotifications()->limit(5)->get()->map(fn ($notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Notification',
                    'message' => $notification->data['message'] ?? '',
                    'level' => $notification->data['level'] ?? 'info',
                    'created_at' => $notification->created_at?->toIso8601String(),
                ]),
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
