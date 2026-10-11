<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\AmenityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $icon
 * @property string $category
 * @property bool $is_active
 * @property int $display_order
 */
#[Fillable(['name', 'slug', 'icon', 'category', 'is_active', 'display_order'])]
class Amenity extends Model
{
    /** @use HasFactory<AmenityFactory> */
    use HasFactory, HasSlug;

    /**
     * @return BelongsToMany<RoomType, $this>
     */
    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
