<?php

namespace App\Http\Requests\Admin\Outlets;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceOrderRequest extends FormRequest
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
            'additional_service_id' => ['required', 'integer', Rule::exists('additional_services', 'id')->whereNull('deleted_at')],
            'reservation_id' => ['nullable', 'integer', Rule::exists('reservations', 'id')],
            'customer_name' => ['nullable', 'required_without:reservation_id', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'scheduled_for' => ['nullable', 'date', 'after_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['customer_name.required_without' => 'Choose an in-house reservation or enter the customer name.'];
    }
}
