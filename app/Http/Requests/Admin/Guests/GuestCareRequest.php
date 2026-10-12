<?php

namespace App\Http\Requests\Admin\Guests;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\Permission;
use App\Enums\TaskPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Complaints, guest preferences and loyalty adjustments.
 */
class GuestCareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::GuestsUpdate->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.complaints.store' => [
                'guest_id' => ['nullable', 'integer', Rule::exists('guests', 'id')],
                'reservation_id' => ['nullable', 'integer', Rule::exists('reservations', 'id')],
                'category' => ['required', Rule::enum(ComplaintCategory::class)],
                'severity' => ['required', Rule::enum(TaskPriority::class)],
                'subject' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string', 'max:5000'],
                'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
            ],
            'admin.complaints.update' => [
                'status' => ['required', Rule::enum(ComplaintStatus::class)],
                'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
                'resolution' => ['nullable', Rule::requiredIf(in_array($this->input('status'), [ComplaintStatus::Resolved->value, ComplaintStatus::Closed->value], true)), 'string', 'max:5000'],
            ],
            'admin.guests.preferences.store' => [
                'category' => ['required', Rule::in(['room', 'dining', 'amenities', 'communication', 'accessibility', 'other'])],
                'preference' => ['required', 'string', 'max:255'],
            ],
            'admin.guests.loyalty.store' => [
                'points' => ['required', 'integer', 'not_in:0', 'between:-100000,100000'],
                'reason' => ['required', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolution.required' => 'Describe how the complaint was resolved.',
            'points.not_in' => 'Enter a non-zero number of points.',
        ];
    }
}
