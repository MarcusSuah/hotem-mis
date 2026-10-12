<?php

namespace App\Http\Requests\Admin\Operations;

use App\Enums\HousekeepingTaskType;
use App\Enums\Permission;
use App\Enums\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HousekeepingTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::HousekeepingManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'type' => ['required', Rule::enum(HousekeepingTaskType::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'scheduled_for' => ['required', 'date', 'after_or_equal:today'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
