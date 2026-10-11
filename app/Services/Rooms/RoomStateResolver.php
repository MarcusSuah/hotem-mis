<?php

namespace App\Services\Rooms;

use App\Enums\OperationalStatus;
use App\Enums\ReservationStatus;
use App\Models\ReservationNight;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Derives each room's front-desk state for one date from its operational
 * status, active blocks and held nights. Housekeeping condition is reported
 * separately and never overrides the occupancy state.
 */
class RoomStateResolver
{
    public const AVAILABLE = 'available';

    public const RESERVED = 'reserved';

    public const OCCUPIED = 'occupied';

    public const MAINTENANCE = 'maintenance';

    public const OUT_OF_SERVICE = 'out_of_service';

    public const BLOCKED = 'blocked';

    /**
     * @param  Collection<int, Room>  $rooms
     * @return array<int, array{state: string, reservation: string|null, block_id: int|null}>
     */
    public function resolve(Collection $rooms, CarbonImmutable $date): array
    {
        $roomIds = $rooms->modelKeys();

        $nights = ReservationNight::query()
            ->whereIn('room_id', $roomIds)
            ->whereDate('night_date', $date)
            ->with('reservation:id,reference,status')
            ->get()
            ->keyBy('room_id');

        $blocks = RoomBlock::query()
            ->whereIn('room_id', $roomIds)
            ->whereNull('released_at')
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>', $date->toDateString())
            ->get()
            ->keyBy('room_id');

        $states = [];

        foreach ($rooms as $room) {
            $night = $nights->get($room->id);
            $block = $blocks->get($room->id);

            $state = match (true) {
                $room->operational_status === OperationalStatus::OutOfService => self::OUT_OF_SERVICE,
                $night !== null && $night->reservation?->status === ReservationStatus::CheckedIn => self::OCCUPIED,
                $night !== null => self::RESERVED,
                $block !== null => $block->type->removesInventory()
                    ? ($block->type->value === 'maintenance' ? self::MAINTENANCE : self::OUT_OF_SERVICE)
                    : self::BLOCKED,
                default => self::AVAILABLE,
            };

            $states[$room->id] = [
                'state' => $state,
                'reservation' => $night?->reservation?->reference,
                'block_id' => $block?->id,
            ];
        }

        return $states;
    }
}
