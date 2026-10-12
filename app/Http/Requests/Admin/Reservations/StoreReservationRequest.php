<?php

namespace App\Http\Requests\Admin\Reservations;

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Booking\StayPeriod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Reservation::class);
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
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->whereNull('deleted_at')],
            'rate_plan_id' => ['nullable', 'integer', Rule::exists('rate_plans', 'id')->where('is_active', true)],
            'currency' => ['required', Rule::enum(Currency::class)],
            'source' => ['required', Rule::in(array_column(BookingSource::staffChannels(), 'value'))],
            'guest_id' => ['nullable', 'integer', Rule::exists('guests', 'id')->whereNull('deleted_at')],
            'first_name' => ['exclude_with:guest_id', 'required', 'string', 'max:100'],
            'last_name' => ['exclude_with:guest_id', 'required', 'string', 'max:100'],
            'email' => ['exclude_with:guest_id', 'nullable', 'email', 'max:255'],
            'phone' => ['exclude_with:guest_id', 'nullable', 'string', 'max:32'],
            'country_code' => ['exclude_with:guest_id', 'nullable', 'string', 'size:2'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'confirm' => ['boolean'],
            'complimentary' => ['boolean'],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_in_date.after_or_equal' => 'Check-in cannot be in the past.',
            'check_out_date.after' => 'Check-out must be after check-in.',
            'first_name.required' => 'Select an existing guest or enter the guest\'s first name.',
            'last_name.required' => 'Enter the guest\'s last name.',
            'room_type_id.required' => 'Choose a room type.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['room_id', 'room_type_id']) || ! $this->filled('room_id')) {
                    return;
                }

                $belongs = Room::query()->whereKey($this->integer('room_id'))->where('room_type_id', $this->integer('room_type_id'))->exists();

                if (! $belongs) {
                    $validator->errors()->add('room_id', 'The selected room is not of the chosen room type.');
                }
            },
            function (Validator $validator): void {
                if ($this->boolean('complimentary') && ! $this->user()->can(Permission::BookingsUpdate->value)) {
                    $validator->errors()->add('complimentary', 'You are not allowed to create complimentary stays.');
                }
            },
        ];
    }

    public function stay(): StayPeriod
    {
        return new StayPeriod($this->string('check_in_date')->toString(), $this->string('check_out_date')->toString());
    }

    /**
     * @return array{first_name: string, last_name: string, email: string|null, phone: string|null, country_code: string|null}
     */
    public function newGuestDetails(): array
    {
        return [
            'first_name' => $this->string('first_name')->trim()->toString(),
            'last_name' => $this->string('last_name')->trim()->toString(),
            'email' => $this->filled('email') ? $this->string('email')->trim()->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            'country_code' => $this->filled('country_code') ? $this->string('country_code')->upper()->toString() : null,
        ];
    }
}
