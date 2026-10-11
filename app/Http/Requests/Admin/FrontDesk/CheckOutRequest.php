<?php

namespace App\Http\Requests\Admin\FrontDesk;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::CheckoutManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'late_check_out_fee' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'settle_later' => ['boolean'],
            'settle_later_reason' => ['nullable', 'required_if_accepted:settle_later', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'late_check_out_fee.regex' => 'Enter an amount with up to two decimal places.',
            'settle_later_reason.required_if_accepted' => 'Explain how and when the balance will be settled.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('settle_later') && ! $this->user()->can(Permission::FoliosOverride->value)) {
                    $validator->errors()->add('settle_later', 'You are not allowed to check out guests with an unpaid balance.');
                }
            },
        ];
    }
}
