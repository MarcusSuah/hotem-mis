<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\TaskPriority;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $reference
 * @property int|null $guest_id
 * @property int|null $reservation_id
 * @property ComplaintCategory $category
 * @property TaskPriority $severity
 * @property string $subject
 * @property string $description
 * @property ComplaintStatus $status
 * @property int|null $assigned_to
 * @property string|null $resolution
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reference', 'guest_id', 'reservation_id', 'category', 'severity', 'subject', 'description', 'status', 'assigned_to', 'resolution', 'resolved_at', 'created_by'])]
class Complaint extends Model
{
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withTrashed();
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
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'severity' => TaskPriority::class,
            'status' => ComplaintStatus::class,
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
