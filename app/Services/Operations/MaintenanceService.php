<?php

namespace App\Services\Operations;

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\InventoryMovementType;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\RoomBlockType;
use App\Enums\TaskPriority;
use App\Models\InventoryItem;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenancePart;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\User;
use App\Notifications\WorkAssigned;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingException;
use App\Services\Booking\StayPeriod;
use App\Services\Currency\CurrencyConverter;
use App\Services\Inventory\InventoryService;
use App\Services\Rooms\RoomBlockService;
use App\Support\Money\Money;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RoundingMode;

/**
 * Maintenance requests. A request can take its room out of sale for a date
 * range (via a room block, which refuses dates already sold); resolving the
 * request releases the block and hands the room to housekeeping.
 */
class MaintenanceService
{
    public function __construct(
        private RoomBlockService $blocks,
        private InventoryService $inventory,
        private HousekeepingService $housekeeping,
        private CurrencyConverter $converter,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{title: string, description?: string|null, category: MaintenanceCategory, priority: TaskPriority, room_id?: int|null, location?: string|null, estimated_cost?: Money|null, maintenance_schedule_id?: int|null, housekeeping_task_id?: int|null}  $data
     */
    public function create(array $data, ?User $reporter = null, ?User $assignee = null, ?StayPeriod $blockRoom = null): MaintenanceRequest
    {
        return DB::transaction(function () use ($data, $reporter, $assignee, $blockRoom): MaintenanceRequest {
            $request = MaintenanceRequest::create([
                'reference' => $this->reference(),
                'room_id' => $data['room_id'] ?? null,
                'location' => $data['location'] ?? null,
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => $assignee ? MaintenanceStatus::Assigned : MaintenanceStatus::Open,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'reported_by' => $reporter?->id,
                'assigned_to' => $assignee?->id,
                'currency' => $this->converter->baseCurrency(),
                'estimated_cost_minor' => ($data['estimated_cost'] ?? null)?->minor,
                'maintenance_schedule_id' => $data['maintenance_schedule_id'] ?? null,
                'housekeeping_task_id' => $data['housekeeping_task_id'] ?? null,
            ]);

            if ($blockRoom !== null) {
                $room = $request->room ?? throw new BookingException('Choose the room to take out of service.', 'room_id');
                $block = $this->blocks->create($room, RoomBlockType::Maintenance, $blockRoom, "{$request->reference}: {$request->title}", $reporter, $request->id);
                $request->forceFill(['room_block_id' => $block->id])->save();
            }

            $this->comment($request, 'Request created'.($blockRoom ? " — room blocked {$blockRoom->checkIn->format('d M')} to {$blockRoom->checkOut->format('d M')}" : ''), $reporter, 'created');
            $this->audit->record('maintenance.created', $request, newValues: ['reference' => $request->reference, 'title' => $request->title, 'room_id' => $request->room_id], actor: $reporter);

            if ($assignee !== null) {
                $this->notifyAssignee($request, $assignee);
            }

            return $request;
        });
    }

    public function assign(MaintenanceRequest $request, ?User $assignee, User $actor): MaintenanceRequest
    {
        $this->ensureActive($request);

        $request->forceFill([
            'assigned_to' => $assignee?->id,
            'status' => $request->status === MaintenanceStatus::Open && $assignee ? MaintenanceStatus::Assigned : $request->status,
        ])->save();

        $this->comment($request, $assignee ? "Assigned to {$assignee->name}" : 'Unassigned', $actor, 'assigned');

        if ($assignee !== null) {
            $this->notifyAssignee($request, $assignee);
        }

        return $request;
    }

    public function changeStatus(MaintenanceRequest $request, MaintenanceStatus $status, User $actor, ?string $note = null, ?Money $actualCost = null): MaintenanceRequest
    {
        if (in_array($request->status, [MaintenanceStatus::Closed, MaintenanceStatus::Cancelled], true)) {
            throw new BookingException("This request is already {$request->status->label()}.", 'status');
        }

        return DB::transaction(function () use ($request, $status, $actor, $note, $actualCost): MaintenanceRequest {
            $previous = $request->status;

            $request->forceFill(array_filter([
                'status' => $status,
                'started_at' => $status === MaintenanceStatus::InProgress ? ($request->started_at ?? now()) : null,
                'completed_at' => in_array($status, [MaintenanceStatus::Resolved, MaintenanceStatus::Closed], true) ? ($request->completed_at ?? now()) : null,
                'actual_cost_minor' => $actualCost->minor ?? ($status === MaintenanceStatus::Resolved ? $this->partsCost($request) : null),
            ], fn ($value) => $value !== null))->save();

            if (in_array($status, [MaintenanceStatus::Resolved, MaintenanceStatus::Closed, MaintenanceStatus::Cancelled], true)) {
                $this->releaseRoom($request, $actor, handToHousekeeping: $status !== MaintenanceStatus::Cancelled);
            }

            $this->comment($request, trim("Status: {$previous->label()} → {$status->label()}. ".($note ?? '')), $actor, 'status');
            $this->audit->record('maintenance.status_changed', $request, ['status' => $previous->value], ['status' => $status->value, 'note' => $note], $actor);

            return $request;
        });
    }

    public function comment(MaintenanceRequest $request, string $body, ?User $author, ?string $event = null): void
    {
        $request->comments()->create(['user_id' => $author?->id, 'body' => $body, 'event' => $event]);
    }

    public function attach(MaintenanceRequest $request, UploadedFile $file, User $actor): MaintenanceAttachment
    {
        $disk = (string) config('hms.uploads.private_disk');
        $path = $file->store("maintenance/{$request->id}", ['disk' => $disk]);

        if ($path === false) {
            throw new \RuntimeException('The attachment could not be stored.');
        }

        return $request->attachments()->create([
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);
    }

    /**
     * Record a part used. Parts taken from stock are deducted from inventory.
     */
    public function addPart(MaintenanceRequest $request, string $description, string $quantity, ?InventoryItem $item, ?Money $unitCost, User $actor): MaintenancePart
    {
        return DB::transaction(function () use ($request, $description, $quantity, $item, $unitCost, $actor): MaintenancePart {
            $costMinor = $unitCost->minor ?? $item->average_cost_minor ?? 0;

            $part = $request->parts()->create([
                'inventory_item_id' => $item?->id,
                'description' => $item->name ?? $description,
                'quantity' => $quantity,
                'unit_cost_minor' => $costMinor,
                'added_by' => $actor->id,
            ]);

            if ($item !== null) {
                $this->inventory->record($item, InventoryMovementType::Consumption, $quantity, $actor, $costMinor, $request->reference, $request, "Used for {$request->title}");
            }

            $this->comment($request, "Part used: {$quantity} × {$part->description}", $actor, 'part');

            return $part;
        });
    }

    /**
     * Create requests for preventive schedules that are due, advancing each schedule.
     */
    public function generateDueSchedules(CarbonImmutable $today): int
    {
        $created = 0;

        $due = MaintenanceSchedule::query()
            ->where('is_active', true)
            ->whereDate('next_due_on', '<=', $today)
            ->with('assignee')
            ->get();

        foreach ($due as $schedule) {
            DB::transaction(function () use ($schedule, $today, &$created): void {
                $this->create([
                    'title' => $schedule->title,
                    'description' => $schedule->description,
                    'category' => MaintenanceCategory::Preventive,
                    'priority' => $schedule->priority,
                    'room_id' => $schedule->room_id,
                    'location' => $schedule->location,
                    'maintenance_schedule_id' => $schedule->id,
                ], assignee: $schedule->assignee);

                $next = $schedule->next_due_on;

                while ($next->lessThanOrEqualTo($today)) {
                    $next = $next->addDays($schedule->frequency_days);
                }

                $schedule->forceFill(['next_due_on' => $next, 'last_generated_at' => now()])->save();
                $created++;
            });
        }

        return $created;
    }

    public function attachmentPath(MaintenanceAttachment $attachment): string
    {
        return Storage::disk((string) config('hms.uploads.private_disk'))->path($attachment->path);
    }

    private function partsCost(MaintenanceRequest $request): int
    {
        return (int) $request->parts()->get()->sum(fn (MaintenancePart $part) => (int) (new Number(Money::numeric((string) $part->quantity)))
            ->mul($part->unit_cost_minor)
            ->round(0, RoundingMode::HalfAwayFromZero)
            ->value);
    }

    private function releaseRoom(MaintenanceRequest $request, User $actor, bool $handToHousekeeping): void
    {
        if ($request->roomBlock !== null && $request->roomBlock->released_at === null) {
            $this->blocks->release($request->roomBlock, $actor);
        }

        if ($handToHousekeeping && $request->room_id !== null && $request->room_block_id !== null) {
            /** @var Room $room */
            $room = $request->room;
            $room->forceFill(['housekeeping_status' => HousekeepingStatus::Dirty])->save();
            $this->housekeeping->create($room, HousekeepingTaskType::DeepClean, priority: TaskPriority::High, notes: "After maintenance {$request->reference}", actor: $actor);
        }
    }

    private function ensureActive(MaintenanceRequest $request): void
    {
        if (! $request->status->isActive()) {
            throw new BookingException("This request is already {$request->status->label()}.", 'status');
        }
    }

    private function notifyAssignee(MaintenanceRequest $request, User $assignee): void
    {
        $assignee->notify(new WorkAssigned(
            'Maintenance assigned',
            "{$request->reference}: {$request->title}".($request->room ? " (room {$request->room->number})" : ''),
            route('admin.maintenance.show', $request, false),
            in_array($request->priority, [TaskPriority::High, TaskPriority::Urgent], true) ? 'warning' : 'info',
        ));
    }

    private function reference(): string
    {
        do {
            $reference = 'MNT-'.Str::upper(Str::random(6));
        } while (MaintenanceRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
