<?php

namespace App\Http\Requests\Admin\FrontDesk;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RunNightAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::NightAuditRun->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['mark_no_shows' => ['boolean']];
    }
}
