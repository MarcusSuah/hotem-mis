<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manual stock movements (issues, wastage, laundry, returns) and stock counts.
 */
class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::InventoryManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $quantity = ['required', 'regex:/^\d{1,8}(\.\d{1,3})?$/'];

        return [
            'type' => ['required', Rule::in(array_column(InventoryMovementType::manual(), 'value'))],
            'quantity' => [...$quantity, Rule::when($this->input('type') !== InventoryMovementType::Adjustment->value, ['not_regex:/^0+(\.0+)?$/'])],
            'notes' => [Rule::requiredIf($this->input('type') === InventoryMovementType::Adjustment->value), 'nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.regex' => 'Enter a quantity with up to three decimal places.',
            'quantity.not_regex' => 'The quantity must be greater than zero.',
            'notes.required' => 'Explain the reason for the count adjustment.',
        ];
    }

    public function movementType(): InventoryMovementType
    {
        return InventoryMovementType::from($this->string('type')->toString());
    }
}
