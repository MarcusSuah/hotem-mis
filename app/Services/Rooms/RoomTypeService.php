<?php

namespace App\Services\Rooms;

use App\Models\Property;
use App\Models\RoomType;
use App\Services\Audit\AuditLogger;
use App\Services\Media\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomTypeService
{
    public function __construct(
        private ImageStorage $images,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $amenityIds
     * @param  list<UploadedFile>  $gallery
     * @param  list<int>  $removeImageIds
     */
    public function save(
        ?RoomType $roomType,
        array $attributes,
        array $amenityIds,
        ?UploadedFile $featuredImage,
        array $gallery,
        array $removeImageIds,
    ): RoomType {
        $roomType ??= new RoomType(['property_id' => Property::default()->id]);
        $isNew = ! $roomType->exists;

        // Files are written before the transaction; any written file is removed
        // again if the database work fails so no orphans are left behind.
        $newFeaturedPath = $featuredImage ? $this->images->store($featuredImage, 'room-types') : null;
        $galleryPaths = array_map(fn (UploadedFile $file): string => $this->images->store($file, 'room-types/gallery'), $gallery);

        try {
            $obsoletePaths = DB::transaction(function () use ($roomType, $attributes, $amenityIds, $newFeaturedPath, $galleryPaths, $removeImageIds): array {
                $obsolete = [];
                $roomType->fill($attributes);

                if ($newFeaturedPath !== null) {
                    $obsolete[] = $roomType->featured_image_path;
                    $roomType->featured_image_path = $newFeaturedPath;
                }

                $roomType->save();
                $roomType->amenities()->sync($amenityIds);

                $removed = $roomType->images()->whereIn('id', $removeImageIds)->get();
                $obsolete = [...$obsolete, ...$removed->pluck('path')->all()];
                $roomType->images()->whereIn('id', $removed->modelKeys())->delete();

                $nextOrder = (int) $roomType->images()->max('sort_order') + 1;

                foreach ($galleryPaths as $index => $path) {
                    $roomType->images()->create([
                        'path' => $path,
                        'alt_text' => $roomType->name,
                        'sort_order' => $nextOrder + $index,
                    ]);
                }

                return array_values(array_filter($obsolete));
            });
        } catch (\Throwable $exception) {
            $this->images->delete($newFeaturedPath);
            array_map($this->images->delete(...), $galleryPaths);

            throw $exception;
        }

        array_map($this->images->delete(...), $obsoletePaths);

        $this->audit->record($isNew ? 'room_type.created' : 'room_type.updated', $roomType, newValues: [
            'name' => $roomType->name,
            'base_rate_minor' => $roomType->base_rate_minor,
        ]);

        return $roomType;
    }

    public function delete(RoomType $roomType): void
    {
        if ($roomType->rooms()->exists()) {
            throw ValidationException::withMessages([
                'room_type' => 'Move or delete the rooms of this type before deleting it.',
            ]);
        }

        $roomType->delete();

        $this->audit->record('room_type.deleted', $roomType, ['name' => $roomType->name]);
    }
}
