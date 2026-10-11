<?php

namespace App\Http\Requests\Admin\Rooms;

use App\Enums\OperationalStatus;
use App\Enums\Permission;
use App\Models\Property;
use App\Models\ReservationNight;
use App\Models\Room;
use App\Services\Media\ImageStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('room') === null
            ? $this->user()->can(Permission::RoomsCreate->value)
            : $this->user()->can(Permission::RoomsUpdate->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Room|null $room */
        $room = $this->route('room');

        return [
            'number' => [
                'required', 'string', 'max:16', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('rooms', 'number')->where('property_id', Property::default()->id)->ignore($room?->id),
            ],
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')->whereNull('deleted_at')],
            'floor' => ['required', 'integer', 'min:-5', 'max:200'],
            'building' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'operational_status' => ['required', Rule::enum(OperationalStatus::class)],
            'is_accessible' => ['boolean'],
            'accessibility_notes' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'images' => ['array', 'max:10'],
            'images.*' => ImageStorage::rules(),
            'remove_image_ids' => ['array'],
            'remove_image_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.regex' => 'Room numbers may contain letters, numbers and dashes only.',
            'number.unique' => 'Another room already uses this number.',
        ];
    }

    /**
     * Rooms holding future reservations cannot be taken out of service or
     * moved to another room type until those guests are reassigned.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Room|null $room */
                $room = $this->route('room');

                if ($room === null || $validator->errors()->hasAny(['operational_status', 'room_type_id'])) {
                    return;
                }

                $futureNights = ReservationNight::query()
                    ->where('room_id', $room->id)
                    ->where('night_date', '>=', today()->toDateString())
                    ->count();

                if ($futureNights === 0) {
                    return;
                }

                if ($this->input('operational_status') === OperationalStatus::OutOfService->value) {
                    $validator->errors()->add('operational_status', "This room has {$futureNights} reserved night(s) ahead. Reassign those reservations first, or add a dated maintenance block instead.");
                }

                if ($this->integer('room_type_id') !== $room->room_type_id) {
                    $validator->errors()->add('room_type_id', 'Reassign upcoming reservations before changing this room\'s type.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function roomAttributes(): array
    {
        return [
            ...$this->safe()->only(['number', 'room_type_id', 'floor', 'building', 'description', 'operational_status', 'accessibility_notes', 'notes']),
            'is_accessible' => $this->boolean('is_accessible'),
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function imageFiles(): array
    {
        return array_values(array_filter((array) $this->file('images', []), fn ($file) => $file instanceof UploadedFile));
    }
}
