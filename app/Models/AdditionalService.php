<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ServiceCategory;
use App\Models\Concerns\HasSlug;
use Carbon\CarbonImmutable;
use Database\Factories\AdditionalServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A bookable guest service such as a spa treatment or airport transfer.
 */
/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property ServiceCategory $category
 * @property string|null $short_description
 * @property string|null $description
 * @property Currency $currency
 * @property int $price_minor
 * @property string $unit
 * @property string|null $image_path
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $display_order
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string $image_url
 */
#[Fillable(['name', 'slug', 'category', 'short_description', 'description', 'currency', 'price_minor', 'unit', 'is_active', 'is_featured', 'display_order'])]
class AdditionalService extends Model
{
    /** @use HasFactory<AdditionalServiceFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    /**
     * @return HasMany<ServiceOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
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
            'category' => ServiceCategory::class,
            'currency' => Currency::class,
            'price_minor' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
