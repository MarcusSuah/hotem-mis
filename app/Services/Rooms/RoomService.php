<?php

namespace App\Services\Rooms;

use App\Models\Property;
use App\Models\ReservationNight;
use App\Models\Room;
use App\Services\Audit\AuditLogger;
use App\Services\Media\ImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomService
{
    public function __construct(
        private ImageStorage $images,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeImageIds
     */
    public function save(?Room $room, array $attributes, array $newImages, array $removeImageIds): Room
    {
        $room ??= new Room(['property_id' => Property::default()->id]);
        $isNew = ! $room->exists;
        $original = $room->only(['number', 'room_type_id', 'operational_status', 'floor']);

        $paths = array_map(fn (UploadedFile $file): string => $this->images->store($file, 'rooms'), $newImages);

        try {
            $obsolete = DB::transaction(function () use ($room, $attributes, $paths, $removeImageIds): array {
                $room->fill($attributes)->save();

                $removed = $room->images()->whereIn('id', $removeImageIds)->get();
                $room->images()->whereIn('id', $removed->modelKeys())->delete();

                $nextOrder = (int) $room->images()->max('sort_order') + 1;

                foreach ($paths as $index => $path) {
                    $room->images()->create(['path' => $path, 'alt_text' => "Room {$room->number}", 'sort_order' => $nextOrder + $index]);
                }

                return $removed->pluck('path')->all();
            });
        } catch (\Throwable $exception) {
            array_map($this->images->delete(...), $paths);

            throw $exception;
        }

        array_map($this->images->delete(...), $obsolete);

        if ($isNew) {
            $this->audit->record('room.created', $room, newValues: ['number' => $room->number, 'room_type_id' => $room->room_type_id]);
        } else {
            $changes = array_diff_assoc(
                array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $room->only(array_keys($original))),
                array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $original),
            );

            if ($changes !== []) {
                $this->audit->record('room.updated', $room, array_intersect_key($original, $changes), $changes);
            }
        }

        return $room;
    }

    public function delete(Room $room): void
    {
        $hasUpcomingNights = ReservationNight::query()
            ->where('room_id', $room->id)
            ->where('night_date', '>=', today()->toDateString())
            ->exists();

        if ($hasUpcomingNights) {
            throw ValidationException::withMessages([
                'room' => "Room {$room->number} has upcoming reservations. Reassign them before deleting the room.",
            ]);
        }

        $room->delete();

        $this->audit->record('room.deleted', $room, ['number' => $room->number]);
    }
}
