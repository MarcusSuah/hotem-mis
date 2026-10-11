<?php

namespace App\Enums;

/**
 * Charge applied when a reservation is cancelled after the free window.
 */
enum CancellationPenalty: string
{
    case None = 'none';
    case FirstNight = 'first_night';
    case Percentage = 'percentage';
    case FullStay = 'full_stay';

    public function label(): string
    {
        return match ($this) {
            self::None => 'No charge',
            self::FirstNight => 'First night',
            self::Percentage => 'Percentage of stay',
            self::FullStay => 'Full stay',
        };
    }
}
