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
        return [
            'no_shows' => ['array', 'max:200'],
            'no_shows.*' => ['string', 'distinct', 'max:32'],
        ];
    }

    /**
     * @return list<string>
     */
    public function noShowReferences(): array
    {
        return array_values(array_map('strval', (array) $this->validated('no_shows', [])));
    }
}
