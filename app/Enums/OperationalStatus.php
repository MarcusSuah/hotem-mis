<?php

namespace App\Enums;

/**
 * Long-term condition of a room. Short-term outages are recorded as room
 * blocks for a date range instead.
 */
enum OperationalStatus: string
{
    case InService = 'in_service';
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::InService => 'In Service',
            self::OutOfService => 'Out of Service',
        };
    }
}
