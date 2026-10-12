<?php

namespace App\Http\Requests\Admin\Operations;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InspectTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::HousekeepingManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'passed' => ['required', 'boolean'],
            'notes' => ['nullable', 'required_if:passed,false', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['notes.required_if' => 'Explain what needs to be redone.'];
    }
}
