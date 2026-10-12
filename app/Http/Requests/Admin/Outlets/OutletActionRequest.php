<?php

namespace App\Http\Requests\Admin\Outlets;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Kitchen progress updates and order cancellations.
 */
class OutletActionRequest extends FormRequest
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
        return $this->route()?->getName() === 'admin.outlets.orders.advance'
            ? ['status' => ['required', Rule::in([OrderStatus::Preparing->value, OrderStatus::Served->value])]]
            : ['reason' => ['required', 'string', 'min:3', 'max:255']];
    }
}
