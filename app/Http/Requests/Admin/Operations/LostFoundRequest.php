<?php

namespace App\Http\Requests\Admin\Operations;

use App\Enums\LostFoundStatus;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LostFoundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::HousekeepingManage->value) || $this->user()->can(Permission::HousekeepingPerform->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:500'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'location_found' => ['nullable', 'string', 'max:255'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'found_at' => ['required', 'date', 'before_or_equal:now'],
            'status' => ['required', Rule::enum(LostFoundStatus::class)],
            'claimed_by_name' => ['nullable', 'required_if:status,claimed', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['claimed_by_name.required_if' => 'Record who collected the item.'];
    }
}
