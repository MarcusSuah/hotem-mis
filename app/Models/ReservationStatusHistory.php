<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $reservation_id
 * @property ReservationStatus|null $from_status
 * @property ReservationStatus $to_status
 * @property int|null $user_id
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
#[Fillable(['reservation_id', 'from_status', 'to_status', 'user_id', 'note'])]
class ReservationStatusHistory extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ReservationStatus::class,
            'to_status' => ReservationStatus::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
