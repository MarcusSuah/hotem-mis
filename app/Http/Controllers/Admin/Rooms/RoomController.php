<?php

namespace App\Http\Controllers\Admin\Rooms;

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Enums\Permission;
use App\Enums\RoomBlockType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rooms\SaveRoomRequest;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\RoomImage;
use App\Models\RoomType;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\StayPeriod;
use App\Services\Rooms\RoomService;
use App\Services\Rooms\RoomStateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request, AvailabilityService $availability, RoomStateResolver $states): Response
    {
        Gate::authorize(Permission::RoomsView->value);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:50'],
            'room_type' => ['nullable', 'integer'],
            'floor' => ['nullable', 'integer'],
            'operational_status' => ['nullable', Rule::enum(OperationalStatus::class)],
            'housekeeping_status' => ['nullable', Rule::enum(HousekeepingStatus::class)],
            'available_from' => ['nullable', 'date'],
            'available_to' => ['nullable', 'date', 'after:available_from', 'required_with:available_from'],
            'view' => ['nullable', 'in:table,grid'],
        ]);

        $query = filled($filters['available_from'] ?? null)
            ? $availability->availableRooms(new StayPeriod($filters['available_from'], $filters['available_to']))
            : Room::query();

        $rooms = $query
            ->with(['roomType:id,name,max_occupancy,base_rate_minor,currency', 'images'])
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->whereLike('number', "%{$search}%"))
            ->when($filters['room_type'] ?? null, fn ($q, int $type) => $q->where('room_type_id', $type))
            ->when(isset($filters['floor']), fn ($q) => $q->where('floor', $filters['floor']))
            ->when($filters['operational_status'] ?? null, fn ($q, string $status) => $q->where('operational_status', $status))
            ->when($filters['housekeeping_status'] ?? null, fn ($q, string $status) => $q->where('housekeeping_status', $status))
            ->orderBy('floor')
            ->orderBy('number')
            ->paginate(24)
            ->withQueryString();

        $today = CarbonImmutable::today();
        $roomStates = $states->resolve($rooms->getCollection(), $today);
        $activeBlocks = RoomBlock::query()
            ->whereIn('room_id', $rooms->getCollection()->modelKeys())
            ->whereNull('released_at')
            ->where('end_date', '>', $today->toDateString())
            ->orderBy('start_date')
            ->get()
            ->groupBy('room_id');

        $user = $request->user();

        return Inertia::render('admin/rooms/index', [
            'rooms' => $rooms->through(fn (Room $room): array => [
                'id' => $room->id,
                'number' => $room->number,
                'floor' => $room->floor,
                'building' => $room->building,
                'description' => $room->description,
                'room_type' => ['id' => $room->roomType->id, 'name' => $room->roomType->name, 'max_occupancy' => $room->roomType->max_occupancy],
                'base_rate' => $room->roomType->baseRate(),
                'operational_status' => $room->operational_status->value,
                'housekeeping_status' => $room->housekeeping_status->value,
                'is_accessible' => $room->is_accessible,
                'accessibility_notes' => $room->accessibility_notes,
                'notes' => $room->notes,
                'state' => $roomStates[$room->id],
                'images' => $room->images->map(fn (RoomImage $image): array => ['id' => $image->id, 'url' => $image->url, 'alt_text' => $image->alt_text]),
                'blocks' => ($activeBlocks->get($room->id) ?? collect())->map(fn (RoomBlock $block): array => [
                    'id' => $block->id,
                    'type' => $block->type->value,
                    'type_label' => $block->type->label(),
                    'start_date' => $block->start_date->toDateString(),
                    'end_date' => $block->end_date->toDateString(),
                    'reason' => $block->reason,
                ])->values(),
            ]),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'room_type' => isset($filters['room_type']) ? (string) $filters['room_type'] : '',
                'floor' => isset($filters['floor']) ? (string) $filters['floor'] : '',
                'operational_status' => $filters['operational_status'] ?? '',
                'housekeeping_status' => $filters['housekeeping_status'] ?? '',
                'available_from' => $filters['available_from'] ?? '',
                'available_to' => $filters['available_to'] ?? '',
                'view' => $filters['view'] ?? 'grid',
            ],
            'roomTypes' => RoomType::query()->ordered()->get(['id', 'name']),
            'floors' => Room::query()->distinct()->orderBy('floor')->pluck('floor'),
            'options' => [
                'operational' => collect(OperationalStatus::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
                'housekeeping' => collect(HousekeepingStatus::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
                'blockTypes' => collect(RoomBlockType::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
            ],
            'can' => [
                'create' => $user->can(Permission::RoomsCreate->value),
                'update' => $user->can(Permission::RoomsUpdate->value),
                'delete' => $user->can(Permission::RoomsDelete->value),
                'block' => $user->can(Permission::RoomBlocksManage->value),
            ],
        ]);
    }

    public function store(SaveRoomRequest $request, RoomService $service): RedirectResponse
    {
        $room = $service->save(null, $request->roomAttributes(), $request->imageFiles(), []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room :number created.', ['number' => $room->number])]);

        return back();
    }

    public function update(SaveRoomRequest $request, Room $room, RoomService $service): RedirectResponse
    {
        $service->save(
            $room,
            $request->roomAttributes(),
            $request->imageFiles(),
            array_values(array_map('intval', (array) $request->validated('remove_image_ids', []))),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room :number updated.', ['number' => $room->number])]);

        return back();
    }

    public function destroy(Room $room, RoomService $service): RedirectResponse
    {
        Gate::authorize(Permission::RoomsDelete->value);

        $service->delete($room);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room :number deleted.', ['number' => $room->number])]);

        return back();
    }
}
