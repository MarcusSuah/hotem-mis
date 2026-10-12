<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Carbon\CarbonImmutable;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A marketed package. Booking an offer applies its linked rate plan, so
 * the advertised price always comes from the real pricing engine.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $image_path
 * @property string|null $image_alt
 * @property int|null $rate_plan_id
 * @property int|null $room_type_id
 * @property CarbonImmutable|null $valid_from
 * @property CarbonImmutable|null $valid_until
 * @property bool $is_published
 * @property int $display_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read string|null $image_url
 */
#[Fillable(['title', 'slug', 'summary', 'body', 'image_alt', 'rate_plan_id', 'room_type_id', 'valid_from', 'valid_until', 'is_published', 'display_order'])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    protected function slugSource(): string
    {
        return 'title';
    }

    /**
     * @return BelongsTo<RatePlan, $this>
     */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Published offers that can still be booked today.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $today = CarbonImmutable::today()->toDateString();

        $query->where('is_published', true)
            ->where(fn (Builder $inner) => $inner->whereNull('valid_until')->orWhere('valid_until', '>=', $today));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->image_path ? Storage::disk(config('hms.uploads.public_disk'))->url($this->image_path) : null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'is_published' => 'boolean',
        ];
    }
}
