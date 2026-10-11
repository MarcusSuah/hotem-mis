<?php

namespace App\Http\Requests\Admin\Billing;

use App\Enums\Currency;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ExchangeRatesManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quote_currency' => ['required', Rule::enum(Currency::class), 'different:base_currency'],
            'base_currency' => ['required', Rule::enum(Currency::class)],
            'rate' => ['required', 'regex:/^\d{1,10}(\.\d{1,8})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'effective_at' => ['required', 'date', 'after_or_equal:'.now()->subMinutes(5)->toDateTimeString()],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.regex' => 'Enter the rate with up to eight decimal places.',
            'rate.not_regex' => 'The rate must be greater than zero.',
            'effective_at.after_or_equal' => 'Rates cannot be back-dated; historical transactions keep the rate they used.',
            'quote_currency.different' => 'Choose two different currencies.',
        ];
    }
}
