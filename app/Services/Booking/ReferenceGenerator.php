<?php

namespace App\Services\Booking;

use App\Models\Reservation;
use App\Services\Settings\SettingsRepository;

/**
 * Booking references such as "MLX-7KQ4TZ": a configurable prefix and six
 * characters from an alphabet without look-alikes (no 0/O, 1/I).
 */
class ReferenceGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private SettingsRepository $settings) {}

    public function generate(): string
    {
        $prefix = strtoupper((string) $this->settings->get('booking.reference_prefix', 'HMS'));

        do {
            $code = '';

            for ($i = 0; $i < 6; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            $reference = "{$prefix}-{$code}";
        } while (Reservation::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
