<?php

namespace App\Http\Requests\Admin\Billing;

use App\Enums\ChargeCategory;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::FoliosManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(array_column(ChargeCategory::manual(), 'value'))],
            'description' => ['required', 'string', 'max:255'],
            'unit_amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_amount.regex' => 'Enter an amount with up to two decimal places.',
            'unit_amount.not_regex' => 'The amount must be greater than zero.',
            'service_date.before_or_equal' => 'Charges cannot be posted for future dates.',
        ];
    }

    public function category(): ChargeCategory
    {
        return ChargeCategory::from($this->string('category')->toString());
    }
}
