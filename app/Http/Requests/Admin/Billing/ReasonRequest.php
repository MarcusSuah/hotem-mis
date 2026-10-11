<?php

namespace App\Http\Requests\Admin\Billing;

use App\Enums\Permission;
use App\Models\FolioCharge;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A mandatory explanation for corrective actions (reversals, voids).
 */
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('charge') instanceof FolioCharge
            ? $this->user()->can(Permission::FoliosManage->value)
            : $this->user()->can(Permission::InvoicesCreate->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'reissue' => ['boolean'],
        ];
    }
}
