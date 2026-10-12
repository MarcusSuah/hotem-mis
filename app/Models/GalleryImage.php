<?php

namespace App\Models;

use App\Enums\GalleryCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A photo in the public gallery.
 *
 * @property int $id
 * @property string $path
 * @property string $alt_text
 * @property string|null $caption
 * @property GalleryCategory $category
 * @property int $display_order
 * @property bool $is_published
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read string $url
 */
#[Fillable(['alt_text', 'caption', 'category', 'display_order', 'is_published'])]
class GalleryImage extends Model
{
    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('hms.uploads.public_disk'))->url($this->path));
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'category' => GalleryCategory::class,
            'is_published' => 'boolean',
        ];
    }
}
