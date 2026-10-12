<?php

namespace App\Enums;

/**
 * Outlet order channels.
 */
enum OrderType: string
{
    case Restaurant = 'restaurant';
    case Bar = 'bar';
    case RoomService = 'room_service';

    public function label(): string
    {
        return match ($this) {
            self::Restaurant => 'Restaurant',
            self::Bar => 'Bar',
            self::RoomService => 'Room service',
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
