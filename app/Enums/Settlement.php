<?php

namespace App\Enums;

/**
 * How an order or service was paid.
 */
enum Settlement: string
{
    case RoomCharge = 'room_charge';
    case PaidAtOutlet = 'paid_at_outlet';

    public function label(): string
    {
        return match ($this) {
            self::RoomCharge => 'Charged to room',
            self::PaidAtOutlet => 'Paid at outlet',
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
