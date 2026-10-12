<?php

namespace App\Http\Requests\Admin\Outlets;

use App\Enums\OrderType;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutletOrderRequest extends FormRequest
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
            'type' => ['required', Rule::enum(OrderType::class)],
            'reservation_id' => ['nullable', 'integer', Rule::exists('reservations', 'id')],
            'table_or_room' => ['nullable', 'string', 'max:32'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['lines.required' => 'Add at least one item to the order.'];
    }

    /**
     * @return list<array{menu_item_id: int, quantity: int, notes: string|null}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $line): array => [
            'menu_item_id' => (int) $line['menu_item_id'],
            'quantity' => (int) $line['quantity'],
            'notes' => isset($line['notes']) && $line['notes'] !== '' ? (string) $line['notes'] : null,
        ], (array) $this->validated('lines')));
    }
}
