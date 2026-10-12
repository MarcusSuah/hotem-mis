<?php

namespace App\Models;

use App\Enums\HousekeepingStatus;
use App\Enums\OperationalStatus;
use App\Models\Concerns\HasSlug;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $property_id
 * @property int $room_type_id
 * @property string $number
 * @property string $slug
 * @property int $floor
 * @property string|null $building
 * @property string|null $description
 * @property OperationalStatus $operational_status
 * @property HousekeepingStatus $housekeeping_status
 * @property bool $is_accessible
 * @property string|null $accessibility_notes
 * @property string|null $notes
 */
#[Fillable([
    'property_id', 'room_type_id', 'number', 'slug', 'floor', 'building', 'description',
    'operational_status', 'housekeeping_status', 'is_accessible', 'accessibility_notes', 'notes',
])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'operational_status' => 'in_service',
        'housekeeping_status' => 'clean',
    ];

    protected function slugSource(): string
    {
        return 'number';
    }

    /**
     * Rooms are addressed by id in the back office; slugs exist for public pages.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return HasMany<RoomImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<RoomBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(RoomBlock::class);
    }

    /**
     * @return HasMany<ReservationRoom, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    /**
     * @return HasMany<ReservationNight, $this>
     */
    public function nights(): HasMany
    {
        return $this->hasMany(ReservationNight::class);
    }

    /**
     * @return HasMany<HousekeepingTask, $this>
     */
    public function housekeepingTasks(): HasMany
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    /**
     * @return HasMany<MaintenanceRequest, $this>
     */
    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    /**
     * @param  Builder<Room>  $query
     */
    #[Scope]
    protected function inService(Builder $query): void
    {
        $query->where('operational_status', OperationalStatus::InService->value);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'operational_status' => OperationalStatus::class,
            'housekeeping_status' => HousekeepingStatus::class,
            'is_accessible' => 'boolean',
            'floor' => 'integer',
        ];
    }
}
