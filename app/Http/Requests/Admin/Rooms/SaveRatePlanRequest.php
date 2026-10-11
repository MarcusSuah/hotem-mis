<?php

namespace App\Http\Requests\Admin\Rooms;

use App\Enums\Permission;
use App\Models\RatePlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::RatePlansManage->value);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code')) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var RatePlan|null $plan */
        $plan = $this->route('rate_plan');

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9-]+$/', Rule::unique('rate_plans', 'code')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'cancellation_policy_id' => ['required', 'integer', Rule::exists('cancellation_policies', 'id')],
            'is_refundable' => ['boolean'],
            'adjustment_percent' => ['required', 'numeric', 'between:-90,200', 'decimal:0,2'],
            'includes_breakfast' => ['boolean'],
            'min_nights' => ['required', 'integer', 'min:1', 'max:60'],
            'deposit_percent' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Use uppercase letters, numbers and dashes only.',
            'adjustment_percent.between' => 'The adjustment must be between -90% and +200%.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function planAttributes(): array
    {
        return [
            ...$this->safe()->only(['name', 'code', 'description', 'cancellation_policy_id', 'adjustment_percent', 'min_nights', 'deposit_percent', 'display_order']),
            'is_refundable' => $this->boolean('is_refundable'),
            'includes_breakfast' => $this->boolean('includes_breakfast'),
            'is_public' => $this->boolean('is_public'),
            'is_active' => $this->boolean('is_active'),
            'is_default' => $this->boolean('is_default'),
        ];
    }
}
