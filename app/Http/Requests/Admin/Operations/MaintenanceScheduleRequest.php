<?php

namespace App\Http\Requests\Admin\Operations;

use App\Enums\MaintenanceCategory;
use App\Enums\Permission;
use App\Enums\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::MaintenanceManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'location' => ['nullable', 'required_without:room_id', 'string', 'max:255'],
            'category' => ['required', Rule::enum(MaintenanceCategory::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'frequency_days' => ['required', 'integer', 'min:1', 'max:730'],
            'next_due_on' => ['required', 'date'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scheduleAttributes(): array
    {
        return [
            ...$this->safe()->except(['is_active']),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
