<?php

namespace App\Enums;

/**
 * Areas of maintenance work.
 */
enum MaintenanceCategory: string
{
    case Electrical = 'electrical';
    case Plumbing = 'plumbing';
    case Hvac = 'hvac';
    case Furniture = 'furniture';
    case Appliance = 'appliance';
    case Damage = 'damage';
    case Preventive = 'preventive';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electrical => 'Electrical',
            self::Plumbing => 'Plumbing',
            self::Hvac => 'Air conditioning',
            self::Furniture => 'Furniture & fixtures',
            self::Appliance => 'Appliances',
            self::Damage => 'Guest damage',
            self::Preventive => 'Preventive',
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
