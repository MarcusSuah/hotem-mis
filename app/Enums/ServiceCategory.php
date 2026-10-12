<?php

namespace App\Enums;

/**
 * Categories of additional guest services.
 */
enum ServiceCategory: string
{
    case Spa = 'spa';
    case Laundry = 'laundry';
    case Transport = 'transport';
    case Events = 'events';
    case Dining = 'dining';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spa => 'Spa & wellness',
            self::Laundry => 'Laundry',
            self::Transport => 'Airport & transport',
            self::Events => 'Events & conferences',
            self::Dining => 'Private dining',
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
