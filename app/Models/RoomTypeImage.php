<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $room_type_id
 * @property string $path
 * @property string|null $alt_text
 * @property int $sort_order
 * @property-read string $url
 */
#[Fillable(['room_type_id', 'path', 'alt_text', 'sort_order'])]
class RoomTypeImage extends Model
{
    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('hms.uploads.public_disk'))->url($this->path));
    }
}
