<?php

namespace App\Http\Requests\Admin\Reservations;

use App\Services\Booking\StayPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ModifyReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('reservation'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_in_date.after_or_equal' => 'Check-in cannot be moved into the past.',
            'check_out_date.after' => 'Check-out must be after check-in.',
        ];
    }

    public function stay(): StayPeriod
    {
        return new StayPeriod($this->string('check_in_date')->toString(), $this->string('check_out_date')->toString());
    }
}
