<?php

namespace App\Http\Requests\Admin\Outlets;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\Settlement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settling an outlet order or completing a guest service.
 */
class SettleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::OrdersManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'settlement' => ['required', Rule::enum(Settlement::class)],
            'payment_method' => ['nullable', 'required_if:settlement,paid_at_outlet', Rule::enum(PaymentMethod::class)],
            'payment_reference' => ['nullable', 'string', 'max:128'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['payment_method.required_if' => 'Choose how the guest paid.'];
    }

    public function settlement(): Settlement
    {
        return Settlement::from($this->string('settlement')->toString());
    }

    public function paymentMethod(): ?PaymentMethod
    {
        return PaymentMethod::tryFrom($this->string('payment_method')->toString());
    }

    public function paymentReference(): ?string
    {
        return $this->filled('payment_reference') ? $this->string('payment_reference')->toString() : null;
    }
}
