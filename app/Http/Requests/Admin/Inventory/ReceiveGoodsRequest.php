<?php

namespace App\Http\Requests\Admin\Inventory;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiveGoodsRequest extends FormRequest
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
            'received' => ['required', 'array'],
            'received.*.quantity' => ['nullable', 'regex:/^\d{1,8}(\.\d{1,3})?$/'],
            'received.*.expires_on' => ['nullable', 'date', 'after:today'],
        ];
    }

    /**
     * @return array<int, array{quantity: string, expires_on: string|null}>
     */
    public function receipts(): array
    {
        $receipts = [];

        foreach ((array) $this->validated('received') as $lineId => $line) {
            if (filled($line['quantity'] ?? null)) {
                $receipts[(int) $lineId] = ['quantity' => (string) $line['quantity'], 'expires_on' => $line['expires_on'] ?? null];
            }
        }

        return $receipts;
    }
}
