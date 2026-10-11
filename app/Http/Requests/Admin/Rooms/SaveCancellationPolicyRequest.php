<?php

namespace App\Http\Requests\Admin\Rooms;

use App\Enums\CancellationPenalty;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCancellationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::RatePlansManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'free_cancellation_hours' => ['required', 'integer', 'min:0', 'max:2160'],
            'penalty_type' => ['required', Rule::enum(CancellationPenalty::class)],
            'penalty_percent' => [
                Rule::requiredIf($this->input('penalty_type') === CancellationPenalty::Percentage->value),
                'nullable', 'numeric', 'between:0,100', 'decimal:0,2',
            ],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function policyAttributes(): array
    {
        return [
            ...$this->safe()->only(['name', 'description', 'free_cancellation_hours', 'penalty_type']),
            'penalty_percent' => $this->input('penalty_type') === CancellationPenalty::Percentage->value
                ? (string) $this->input('penalty_percent')
                : '0',
            'is_default' => $this->boolean('is_default'),
        ];
    }
}
