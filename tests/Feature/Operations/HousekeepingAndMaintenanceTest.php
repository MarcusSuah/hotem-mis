<?php

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\InventoryMovementType;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\SystemRole;
use App\Enums\TaskPriority;
use App\Events\GuestCheckedOut;
use App\Models\HousekeepingTask;
use App\Models\InventoryItem;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingException;
use App\Services\Booking\StayPeriod;
use App\Services\Inventory\InventoryService;
use App\Services\Operations\HousekeepingService;
use App\Services\Operations\MaintenanceService;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->travelTo('2026-11-10 11:00:00'));

it('creates an urgent departure clean when the next guest arrives today', function () {
    $room = Room::factory()->create();
    $departed = Reservation::factory()->checkedOut()->between('2026-11-07', '2026-11-10')->inRoom($room)->create();
    Reservation::factory()->confirmed()->between('2026-11-10', '2026-11-12')->inRoom($room)->create();

    GuestCheckedOut::dispatch($departed, $room);

    $task = HousekeepingTask::query()->sole();
    expect($task->type)->toBe(HousekeepingTaskType::DepartureClean)
        ->and($task->priority)->toBe(TaskPriority::Urgent)
        ->and($task->reservation_id)->toBe($departed->id);
});

it('moves the room through cleaning, clean and inspected as the task progresses', function () {
    $room = Room::factory()->dirty()->create();
    $housekeeper = User::factory()->withRole(SystemRole::HousekeepingStaff)->create();
    $supervisor = User::factory()->withRole(SystemRole::HotelManager)->create();
    $service = app(HousekeepingService::class);
    $task = $service->create($room, HousekeepingTaskType::DepartureClean, assignee: $housekeeper);

    $service->start($task, $housekeeper);
    expect($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Cleaning);

    $done = array_map(fn ($line) => [...$line, 'done' => true], $task->checklist);
    $service->complete($task->fresh(), $done, 'All good', $housekeeper);
    expect($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Clean);

    $service->inspect($task->fresh(), true, null, $supervisor);
    expect($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Inspected)
        ->and($task->fresh()->status)->toBe(HousekeepingTaskStatus::Inspected);
    expect($housekeeper->notifications()->count())->toBe(1);
});

it('refuses to complete a task with unchecked checklist items', function () {
    $room = Room::factory()->dirty()->create();
    $housekeeper = User::factory()->withRole(SystemRole::HousekeepingStaff)->create();
    $task = app(HousekeepingService::class)->create($room, HousekeepingTaskType::Stayover);

    app(HousekeepingService::class)->complete($task, $task->checklist, null, $housekeeper);
})->throws(BookingException::class, 'Finish the checklist first: Make bed, Replace used towels, Empty bins, Tidy bathroom, Replenish amenities.');

it('reopens the task and marks the room dirty when inspection fails', function () {
    $room = Room::factory()->create();
    $task = HousekeepingTask::factory()->for($room)->completed()->create();
    $supervisor = User::factory()->withRole(SystemRole::HotelManager)->create();

    app(HousekeepingService::class)->inspect($task, false, 'Hair in the shower', $supervisor);

    expect($task->fresh()->status)->toBe(HousekeepingTaskStatus::Reopened)
        ->and(collect($task->fresh()->checklist)->every(fn ($line) => $line['done'] === false))->toBeTrue()
        ->and($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Dirty);
});

it('generates one stayover task per occupied room per day', function () {
    $room = Room::factory()->create();
    Reservation::factory()->checkedIn()->inRoom($room)->create();
    $service = app(HousekeepingService::class);

    expect($service->generateStayoverTasks(now()->toImmutable()->startOfDay()))->toBe(1)
        ->and($service->generateStayoverTasks(now()->toImmutable()->startOfDay()))->toBe(0);
});

it('takes a room out of sale for the repair dates and refuses dates already sold', function () {
    $room = Room::factory()->create();
    $engineer = User::factory()->withRole(SystemRole::MaintenanceStaff)->create();
    $service = app(MaintenanceService::class);

    $request = $service->create(['title' => 'Replace AC compressor', 'category' => MaintenanceCategory::Hvac, 'priority' => TaskPriority::High, 'room_id' => $room->id], $engineer, blockRoom: new StayPeriod('2026-11-12', '2026-11-14'));

    expect(app(AvailabilityService::class)->isRoomAvailable($room, new StayPeriod('2026-11-12', '2026-11-13')))->toBeFalse()
        ->and($request->roomBlock->maintenance_request_id)->toBe($request->id);

    Reservation::factory()->between('2026-11-20', '2026-11-22')->inRoom($room)->create();
    expect(fn () => $service->create(['title' => 'Paint', 'category' => MaintenanceCategory::Other, 'priority' => TaskPriority::Low, 'room_id' => $room->id], $engineer, blockRoom: new StayPeriod('2026-11-21', '2026-11-23')))
        ->toThrow(ValidationException::class);
});

it('releases the room and hands it to housekeeping when the repair is resolved', function () {
    $room = Room::factory()->create();
    $engineer = User::factory()->withRole(SystemRole::MaintenanceStaff)->create();
    $service = app(MaintenanceService::class);
    $request = $service->create(['title' => 'Fix leak', 'category' => MaintenanceCategory::Plumbing, 'priority' => TaskPriority::High, 'room_id' => $room->id], $engineer, blockRoom: new StayPeriod('2026-11-10', '2026-11-12'));

    $service->changeStatus($request, MaintenanceStatus::Resolved, $engineer, 'Washer replaced');

    expect($request->roomBlock->fresh()->released_at)->not->toBeNull()
        ->and($room->fresh()->housekeeping_status)->toBe(HousekeepingStatus::Dirty)
        ->and(HousekeepingTask::query()->where('room_id', $room->id)->value('type'))->toBe(HousekeepingTaskType::DeepClean)
        ->and(app(AvailabilityService::class)->isRoomAvailable($room, new StayPeriod('2026-11-10', '2026-11-12')))->toBeTrue();
});

it('deducts parts from stock and totals their cost on resolution', function () {
    $item = InventoryItem::factory()->create(['name' => 'Tap washer', 'reorder_level' => '0']);
    $engineer = User::factory()->withRole(SystemRole::MaintenanceStaff)->create();
    app(InventoryService::class)->record($item, InventoryMovementType::Receipt, '10', $engineer, 250);
    $service = app(MaintenanceService::class);
    $request = MaintenanceRequest::factory()->create();

    $service->addPart($request, '', '3', $item->fresh(), null, $engineer);
    $service->changeStatus($request, MaintenanceStatus::Resolved, $engineer);

    expect($item->fresh()->quantity)->toBe('7.000')
        ->and($request->fresh()->actual_cost_minor)->toBe(750);
});

it('creates preventive requests when schedules fall due and advances them', function () {
    $schedule = MaintenanceSchedule::factory()->create(['next_due_on' => '2026-11-01', 'frequency_days' => 7]);

    $created = app(MaintenanceService::class)->generateDueSchedules(now()->toImmutable()->startOfDay());

    expect($created)->toBe(1)
        ->and(MaintenanceRequest::query()->sole()->category)->toBe(MaintenanceCategory::Preventive)
        ->and($schedule->fresh()->next_due_on->toDateString())->toBe('2026-11-15');
});
