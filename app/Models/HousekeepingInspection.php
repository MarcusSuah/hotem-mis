<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $housekeeping_task_id
 * @property int|null $inspector_id
 * @property bool $passed
 * @property string|null $notes
 * @property CarbonImmutable $created_at
 */
#[Fillable(['housekeeping_task_id', 'inspector_id', 'passed', 'notes'])]
class HousekeepingInspection extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<HousekeepingTask, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(HousekeepingTask::class, 'housekeeping_task_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }
}
