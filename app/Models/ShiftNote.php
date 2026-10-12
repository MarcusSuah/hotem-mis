<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A handover note left by one shift for the next (night audit, front office,
 * engineering). Notes are append-only so the log can be trusted.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $topic
 * @property string $body
 * @property CarbonImmutable $created_at
 */
#[Fillable(['topic', 'body'])]
class ShiftNote extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
