<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SeasonalRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Overrides the nightly rate for nights from start_date to end_date inclusive.
 *
 * @property int $id
 * @property int $room_type_id
 * @property string $name
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property int $rate_minor
 * @property int|null $weekend_rate_minor
 * @property int $priority
 * @property bool $is_active
 */
#[Fillable(['room_type_id', 'name', 'start_date', 'end_date', 'rate_minor', 'weekend_rate_minor', 'priority', 'is_active'])]
class SeasonalRate extends Model
{
    /** @use HasFactory<SeasonalRateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class)->withTrashed();
    }

    public function covers(CarbonImmutable $night): bool
    {
        return $night->betweenIncluded($this->start_date, $this->end_date);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'rate_minor' => 'integer',
            'weekend_rate_minor' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
