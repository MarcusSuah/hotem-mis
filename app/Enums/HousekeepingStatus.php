<?php

namespace App\Enums;

enum HousekeepingStatus: string
{
    case Dirty = 'dirty';
    case Cleaning = 'cleaning';
    case Clean = 'clean';
    case Inspected = 'inspected';

    public function label(): string
    {
        return match ($this) {
            self::Dirty => 'Dirty',
            self::Cleaning => 'Cleaning',
            self::Clean => 'Clean',
            self::Inspected => 'Inspected',
        };
    }

    /**
     * Whether a guest may be checked in, given the hotel's inspection policy.
     */
    public function isReady(bool $requireInspection): bool
    {
        return $requireInspection ? $this === self::Inspected : in_array($this, [self::Clean, self::Inspected], true);
    }
}
