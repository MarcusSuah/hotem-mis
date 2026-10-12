<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Services\Operations\HousekeepingService;
use App\Services\Rooms\RoomStateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The live room board: every room by floor with who is in it, who is
 * arriving, its cleaning state and the attendant responsible.
 */
class RoomBoardController extends Controller
{
    public function index(Request $request, RoomStateResolver $states): Response
    {
        $user = $request->user();
        abort_unless($user->canAny([Permission::HousekeepingManage->value, Permission::HousekeepingPerform->value, Permission::CheckinManage->value]), 403);

        $today = CarbonImmutable::today();
        $rooms = Room::query()->with('roomType:id,name')->orderBy('floor')->orderBy('number')->get();
        $resolved = $states->resolve($rooms, $today);

        $allocations = ReservationRoom::query()
            ->whereNull('released_at')
            ->whereIn('room_id', $rooms->modelKeys())
            ->whereHas('reservation', fn ($query) => $query->whereIn('status', [ReservationStatus::CheckedIn->value, ReservationStatus::Confirmed->value, ReservationStatus::Pending->value]))
            ->with('reservation:id,reference,status,contact_name,check_in_date,check_out_date,nights,arrival_time,checked_in_at')
            ->get()
            ->groupBy('room_id');

        $departedToday = ReservationRoom::query()
            ->whereIn('room_id', $rooms->modelKeys())
            ->whereHas('reservation', fn ($query) => $query->where('status', ReservationStatus::CheckedOut->value)->whereDate('checked_out_at', $today))
            ->pluck('room_id')
            ->flip();

        $tasks = HousekeepingTask::query()
            ->whereIn('status', HousekeepingTaskStatus::openValues())
            ->with('assignee:id,name')
            ->orderByDesc('id')
            ->get()
            ->unique('room_id')
            ->keyBy('room_id');

        $blocks = RoomBlock::query()
            ->whereNull('released_at')
            ->where('start_date', '<=', $today->toDateString())
            ->where('end_date', '>', $today->toDateString())
            ->get()
            ->keyBy('room_id');

        $cards = $rooms->map(function (Room $room) use ($resolved, $allocations, $departedToday, $tasks, $blocks, $today): array {
            /** @var Collection<int, ReservationRoom> $roomAllocations */
            $roomAllocations = $allocations->get($room->id, collect());
            $inHouse = $roomAllocations->first(fn (ReservationRoom $allocation) => $allocation->reservation->status === ReservationStatus::CheckedIn);
            $arriving = $roomAllocations->first(fn (ReservationRoom $allocation) => $allocation->reservation->status !== ReservationStatus::CheckedIn
                && $allocation->reservation->check_in_date->isSameDay($today));
            $task = $tasks->get($room->id);
            $block = $blocks->get($room->id);
            $state = $resolved[$room->id]['state'];

            return [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'room_type' => $room->roomType->name,
                'state' => $state,
                'occupied' => $inHouse !== null,
                'out_of_order' => in_array($state, [RoomStateResolver::OUT_OF_SERVICE, RoomStateResolver::MAINTENANCE], true),
                'housekeeping' => $room->housekeeping_status->value,
                'housekeeping_label' => $room->housekeeping_status->label(),
                'guest' => $inHouse ? [
                    'name' => $this->shortName($inHouse->reservation->contact_name),
                    'reference' => $inHouse->reservation->reference,
                    'night' => min($inHouse->reservation->nights, (int) $inHouse->reservation->check_in_date->diffInDays($today) + 1),
                    'nights' => $inHouse->reservation->nights,
                    'check_out' => $inHouse->reservation->check_out_date->toDateString(),
                    'due_out' => $inHouse->reservation->check_out_date->lte($today),
                ] : null,
                'arrival' => $arriving ? [
                    'name' => $this->shortName($arriving->reservation->contact_name),
                    'reference' => $arriving->reservation->reference,
                    'time' => $arriving->reservation->arrival_time ? substr($arriving->reservation->arrival_time, 0, 5) : null,
                ] : null,
                'vacated_today' => $inHouse === null && $departedToday->has($room->id),
                'block' => $block ? ['until' => $block->end_date->toDateString(), 'reason' => $block->reason] : null,
                'task' => $task ? [
                    'id' => $task->id,
                    'type' => $task->type->label(),
                    'status' => $task->status->label(),
                    'assignee_id' => $task->assigned_to,
                    'assignee' => $task->assignee?->name,
                ] : null,
            ];
        });

        $vacant = $cards->filter(fn (array $card) => ! $card['occupied'] && ! $card['out_of_order']);

        return Inertia::render('admin/rooms/board', [
            'floors' => $cards->groupBy('floor')->map(fn (Collection $floorRooms, int $floor) => [
                'floor' => $floor,
                'rooms' => $floorRooms->values(),
                'occupied' => $floorRooms->where('occupied', true)->count(),
                'clean' => $floorRooms->filter(fn (array $card) => ! $card['occupied'] && ! $card['out_of_order'] && $card['housekeeping'] !== HousekeepingStatus::Dirty->value)->count(),
                'dirty' => $floorRooms->filter(fn (array $card) => ! $card['occupied'] && $card['housekeeping'] === HousekeepingStatus::Dirty->value)->count(),
                'out_of_order' => $floorRooms->where('out_of_order', true)->count(),
            ])->values(),
            'counts' => [
                'all' => $cards->count(),
                'floors' => $cards->pluck('floor')->unique()->count(),
                'occupied' => $cards->where('occupied', true)->count(),
                'occupied_clean' => $cards->filter(fn (array $card) => $card['occupied'] && $card['housekeeping'] !== HousekeepingStatus::Dirty->value)->count(),
                'clean' => $vacant->filter(fn (array $card) => $card['housekeeping'] !== HousekeepingStatus::Dirty->value)->count(),
                'dirty' => $vacant->filter(fn (array $card) => $card['housekeeping'] === HousekeepingStatus::Dirty->value)->count(),
                'out_of_order' => $cards->where('out_of_order', true)->count(),
            ],
            'attendants' => User::query()->active()->permission(Permission::HousekeepingPerform->value)->orderBy('name')->get(['id', 'name']),
            'updatedAt' => now()->toIso8601String(),
            'can' => ['manage' => $user->can(Permission::HousekeepingManage->value)],
        ]);
    }

    public function update(Request $request, Room $room, HousekeepingService $housekeeping): RedirectResponse
    {
        Gate::authorize(Permission::HousekeepingManage->value);

        $status = HousekeepingStatus::from($request->validate([
            'status' => ['required', Rule::in([HousekeepingStatus::Clean->value, HousekeepingStatus::Dirty->value])],
        ])['status']);

        $housekeeping->markRoom($room, $status, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room :number marked :status.', ['number' => $room->number, 'status' => strtolower($status->label())])]);

        return back();
    }

    /**
     * "Amara Okafor" becomes "A. Okafor" so a board on a shared screen
     * reveals as little as possible.
     */
    private function shortName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) < 2) {
            return $name;
        }

        return mb_substr((string) $parts[0], 0, 1).'. '.end($parts);
    }
}
