<?php

namespace App\Services\Rooms;

use App\Enums\RoomBlockType;
use App\Models\ReservationNight;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\StayPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes rooms out of sale for a date range. A block may not cover nights
 * already sold; those reservations must be moved to another room first.
 */
class RoomBlockService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(
        Room $room,
        RoomBlockType $type,
        StayPeriod $period,
        string $reason,
        ?User $actor = null,
        ?int $maintenanceRequestId = null,
    ): RoomBlock {
        return DB::transaction(function () use ($room, $type, $period, $reason, $actor, $maintenanceRequestId): RoomBlock {
            Room::query()->whereKey($room->id)->lockForUpdate()->first();

            $conflicts = ReservationNight::query()
                ->where('room_id', $room->id)
                ->where('night_date', '>=', $period->checkIn->toDateString())
                ->where('night_date', '<', $period->checkOut->toDateString())
                ->with('reservation:id,reference')
                ->get()
                ->pluck('reservation.reference')
                ->unique()
                ->values();

            if ($conflicts->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'start_date' => "Room {$room->number} is reserved during this period ({$conflicts->join(', ')}). Reassign those reservations before blocking the room.",
                ]);
            }

            $block = RoomBlock::create([
                'room_id' => $room->id,
                'type' => $type,
                'start_date' => $period->checkIn,
                'end_date' => $period->checkOut,
                'reason' => $reason,
                'maintenance_request_id' => $maintenanceRequestId,
                'created_by' => $actor?->id,
            ]);

            $this->audit->record('room.blocked', $room, newValues: [
                'block_id' => $block->id,
                'type' => $type->value,
                'start_date' => $period->checkIn->toDateString(),
                'end_date' => $period->checkOut->toDateString(),
                'reason' => $reason,
            ], actor: $actor);

            return $block;
        });
    }

    public function release(RoomBlock $block, ?User $actor = null): void
    {
        if ($block->released_at !== null) {
            return;
        }

        $block->forceFill(['released_at' => now(), 'released_by' => $actor?->id])->save();

        $this->audit->record('room.block_released', $block->room, newValues: ['block_id' => $block->id], actor: $actor);
    }
}
