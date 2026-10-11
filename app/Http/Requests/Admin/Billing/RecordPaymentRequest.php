<?php

namespace App\Http\Requests\Admin\Billing;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::PaymentsCreate->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'external_reference' => [
                Rule::requiredIf(fn () => PaymentMethod::tryFrom((string) $this->input('method'))?->requiresReference() ?? false),
                'nullable', 'string', 'max:128',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Enter an amount with up to two decimal places.',
            'amount.not_regex' => 'The payment amount must be greater than zero.',
            'external_reference.required' => 'Enter the transaction reference from the bank, terminal or mobile money provider.',
        ];
    }
}
