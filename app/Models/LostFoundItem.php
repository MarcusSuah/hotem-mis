<?php

namespace App\Models;

use App\Enums\LostFoundStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LostFoundItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $room_id
 * @property int|null $reservation_id
 * @property string $description
 * @property string|null $location_found
 * @property string|null $storage_location
 * @property CarbonImmutable $found_at
 * @property int|null $found_by
 * @property LostFoundStatus $status
 * @property string|null $claimed_by_name
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reference', 'room_id', 'reservation_id', 'description', 'location_found', 'storage_location', 'found_at', 'found_by', 'status', 'claimed_by_name', 'resolved_at', 'notes'])]
class LostFoundItem extends Model
{
    /** @use HasFactory<LostFoundItemFactory> */
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
    public function finder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'found_by')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => LostFoundStatus::class,
            'found_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
