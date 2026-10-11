<?php

namespace App\Http\Requests\Admin\Reservations;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('reservation'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'waive_fee' => ['boolean'],
        ];
    }

    /**
     * Waiving a contractual cancellation fee is a financial decision.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->boolean('waive_fee') && ! $this->user()->can(Permission::PaymentsRefund->value)) {
                    $validator->errors()->add('waive_fee', 'You are not allowed to waive cancellation fees.');
                }
            },
        ];
    }
}
