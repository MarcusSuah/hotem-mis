<?php

namespace App\Http\Requests\Admin\Operations;

use App\Enums\MaintenanceCategory;
use App\Enums\Permission;
use App\Enums\TaskPriority;
use App\Services\Booking\StayPeriod;
use App\Support\Money\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Reporting a maintenance issue, from the maintenance screen or escalated
 * from a housekeeping task.
 */
class MaintenanceRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::MaintenanceRequest->value)
            || $this->user()->can(Permission::MaintenanceManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(MaintenanceCategory::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'location' => ['nullable', 'required_without:room_id', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'estimated_cost' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'block_room' => ['boolean'],
            'block_from' => ['nullable', 'required_if_accepted:block_room', 'date', 'after_or_equal:today'],
            'block_until' => ['nullable', 'required_if_accepted:block_room', 'date', 'after:block_from'],
            'photos' => ['array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('hms.uploads.max_image_kb')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'location.required_without' => 'Choose a room or describe where the problem is.',
            'block_from.required_if_accepted' => 'Choose when the room goes out of service.',
            'block_until.required_if_accepted' => 'Choose when the room is expected back.',
            'block_until.after' => 'The room must come back after it goes out of service.',
            'estimated_cost.regex' => 'Enter an amount with up to two decimal places.',
        ];
    }

    /**
     * @return array{title: string, description: string|null, category: MaintenanceCategory, priority: TaskPriority, room_id: int|null, location: string|null, estimated_cost: Money|null}
     */
    public function requestData(string $currency): array
    {
        return [
            'title' => $this->string('title')->toString(),
            'description' => $this->filled('description') ? $this->string('description')->toString() : null,
            'category' => MaintenanceCategory::from($this->string('category')->toString()),
            'priority' => TaskPriority::from($this->string('priority')->toString()),
            'room_id' => $this->filled('room_id') ? $this->integer('room_id') : null,
            'location' => $this->filled('location') ? $this->string('location')->toString() : null,
            'estimated_cost' => $this->filled('estimated_cost') ? Money::of($this->string('estimated_cost')->toString(), $currency) : null,
        ];
    }

    public function blockPeriod(): ?StayPeriod
    {
        return $this->boolean('block_room') && $this->filled('room_id')
            ? new StayPeriod($this->string('block_from')->toString(), $this->string('block_until')->toString())
            : null;
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        return array_values(array_filter((array) $this->file('photos', []), fn ($file) => $file instanceof UploadedFile));
    }
}
