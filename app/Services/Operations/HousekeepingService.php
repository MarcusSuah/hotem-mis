<?php

namespace App\Services\Operations;

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\ReservationStatus;
use App\Enums\TaskPriority;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Notifications\WorkAssigned;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Housekeeping work drives each room's housekeeping status:
 *
 *   task started   → room "cleaning"
 *   task completed → room "clean"
 *   inspection     → room "inspected" (passed) or "dirty" and task reopened (failed)
 *
 * When the hotel requires inspection, only inspected rooms are offered for check-in.
 */
class HousekeepingService
{
    /**
     * Default checklist items per task type.
     *
     * @var array<string, list<string>>
     */
    private const CHECKLISTS = [
        'departure_clean' => ['Strip and remake bed', 'Clean and sanitise bathroom', 'Replace towels and amenities', 'Dust and vacuum', 'Restock minibar', 'Check for lost property', 'Check for damage'],
        'stayover' => ['Make bed', 'Replace used towels', 'Empty bins', 'Tidy bathroom', 'Replenish amenities'],
        'deep_clean' => ['Move furniture and vacuum', 'Clean windows and curtains', 'Descale bathroom', 'Clean air-conditioning filters', 'Wash walls and skirting'],
        'turndown' => ['Turn down bed', 'Close curtains', 'Set evening lighting', 'Replace towels if needed'],
        'inspection' => ['Bed and linen', 'Bathroom', 'Amenities stocked', 'Minibar', 'Technical check'],
    ];

    public function __construct(private AuditLogger $audit) {}

    /**
     * @return list<array{item: string, done: bool}>
     */
    public static function checklistFor(HousekeepingTaskType $type): array
    {
        return array_map(fn (string $item): array => ['item' => $item, 'done' => false], self::CHECKLISTS[$type->value]);
    }

    public function create(
        Room $room,
        HousekeepingTaskType $type,
        ?CarbonImmutable $scheduledFor = null,
        TaskPriority $priority = TaskPriority::Normal,
        ?User $assignee = null,
        ?Reservation $reservation = null,
        ?string $notes = null,
        ?User $actor = null,
    ): HousekeepingTask {
        $task = HousekeepingTask::create([
            'room_id' => $room->id,
            'reservation_id' => $reservation?->id,
            'type' => $type,
            'priority' => $priority,
            'status' => $assignee ? HousekeepingTaskStatus::Assigned : HousekeepingTaskStatus::Pending,
            'scheduled_for' => $scheduledFor ?? CarbonImmutable::today(),
            'assigned_to' => $assignee?->id,
            'checklist' => self::checklistFor($type),
            'notes' => $notes,
            'created_by' => $actor?->id,
        ]);

        if ($assignee !== null) {
            $this->notifyAssignee($task, $assignee);
        }

        return $task;
    }

    /**
     * Departure clean created automatically after check-out. Urgent when the
     * room has another arrival today.
     */
    public function createDepartureTask(Room $room, Reservation $reservation): HousekeepingTask
    {
        $existing = HousekeepingTask::query()
            ->where('room_id', $room->id)
            ->where('type', HousekeepingTaskType::DepartureClean->value)
            ->whereIn('status', HousekeepingTaskStatus::openValues())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $arrivingToday = Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereDate('check_in_date', CarbonImmutable::today())
            ->whereHas('activeAllocations', fn ($query) => $query->where('room_id', $room->id))
            ->exists();

        return $this->create(
            $room,
            HousekeepingTaskType::DepartureClean,
            priority: $arrivingToday ? TaskPriority::Urgent : TaskPriority::High,
            reservation: $reservation,
            notes: $arrivingToday ? 'Guest arriving today — prioritise.' : null,
        );
    }

    /**
     * Daily stayover service for every occupied room that has no open task today.
     */
    public function generateStayoverTasks(CarbonImmutable $date): int
    {
        $created = 0;

        $inHouse = Reservation::query()
            ->where('status', ReservationStatus::CheckedIn->value)
            ->whereDate('check_out_date', '>', $date)
            ->with(['activeAllocations' => fn ($query) => $query->where('start_date', '<=', $date->toDateString())->where('end_date', '>', $date->toDateString())])
            ->get();

        foreach ($inHouse as $reservation) {
            foreach ($reservation->activeAllocations as $allocation) {
                $exists = HousekeepingTask::query()
                    ->where('room_id', $allocation->room_id)
                    ->whereDate('scheduled_for', $date)
                    ->exists();

                if (! $exists) {
                    $this->create(Room::query()->findOrFail($allocation->room_id), HousekeepingTaskType::Stayover, $date, reservation: $reservation);
                    $created++;
                }
            }
        }

        return $created;
    }

    public function assign(HousekeepingTask $task, ?User $assignee, ?User $actor = null): HousekeepingTask
    {
        $this->ensureOpen($task);

        $task->forceFill([
            'assigned_to' => $assignee?->id,
            'status' => $task->status === HousekeepingTaskStatus::InProgress
                ? HousekeepingTaskStatus::InProgress
                : ($assignee ? HousekeepingTaskStatus::Assigned : HousekeepingTaskStatus::Pending),
        ])->save();

        if ($assignee !== null) {
            $this->notifyAssignee($task, $assignee);
        }

        $this->audit->record('housekeeping.assigned', $task, newValues: ['assigned_to' => $assignee?->name], actor: $actor);

        return $task;
    }

