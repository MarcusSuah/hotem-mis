<?php

namespace App\Http\Requests\Admin\Rooms;

use App\Enums\Permission;
use App\Services\Currency\CurrencyConverter;
use App\Support\Money\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSeasonalRateRequest extends FormRequest
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
        $money = ['regex:/^\d{1,9}(\.\d{1,2})?$/'];

        return [
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'rate' => ['required', ...$money],
            'weekend_rate' => ['nullable', ...$money],
            'priority' => ['required', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.regex' => 'Enter an amount with up to two decimal places.',
            'weekend_rate.regex' => 'Enter an amount with up to two decimal places.',
            'end_date.after_or_equal' => 'The season must end on or after its start date.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function seasonAttributes(CurrencyConverter $converter): array
    {
        $currency = $converter->baseCurrency();

        return [
            ...$this->safe()->only(['room_type_id', 'name', 'start_date', 'end_date', 'priority']),
            'rate_minor' => Money::of($this->string('rate')->toString(), $currency)->minor,
            'weekend_rate_minor' => $this->filled('weekend_rate') ? Money::of($this->string('weekend_rate')->toString(), $currency)->minor : null,
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
