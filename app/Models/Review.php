<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest's review of a completed stay. One review per reservation, and
 * only reviews approved by a moderator are shown publicly.
 *
 * @property int $id
 * @property int $reservation_id
 * @property int $guest_id
 * @property int|null $user_id
 * @property int $rating
 * @property string $title
 * @property string $body
 * @property ReviewStatus $status
 * @property string|null $management_response
 * @property CarbonImmutable|null $responded_at
 * @property int|null $moderated_by
 * @property CarbonImmutable|null $moderated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reservation_id', 'guest_id', 'user_id', 'rating', 'title', 'body'])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by')->withTrashed();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', ReviewStatus::Approved->value);
    }

    /**
     * The public byline: first name and last initial only.
     */
    public function displayName(): string
    {
        $guest = $this->guest;

        return trim($guest->first_name.' '.mb_substr($guest->last_name, 0, 1).'.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'responded_at' => 'immutable_datetime',
            'moderated_at' => 'immutable_datetime',
        ];
    }
}
