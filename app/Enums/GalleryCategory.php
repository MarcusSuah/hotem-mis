<?php

namespace App\Enums;

/**
 * Grouping used to filter the public photo gallery.
 */
enum GalleryCategory: string
{
    case Rooms = 'rooms';
    case Dining = 'dining';
    case Wellness = 'wellness';
    case Events = 'events';
    case Grounds = 'grounds';

    public function label(): string
    {
        return match ($this) {
            self::Rooms => 'Rooms & suites',
            self::Dining => 'Dining',
            self::Wellness => 'Wellness',
            self::Events => 'Events',
            self::Grounds => 'Hotel & grounds',
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
