<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\TaskPriority;
use Carbon\CarbonImmutable;
use Database\Factories\MaintenanceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Repair work, optionally taking its room out of sale through a room block.
 *
 * @property int $id
 * @property string $reference
 * @property int|null $room_id
 * @property string|null $location
 * @property MaintenanceCategory $category
 * @property TaskPriority $priority
 * @property MaintenanceStatus $status
 * @property string $title
 * @property string|null $description
 * @property int|null $reported_by
 * @property int|null $assigned_to
 * @property Currency $currency
 * @property int|null $estimated_cost_minor
 * @property int|null $actual_cost_minor
 * @property int|null $room_block_id
 * @property int|null $maintenance_schedule_id
 * @property int|null $housekeeping_task_id
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reference', 'room_id', 'location', 'category', 'priority', 'status', 'title', 'description', 'reported_by', 'assigned_to', 'currency', 'estimated_cost_minor', 'actual_cost_minor', 'maintenance_schedule_id', 'housekeeping_task_id'])]
class MaintenanceRequest extends Model
{
    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /**
     * @return BelongsTo<RoomBlock, $this>
     */
    public function roomBlock(): BelongsTo
    {
        return $this->belongsTo(RoomBlock::class);
    }

    /**
     * @return BelongsTo<MaintenanceSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'maintenance_schedule_id');
    }

    /**
     * @return HasMany<MaintenanceComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(MaintenanceComment::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<MaintenanceAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MaintenanceAttachment::class);
    }

    /**
     * @return HasMany<MaintenancePart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(MaintenancePart::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'category' => MaintenanceCategory::class,
            'priority' => TaskPriority::class,
            'status' => MaintenanceStatus::class,
            'currency' => Currency::class,
            'estimated_cost_minor' => 'integer',
            'actual_cost_minor' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
