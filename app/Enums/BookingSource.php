<?php

namespace App\Enums;

/**
 * The channel a reservation arrived through.
 */
enum BookingSource: string
{
    case Website = 'website';
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Email = 'email';
    case TravelAgent = 'travel_agent';
    case Ota = 'ota';
    case Corporate = 'corporate';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Website',
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone',
            self::Email => 'Email',
            self::TravelAgent => 'Travel Agent',
            self::Ota => 'Online Travel Agency',
            self::Corporate => 'Corporate',
        };
    }

    /**
     * Channels staff can record when creating a reservation in the back office.
     *
     * @return list<self>
     */
    public static function staffChannels(): array
    {
        return [self::WalkIn, self::Phone, self::Email, self::TravelAgent, self::Ota, self::Corporate];
    }
}
