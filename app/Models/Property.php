<?php

namespace App\Models;

use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A hotel property. The application currently operates a single default
 * property; inventory is keyed by property so more hotels can be added later.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $code
 * @property string $timezone
 * @property bool $is_default
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'code', 'timezone', 'is_default', 'is_active'])]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory;

    /**
     * Resolve the default property, which every single-property workflow uses.
     */
    public static function default(): self
    {
        return static::query()->where('is_default', true)->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
