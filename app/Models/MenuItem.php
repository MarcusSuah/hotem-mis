<?php

namespace App\Models;

use App\Enums\Currency;
use App\Models\Concerns\HasSlug;
use Carbon\CarbonImmutable;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $menu_category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Currency $currency
 * @property int $price_minor
 * @property string|null $image_path
 * @property bool $is_available
 * @property bool $is_featured
 * @property int $display_order
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string $image_url
 */
#[Fillable(['menu_category_id', 'name', 'slug', 'description', 'currency', 'price_minor', 'is_available', 'is_featured', 'display_order'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, HasSlug, SoftDeletes;

    /**
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
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
            'currency' => Currency::class,
            'price_minor' => 'integer',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
