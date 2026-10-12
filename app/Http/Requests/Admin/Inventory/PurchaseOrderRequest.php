<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\Permission;
use App\Support\Money\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
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
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'expected_on' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'regex:/^\d{1,8}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
            'lines.*.unit_cost' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one item.',
            'lines.*.inventory_item_id.distinct' => 'Each item can only appear once per order.',
            'lines.*.quantity.regex' => 'Enter a quantity with up to three decimal places.',
            'lines.*.quantity.not_regex' => 'Quantities must be greater than zero.',
            'lines.*.unit_cost.regex' => 'Enter a unit cost with up to two decimal places.',
        ];
    }

    /**
     * @return list<array{inventory_item_id: int, quantity: string, unit_cost: Money}>
     */
    public function lines(string $currency): array
    {
        return array_values(array_map(fn (array $line): array => [
            'inventory_item_id' => (int) $line['inventory_item_id'],
            'quantity' => (string) $line['quantity'],
            'unit_cost' => Money::of((string) $line['unit_cost'], $currency),
        ], (array) $this->validated('lines')));
    }
}
