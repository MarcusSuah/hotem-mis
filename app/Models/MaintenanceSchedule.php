<?php

namespace App\Models;

use App\Enums\MaintenanceCategory;
use App\Enums\TaskPriority;
use Carbon\CarbonImmutable;
use Database\Factories\MaintenanceScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recurring preventive maintenance that generates requests when due.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int|null $room_id
 * @property string|null $location
 * @property MaintenanceCategory $category
 * @property TaskPriority $priority
 * @property int $frequency_days
 * @property CarbonImmutable $next_due_on
 * @property int|null $assigned_to
 * @property bool $is_active
 * @property CarbonImmutable|null $last_generated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'description', 'room_id', 'location', 'category', 'priority', 'frequency_days', 'next_due_on', 'assigned_to', 'is_active'])]
class MaintenanceSchedule extends Model
{
    /** @use HasFactory<MaintenanceScheduleFactory> */
    use HasFactory;

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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'category' => MaintenanceCategory::class,
            'priority' => TaskPriority::class,
            'next_due_on' => 'immutable_date',
            'frequency_days' => 'integer',
            'is_active' => 'boolean',
            'last_generated_at' => 'immutable_datetime',
        ];
    }
}
