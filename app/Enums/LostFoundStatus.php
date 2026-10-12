<?php

namespace App\Enums;

/**
 * State of a lost-and-found item.
 */
enum LostFoundStatus: string
{
    case Stored = 'stored';
    case Claimed = 'claimed';
    case Disposed = 'disposed';

    public function label(): string
    {
        return match ($this) {
            self::Stored => 'In storage',
            self::Claimed => 'Returned to owner',
            self::Disposed => 'Disposed',
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
