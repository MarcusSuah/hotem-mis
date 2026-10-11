<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property CarbonImmutable $business_date
 * @property int|null $run_by
 * @property array<string, mixed> $summary
 * @property CarbonImmutable $completed_at
 */
#[Fillable(['business_date', 'run_by', 'summary', 'completed_at'])]
class NightAudit extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function runner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_by')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'immutable_date',
            'summary' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
