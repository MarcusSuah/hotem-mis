<?php

namespace App\Http\Requests\Admin\Rooms;

use App\Enums\Permission;
use App\Models\Amenity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAmenityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::RoomTypesManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Amenity|null $amenity */
        $amenity = $this->route('amenity');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('amenities', 'name')->ignore($amenity?->id)],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'category' => ['required', Rule::in(['room', 'hotel'])],
            'is_active' => ['boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['icon.regex' => 'Use a Lucide icon name such as "wifi" or "coffee".'];
    }

    /**
     * @return array<string, mixed>
     */
    public function amenityAttributes(): array
    {
        return [
            ...$this->safe()->only(['name', 'icon', 'category', 'display_order']),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
