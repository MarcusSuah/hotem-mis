<?php

namespace App\Http\Requests\Admin\Guests;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Models\Guest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('guest') instanceof Guest
            ? $this->user()->can(Permission::GuestsUpdate->value)
            : $this->user()->can(Permission::GuestsCreate->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Guest|null $guest */
        $guest = $this->route('guest');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('guests', 'email')->ignore($guest?->id)->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'identity_type' => ['nullable', Rule::in(['passport', 'national_id', 'drivers_license', 'other'])],
            'identity_number' => ['nullable', 'string', 'max:64'],
            'is_vip' => ['boolean'],
            'marketing_consent' => ['boolean'],
            'preferred_currency' => ['nullable', Rule::enum(Currency::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.unique' => 'Another guest profile already uses this email address.'];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (filled($this->input('identity_number')) && ! $this->user()->can(Permission::GuestsViewIdentity->value)) {
                    $validator->errors()->add('identity_number', 'You are not allowed to record identity documents.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function guestAttributes(): array
    {
        $attributes = [
            ...$this->safe()->only(['first_name', 'last_name', 'phone', 'address', 'date_of_birth', 'preferred_currency', 'notes']),
            'email' => $this->filled('email') ? $this->string('email')->lower()->toString() : null,
            'country_code' => $this->filled('country_code') ? $this->string('country_code')->upper()->toString() : null,
            'is_vip' => $this->boolean('is_vip'),
            'marketing_consent' => $this->boolean('marketing_consent'),
        ];

        // Identity details are only changed by staff allowed to see them, and a
        // blank number never erases a stored one.
        if ($this->user()->can(Permission::GuestsViewIdentity->value)) {
            $attributes['identity_type'] = $this->input('identity_type');

            if ($this->filled('identity_number')) {
                $attributes['identity_number'] = $this->string('identity_number')->toString();
            }
        }

        return $attributes;
    }
}
