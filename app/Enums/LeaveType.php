<?php

namespace App\Enums;

/**
 * Kinds of leave.
 */
enum LeaveType: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Maternity = 'maternity';
    case Unpaid = 'unpaid';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual leave',
            self::Sick => 'Sick leave',
            self::Maternity => 'Maternity / paternity',
            self::Unpaid => 'Unpaid leave',
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
