<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

/**
 * Generates a unique slug when a model is created.
 *
 * Policy: slugs are generated once from the source attribute and are not
 * regenerated when the name changes, so public URLs stay stable. Staff may
 * set a new slug explicitly; it is normalised and de-duplicated.
 *
 * @mixin Model
 */
trait HasSlug
{
    protected static function bootHasSlug(): void
    {
        static::saving(function (self $model): void {
            $current = $model->getAttribute('slug');

            if (blank($current)) {
                $model->setAttribute('slug', $model->uniqueSlug((string) $model->getAttribute($model->slugSource())));
            } elseif ($model->isDirty('slug')) {
                $model->setAttribute('slug', $model->uniqueSlug((string) $current));
            }
        });
    }

    /**
     * Attribute the slug is generated from.
     */
    protected function slugSource(): string
    {
        return 'name';
    }

    public function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug): bool
    {
        $query = static::query()->where('slug', $slug);

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
