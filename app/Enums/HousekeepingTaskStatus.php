<?php

namespace App\Enums;

/**
 * Lifecycle of a housekeeping task.
 */
enum HousekeepingTaskStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Inspected = 'inspected';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Inspected => 'Inspected',
            self::Reopened => 'Reopened',
        };
    }

    /**
     * Work still to be done (or redone) on the room.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Assigned, self::InProgress, self::Reopened], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return [self::Pending->value, self::Assigned->value, self::InProgress->value, self::Reopened->value];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
