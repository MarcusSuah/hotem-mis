<?php

namespace App\Enums;

enum RoomBlockType: string
{
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';
    case Hold = 'hold';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Under Maintenance',
            self::OutOfService => 'Out of Service',
            self::Hold => 'Blocked',
        };
    }

    /**
     * Maintenance and out-of-service nights are not sellable and are excluded
     * from the occupancy denominator; administrative holds remain sellable inventory.
     */
    public function removesInventory(): bool
    {
        return $this !== self::Hold;
    }
}
