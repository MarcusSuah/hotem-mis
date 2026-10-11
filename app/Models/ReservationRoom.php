<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room allocated to a reservation for [start_date, end_date).
 *
 * @property int $id
 * @property int $reservation_id
 * @property int $room_id
 * @property int $room_type_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property int $room_total_minor
 * @property CarbonImmutable|null $released_at
 * @property string|null $release_reason
 * @property int|null $assigned_by
 */
#[Fillable(['reservation_id', 'room_id', 'room_type_id', 'start_date', 'end_date', 'room_total_minor', 'assigned_by'])]
class ReservationRoom extends Model
{
    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class)->withTrashed();
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    /**
     * @return HasMany<ReservationNight, $this>
     */
    public function nights(): HasMany
    {
        return $this->hasMany(ReservationNight::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'room_total_minor' => 'integer',
            'released_at' => 'immutable_datetime',
        ];
    }
}
