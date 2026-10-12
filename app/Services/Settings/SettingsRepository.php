<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Database-backed hotel settings addressed as "group.key", with defaults
 * defined in SettingsRepository::DEFAULTS.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'hms.settings';

    /**
     * @var array<string, array<string, mixed>>
     */
    public const DEFAULTS = [
        'hotel' => [
            'name' => 'The Midnight Luxe Hotel',
            'tagline' => 'Refined hospitality on the Atlantic coast',
            'email' => 'stay@example.com',
            'phone' => '+231 77 000 0000',
            'address' => 'Tubman Boulevard, Monrovia, Liberia',
            'logo_path' => null,
            'favicon_path' => null,
            'map_embed_url' => null,
        ],
        'currency' => [
            'base' => 'USD',
            'default_display' => 'USD',
        ],
        'booking' => [
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'tax_rate' => '10',
            'service_charge_rate' => '5',
            'deposit_percent' => '30',
            'pending_expiry_minutes' => 60,
            'website_hold_hours' => 24,
            'arrival_reminder_days' => 2,
            'max_advance_days' => 365,
            'max_stay_nights' => 30,
            'reference_prefix' => 'MLX',
            'invoice_prefix' => 'INV',
            'require_inspection_before_ready' => true,
            'early_check_in_fee_percent' => '50',
            'late_check_out_fee_percent' => '50',
        ],
        'social' => [
            'facebook' => null,
            'instagram' => null,
            'x' => null,
            'tripadvisor' => null,
        ],
        'seo' => [
            'meta_title' => null,
            'meta_description' => null,
        ],
        'website' => [
            'hero_eyebrow' => 'Monrovia, Liberia',
            'hero_title' => 'Quiet luxury on the Atlantic coast',
            'hero_subtitle' => 'Ocean-view rooms, Liberian hospitality and a calm place to rest between meetings or adventures.',
            'hero_image_path' => null,
            'about_title' => 'A calm retreat in the heart of the city',
            'about_body' => null,
            'booking_note' => 'Your reservation is held while our team confirms it and arranges your deposit.',
        ],
        'notifications' => [
            'booking_confirmation' => true,
            'payment_receipt' => true,
            'checkout_reminder' => true,
            'review_invitation' => true,
            'low_stock_alert' => true,
        ],
        'loyalty' => [
            'enabled' => true,
            'points_per_unit' => 1,
        ],
        'operations' => [
            'business_date' => null,
            'audit_retention_days' => 730,
        ],
    ];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->all(), $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        return $this->all()[$group] ?? [];
    }

    public function set(string $key, mixed $value): void
    {
        [$group, $name] = explode('.', $key, 2);

        Setting::updateOrCreate(['group' => $group, 'key' => $name], ['value' => $value]);

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(string $group, array $values): void
    {
        foreach ($values as $name => $value) {
            Setting::updateOrCreate(['group' => $group, 'key' => $name], ['value' => $value]);
        }

        $this->flush();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->loaded ??= Cache::rememberForever(self::CACHE_KEY, function (): array {
            $settings = self::DEFAULTS;
            $settings['currency']['base'] = config('hms.currency.base');
            $settings['currency']['default_display'] = config('hms.currency.default_display');

            foreach (Setting::query()->get(['group', 'key', 'value']) as $setting) {
                $settings[$setting->group][$setting->key] = $setting->value;
            }

            return $settings;
        });
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}