    public function start(HousekeepingTask $task, User $actor): HousekeepingTask
    {
        $this->ensureOpen($task);

        return DB::transaction(function () use ($task, $actor): HousekeepingTask {
            $task->forceFill([
                'status' => HousekeepingTaskStatus::InProgress,
                'started_at' => now(),
                'assigned_to' => $task->assigned_to ?? $actor->id,
            ])->save();

            $this->setRoomStatus($task, HousekeepingStatus::Cleaning);

            return $task;
        });
    }

    /**
     * @param  list<array{item: string, done: bool}>  $checklist
     */
    public function complete(HousekeepingTask $task, array $checklist, ?string $notes, User $actor): HousekeepingTask
    {
        if (! in_array($task->status, [HousekeepingTaskStatus::InProgress, HousekeepingTaskStatus::Assigned, HousekeepingTaskStatus::Pending, HousekeepingTaskStatus::Reopened], true)) {
            throw new BookingException('This task has already been completed.', 'task');
        }

        $outstanding = collect($checklist)->where('done', false)->pluck('item');

        if ($outstanding->isNotEmpty()) {
            throw new BookingException('Finish the checklist first: '.$outstanding->join(', ').'.', 'checklist');
        }

        return DB::transaction(function () use ($task, $checklist, $notes, $actor): HousekeepingTask {
            $task->forceFill([
                'status' => HousekeepingTaskStatus::Completed,
                'checklist' => $checklist,
                'notes' => $notes ?? $task->notes,
                'started_at' => $task->started_at ?? now(),
                'completed_at' => now(),
                'assigned_to' => $task->assigned_to ?? $actor->id,
            ])->save();

            $this->setRoomStatus($task, HousekeepingStatus::Clean);

            return $task;
        });
    }

    public function inspect(HousekeepingTask $task, bool $passed, ?string $notes, User $inspector): HousekeepingTask
    {
        if ($task->status !== HousekeepingTaskStatus::Completed) {
            throw new BookingException('Only completed tasks can be inspected.', 'task');
        }

        return DB::transaction(function () use ($task, $passed, $notes, $inspector): HousekeepingTask {
            $task->inspections()->create([
                'inspector_id' => $inspector->id,
                'passed' => $passed,
                'notes' => $notes,
            ]);

            if ($passed) {
                $task->forceFill(['status' => HousekeepingTaskStatus::Inspected, 'inspected_at' => now(), 'inspected_by' => $inspector->id])->save();
                $this->setRoomStatus($task, HousekeepingStatus::Inspected);
            } else {
                $task->forceFill([
                    'status' => HousekeepingTaskStatus::Reopened,
                    'completed_at' => null,
                    'checklist' => collect($task->checklist ?? [])->map(fn (array $line) => [...$line, 'done' => false])->all(),
                ])->save();
                $this->setRoomStatus($task, HousekeepingStatus::Dirty);

                if ($task->assigned_to !== null) {
                    $task->assignee?->notify(new WorkAssigned(
                        'Room needs re-cleaning',
                        "Room {$task->room->number} failed inspection: ".($notes ?? 'see notes'),
                        route('admin.housekeeping.index', [], false),
                        'warning',
                    ));
                }
            }

            $this->audit->record('housekeeping.inspected', $task, newValues: ['passed' => $passed, 'notes' => $notes], actor: $inspector);

            return $task;
        });
    }

    /**
     * A supervisor's manual correction from the room board. Only "clean" and
     * "dirty" can be set by hand; "inspected" still comes from an inspection,
     * so the rule that rooms are inspected before sale cannot be bypassed.
     */
    public function markRoom(Room $room, HousekeepingStatus $status, User $actor): Room
    {
        if (! in_array($status, [HousekeepingStatus::Clean, HousekeepingStatus::Dirty], true)) {
            throw new BookingException('Rooms can only be marked clean or dirty by hand; inspection sets them ready.', 'status');
        }

        $previous = $room->housekeeping_status;

        if ($previous === $status) {
            return $room;
        }

        $room->forceFill(['housekeeping_status' => $status])->save();

        $this->audit->record('room.housekeeping_status_changed', $room, ['status' => $previous->value], ['status' => $status->value, 'source' => 'room_board'], actor: $actor);

        return $room;
    }

    private function setRoomStatus(HousekeepingTask $task, HousekeepingStatus $status): void
    {
        $room = $task->room;
        $previous = $room->housekeeping_status;

        if ($previous === $status) {
            return;
        }

        $room->forceFill(['housekeeping_status' => $status])->save();

        $this->audit->record('room.housekeeping_status_changed', $room, ['status' => $previous->value], ['status' => $status->value, 'task_id' => $task->id]);
    }

    private function ensureOpen(HousekeepingTask $task): void
    {
        if (! $task->status->isOpen()) {
            throw new BookingException("This task is already {$task->status->label()}.", 'task');
        }
    }

    private function notifyAssignee(HousekeepingTask $task, User $assignee): void
    {
        $task->loadMissing('room');

        $assignee->notify(new WorkAssigned(
            'Housekeeping task assigned',
            "{$task->type->label()} — room {$task->room->number}".($task->priority === TaskPriority::Urgent ? ' (urgent)' : ''),
            route('admin.housekeeping.index', [], false),
            $task->priority === TaskPriority::Urgent ? 'warning' : 'info',
        ));
    }
}
