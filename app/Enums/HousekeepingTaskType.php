<?php

namespace App\Enums;

/**
 * Kinds of housekeeping work.
 */
enum HousekeepingTaskType: string
{
    case DepartureClean = 'departure_clean';
    case Stayover = 'stayover';
    case DeepClean = 'deep_clean';
    case Turndown = 'turndown';
    case Inspection = 'inspection';

    public function label(): string
    {
        return match ($this) {
            self::DepartureClean => 'Departure clean',
            self::Stayover => 'Stayover service',
            self::DeepClean => 'Deep clean',
            self::Turndown => 'Turndown',
            self::Inspection => 'Inspection only',
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
