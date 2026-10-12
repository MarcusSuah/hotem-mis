<?php

namespace App\Models;

use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\TaskPriority;
use Carbon\CarbonImmutable;
use Database\Factories\HousekeepingTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cleaning or inspection work on a room.
 *
 * @property int $id
 * @property int $room_id
 * @property int|null $reservation_id
 * @property HousekeepingTaskType $type
 * @property TaskPriority $priority
 * @property HousekeepingTaskStatus $status
 * @property CarbonImmutable $scheduled_for
 * @property int|null $assigned_to
 * @property array<int|string, mixed>|null $checklist
 * @property string|null $notes
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $inspected_at
 * @property int|null $inspected_by
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['room_id', 'reservation_id', 'type', 'priority', 'status', 'scheduled_for', 'assigned_to', 'checklist', 'notes', 'created_by'])]
class HousekeepingTask extends Model
{
    /** @use HasFactory<HousekeepingTaskFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by')->withTrashed();
    }

    /**
     * @return HasMany<HousekeepingInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(HousekeepingInspection::class)->latest('id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => HousekeepingTaskType::class,
            'priority' => TaskPriority::class,
            'status' => HousekeepingTaskStatus::class,
            'scheduled_for' => 'immutable_date',
            'checklist' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'inspected_at' => 'immutable_datetime',
        ];
    }
}
