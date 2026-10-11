<?php

namespace App\Http\Requests\Admin\FrontDesk;

use App\Enums\Permission;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExtendStayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::BookingsUpdate->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['check_out_date' => ['required', 'date', 'after:today']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['check_out_date.after' => 'The new departure date must be in the future.'];
    }

    public function checkOutDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('check_out_date')->toString())->startOfDay();
    }
}
