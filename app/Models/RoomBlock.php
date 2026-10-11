<?php

namespace App\Models;

use App\Enums\RoomBlockType;
use Carbon\CarbonImmutable;
use Database\Factories\RoomBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Takes a room out of sale from start_date (inclusive) to end_date (exclusive).
 *
 * @property int $id
 * @property int $room_id
 * @property RoomBlockType $type
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property string $reason
 * @property int|null $maintenance_request_id
 * @property int|null $created_by
 * @property CarbonImmutable|null $released_at
 * @property int|null $released_by
 */
#[Fillable(['room_id', 'type', 'start_date', 'end_date', 'reason', 'maintenance_request_id', 'created_by'])]
class RoomBlock extends Model
{
    /** @use HasFactory<RoomBlockFactory> */
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @param  Builder<RoomBlock>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('released_at');
    }

    /**
     * Blocks overlapping the half-open stay interval [checkIn, checkOut).
     *
     * @param  Builder<RoomBlock>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonImmutable $checkIn, CarbonImmutable $checkOut): void
    {
        $query->where('start_date', '<', $checkOut->toDateString())
            ->where('end_date', '>', $checkIn->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => RoomBlockType::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'released_at' => 'datetime',
        ];
    }
}
