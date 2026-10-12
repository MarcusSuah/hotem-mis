<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Carbon\CarbonImmutable;
use Database\Factories\HotelEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A public happening at the hotel: live music, brunches, holiday dinners.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $body
 * @property string|null $venue
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property string|null $image_path
 * @property string|null $image_alt
 * @property bool $is_published
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read string|null $image_url
 */
#[Fillable(['title', 'slug', 'summary', 'body', 'venue', 'starts_at', 'ends_at', 'image_alt', 'is_published'])]
class HotelEvent extends Model
{
    /** @use HasFactory<HotelEventFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    protected function slugSource(): string
    {
        return 'title';
    }

    /**
     * Published events that have not finished yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('is_published', true)
            ->where(fn (Builder $inner) => $inner->where('ends_at', '>=', now())
                ->orWhere(fn (Builder $open) => $open->whereNull('ends_at')->where('starts_at', '>=', now()->startOfDay())));
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
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'is_published' => 'boolean',
        ];
    }
}
