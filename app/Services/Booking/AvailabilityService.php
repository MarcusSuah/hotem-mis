<?php

namespace App\Services\Booking;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Availability is always evaluated for a specific stay: a room is bookable
 * when it is in service, has no held night in the stay and no active block
 * overlapping it. Bookings on other dates never affect the result.
 */
class AvailabilityService
{
    /**
     * @return Builder<Room>
     */
    public function availableRooms(StayPeriod $stay, ?int $roomTypeId = null, ?Reservation $ignore = null): Builder
    {
        return Room::query()
            ->inService()
            ->when($roomTypeId, fn (Builder $query, int $typeId) => $query->where('room_type_id', $typeId))
            ->whereDoesntHave('nights', fn (Builder $nights) => $nights
                ->where('night_date', '>=', $stay->checkIn->toDateString())
                ->where('night_date', '<', $stay->checkOut->toDateString())
                ->when($ignore, fn (Builder $query, Reservation $reservation) => $query->where('reservation_id', '!=', $reservation->id)))
            ->whereDoesntHave('blocks', fn (Builder $blocks) => $blocks
                ->whereNull('released_at')
                ->where('start_date', '<', $stay->checkOut->toDateString())
                ->where('end_date', '>', $stay->checkIn->toDateString()));
    }

    public function isRoomAvailable(Room $room, StayPeriod $stay, ?Reservation $ignore = null): bool
    {
        return $this->availableRooms($stay, ignore: $ignore)->whereKey($room->id)->exists();
    }

    /**
     * Active room types that fit the party, each with its count of free rooms.
     *
     * @return Collection<int, array{room_type: RoomType, available: int}>
     */
    public function roomTypesFor(StayPeriod $stay, int $adults, int $children): Collection
    {
        $counts = $this->availableRooms($stay)
            ->selectRaw('room_type_id, count(*) as available')
            ->groupBy('room_type_id')
            ->pluck('available', 'room_type_id');

        return RoomType::query()
            ->active()
            ->ordered()
            ->where('max_adults', '>=', $adults)
            ->where('max_children', '>=', $children)
            ->where('max_occupancy', '>=', $adults + $children)
            ->get()
            ->map(fn (RoomType $type): array => [
                'room_type' => $type,
                'available' => (int) ($counts[$type->id] ?? 0),
            ])
            ->values();
    }
}
