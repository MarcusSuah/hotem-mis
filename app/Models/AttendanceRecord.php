<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $staff_profile_id
 * @property CarbonImmutable $work_date
 * @property CarbonImmutable|null $clock_in_at
 * @property CarbonImmutable|null $clock_out_at
 * @property AttendanceStatus $status
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['staff_profile_id', 'work_date', 'clock_in_at', 'clock_out_at', 'status', 'notes', 'recorded_by'])]
class AttendanceRecord extends Model
{
    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_id')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'work_date' => 'immutable_date',
            'clock_in_at' => 'immutable_datetime',
            'clock_out_at' => 'immutable_datetime',
        ];
    }
}
