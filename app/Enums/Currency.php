<?php

namespace App\Enums;

enum Currency: string
{
    case USD = 'USD';
    case LRD = 'LRD';

    public function label(): string
    {
        return match ($this) {
            self::USD => 'United States Dollar',
            self::LRD => 'Liberian Dollar',
        };
    }

    public function symbol(): string
    {
        return match ($this) {
            self::USD => '$',
            self::LRD => 'L$',
        };
    }

    /**
     * Number of decimal places the currency supports (ISO 4217 minor units).
     */
    public function minorUnits(): int
    {
        return 2;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::cases(), 'value');
    }
}
