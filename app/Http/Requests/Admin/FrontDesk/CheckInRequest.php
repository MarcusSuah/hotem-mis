<?php

namespace App\Http\Requests\Admin\FrontDesk;

use App\Enums\Permission;
use App\Services\FrontDesk\CheckInDetails;
use App\Support\Money\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::CheckinManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identity_type' => ['nullable', Rule::in(['passport', 'national_id', 'drivers_license', 'other'])],
            'identity_number' => ['nullable', 'required_with:identity_type', 'string', 'max:64'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:500'],
            'additional_guests' => ['array', 'max:10'],
            'additional_guests.*.name' => ['required', 'string', 'max:150'],
            'additional_guests.*.age_category' => ['required', Rule::in(['adult', 'child'])],
            'waive_deposit' => ['boolean'],
            'early_check_in_fee' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identity_number.required_with' => 'Enter the document number for the selected ID type.',
            'additional_guests.*.name.required' => 'Enter a name for each accompanying guest.',
            'early_check_in_fee.regex' => 'Enter an amount with up to two decimal places.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('waive_deposit') && ! $this->user()->can(Permission::FoliosOverride->value)) {
                    $validator->errors()->add('waive_deposit', 'You are not allowed to waive deposits.');
                }

                if (filled($this->input('identity_number')) && ! $this->user()->can(Permission::GuestsViewIdentity->value)) {
                    $validator->errors()->add('identity_number', 'You are not allowed to record identity documents.');
                }
            },
        ];
    }

    public function details(string $currency): CheckInDetails
    {
        return new CheckInDetails(
            additionalGuests: array_values(array_map(
                fn (array $guest): array => ['name' => (string) $guest['name'], 'age_category' => (string) $guest['age_category']],
                (array) $this->validated('additional_guests', []),
            )),
            identityType: $this->filled('identity_type') ? $this->string('identity_type')->toString() : null,
            identityNumber: $this->filled('identity_number') ? $this->string('identity_number')->toString() : null,
            countryCode: $this->filled('country_code') ? $this->string('country_code')->upper()->toString() : null,
            address: $this->filled('address') ? $this->string('address')->toString() : null,
            waiveDeposit: $this->boolean('waive_deposit'),
            earlyCheckInFee: $this->filled('early_check_in_fee') ? Money::of($this->string('early_check_in_fee')->toString(), $currency) : null,
        );
    }
}
