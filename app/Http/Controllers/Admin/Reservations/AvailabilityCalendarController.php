<?php

namespace App\Http\Controllers\Admin\Reservations;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ReservationNight;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rooms × dates grid. Reservations are shown as contiguous segments built
 * from held nights, so cancelled and no-show bookings never appear.
 */
class AvailabilityCalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize(Permission::BookingsView->value);

        $filters = $request->validate([
            'start' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', Rule::in([7, 14, 21, 31])],
            'room_type' => ['nullable', 'integer'],
        ]);

        $start = CarbonImmutable::parse($filters['start'] ?? CarbonImmutable::today())->startOfDay();
        $days = (int) ($filters['days'] ?? 14);
        $end = $start->addDays($days);

        $rooms = Room::query()
            ->with('roomType:id,name')
            ->when($filters['room_type'] ?? null, fn ($query, int $type) => $query->where('room_type_id', $type))
            ->orderBy('floor')
            ->orderBy('number')
            ->get(['id', 'number', 'floor', 'room_type_id', 'operational_status', 'housekeeping_status']);

        $roomIds = $rooms->modelKeys();

        $segments = ReservationNight::query()
            ->whereIn('room_id', $roomIds)
            ->where('night_date', '>=', $start->toDateString())
            ->where('night_date', '<', $end->toDateString())
            ->with('reservation:id,reference,status,contact_name,adults,children,check_in_date,check_out_date')
            ->orderBy('room_id')
            ->orderBy('night_date')
            ->get()
            ->groupBy(fn (ReservationNight $night) => $night->room_id.'-'.$night->reservation_id)
            ->map(fn ($nights) => [
                'room_id' => $nights->first()->room_id,
                'reference' => $nights->first()->reservation->reference,
                'status' => $nights->first()->reservation->status->value,
                'guest' => $nights->first()->reservation->contact_name,
                'party' => $nights->first()->reservation->adults + $nights->first()->reservation->children,
                'start' => $nights->first()->night_date->toDateString(),
                'end' => $nights->last()->night_date->addDay()->toDateString(),
                'arrival' => $nights->first()->reservation->check_in_date->toDateString(),
                'departure' => $nights->first()->reservation->check_out_date->toDateString(),
            ])
            ->values();

        $blocks = RoomBlock::query()
            ->whereIn('room_id', $roomIds)
            ->whereNull('released_at')
            ->where('start_date', '<', $end->toDateString())
            ->where('end_date', '>', $start->toDateString())
            ->get()
            ->map(fn (RoomBlock $block) => [
                'room_id' => $block->room_id,
                'type' => $block->type->value,
                'label' => $block->type->label(),
                'reason' => $block->reason,
                'start' => $block->start_date->toDateString(),
                'end' => $block->end_date->toDateString(),
            ]);

        return Inertia::render('admin/reservations/calendar', [
            'start' => $start->toDateString(),
            'days' => $days,
            'dates' => collect(range(0, $days - 1))->map(fn (int $offset) => $start->addDays($offset)->toDateString()),
            'rooms' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type' => $room->roomType->name,
                'out_of_service' => $room->operational_status->value === 'out_of_service',
            ]),
            'segments' => $segments,
            'blocks' => $blocks,
            'roomTypes' => RoomType::query()->ordered()->get(['id', 'name']),
            'filters' => ['room_type' => isset($filters['room_type']) ? (string) $filters['room_type'] : ''],
            'can' => ['create' => $request->user()->can(Permission::BookingsCreate->value)],
        ]);
    }
}
