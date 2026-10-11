<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One room held for one night. Unique per (room_id, night_date).
 *
 * @property int $id
 * @property int $reservation_id
 * @property int $reservation_room_id
 * @property int $room_id
 * @property int $room_type_id
 * @property CarbonImmutable $night_date
 * @property int $rate_minor
 * @property int $base_rate_minor
 */
#[Fillable(['reservation_id', 'reservation_room_id', 'room_id', 'room_type_id', 'night_date', 'rate_minor', 'base_rate_minor'])]
class ReservationNight extends Model
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
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'night_date' => 'immutable_date',
            'rate_minor' => 'integer',
            'base_rate_minor' => 'integer',
        ];
    }
}
