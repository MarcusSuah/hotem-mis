<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\Permission;
use App\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::InventoryManage->value);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('sku')) {
            $this->merge(['sku' => strtoupper(trim((string) $this->input('sku')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var InventoryItem|null $item */
        $item = $this->route('item');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/', Rule::unique('inventory_items', 'sku')->ignore($item?->id)],
            'inventory_category_id' => ['nullable', 'required_without:new_category', 'integer', Rule::exists('inventory_categories', 'id')],
            'new_category' => ['nullable', 'string', 'max:100'],
            'preferred_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'unit' => ['required', 'string', 'max:16'],
            'reorder_level' => ['required', 'regex:/^\d{1,8}(\.\d{1,3})?$/'],
            'tracks_expiry' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'Use uppercase letters, numbers and dashes.',
            'inventory_category_id.required_without' => 'Choose a category or name a new one.',
            'reorder_level.regex' => 'Enter a quantity with up to three decimal places.',
        ];
    }
}
