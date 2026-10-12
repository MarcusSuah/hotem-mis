<?php

namespace App\Enums;

/**
 * Lifecycle of an outlet order.
 */
enum OrderStatus: string
{
    case Open = 'open';
    case Preparing = 'preparing';
    case Served = 'served';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Preparing => 'Preparing',
            self::Served => 'Served',
            self::Settled => 'Settled',
            self::Cancelled => 'Cancelled',
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
