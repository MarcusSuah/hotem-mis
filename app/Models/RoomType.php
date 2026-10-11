<?php

namespace App\Models;

use App\Enums\Currency;
use App\Models\Concerns\HasSlug;
use App\Support\Money\Money;
use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $property_id
 * @property string $name
 * @property string $slug
 * @property string|null $short_description
 * @property string|null $description
 * @property string|null $featured_image_path
 * @property string|null $featured_image_alt
 * @property int $max_occupancy
 * @property int $max_adults
 * @property int $max_children
 * @property int $included_adults
 * @property int $included_children
 * @property string $bed_type
 * @property int $bed_count
 * @property string|null $size_sqm
 * @property string|null $bathroom_details
 * @property Currency $currency
 * @property int $base_rate_minor
 * @property int|null $weekend_rate_minor
 * @property int $extra_adult_minor
 * @property int $extra_child_minor
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $display_order
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property-read string|null $featured_image_url
 */
#[Fillable([
    'property_id', 'name', 'slug', 'short_description', 'description', 'featured_image_alt',
    'max_occupancy', 'max_adults', 'max_children', 'included_adults', 'included_children',
    'bed_type', 'bed_count', 'size_sqm', 'bathroom_details', 'currency',
    'base_rate_minor', 'weekend_rate_minor', 'extra_adult_minor', 'extra_child_minor',
    'is_active', 'is_featured', 'display_order', 'meta_title', 'meta_description',
])]
class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<RoomTypeImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(RoomTypeImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Amenity, $this>
     */
    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class)->orderBy('display_order')->orderBy('name');
    }

    /**
     * @return HasMany<SeasonalRate, $this>
     */
    public function seasonalRates(): HasMany
    {
        return $this->hasMany(SeasonalRate::class);
    }

    public function baseRate(): Money
    {
        return Money::ofMinor($this->base_rate_minor, $this->currency);
    }

    public function weekendRate(): ?Money
    {
        return $this->weekend_rate_minor === null ? null : Money::ofMinor($this->weekend_rate_minor, $this->currency);
    }

    /**
     * @param  Builder<RoomType>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<RoomType>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('display_order')->orderBy('name');
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function featuredImageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->featured_image_path
            ? Storage::disk(config('hms.uploads.public_disk'))->url($this->featured_image_path)
            : null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'size_sqm' => 'decimal:1',
            'base_rate_minor' => 'integer',
            'weekend_rate_minor' => 'integer',
            'extra_adult_minor' => 'integer',
            'extra_child_minor' => 'integer',
            'max_occupancy' => 'integer',
            'max_adults' => 'integer',
            'max_children' => 'integer',
            'included_adults' => 'integer',
            'included_children' => 'integer',
            'bed_count' => 'integer',
            'display_order' => 'integer',
        ];
    }
}
