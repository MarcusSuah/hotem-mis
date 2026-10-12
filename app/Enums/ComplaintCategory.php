<?php

namespace App\Enums;

/**
 * Topics of guest complaints.
 */
enum ComplaintCategory: string
{
    case Room = 'room';
    case Service = 'service';
    case Noise = 'noise';
    case Billing = 'billing';
    case FoodBeverage = 'food_beverage';
    case Facilities = 'facilities';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Room condition',
            self::Service => 'Staff & service',
            self::Noise => 'Noise',
            self::Billing => 'Billing',
            self::FoodBeverage => 'Food & beverage',
            self::Facilities => 'Facilities',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
