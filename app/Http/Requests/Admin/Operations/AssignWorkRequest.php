<?php

namespace App\Http\Requests\Admin\Operations;

use App\Enums\Permission;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assigning housekeeping tasks or maintenance requests to a team member.
 */
class AssignWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('maintenance') instanceof MaintenanceRequest
            ? $this->user()->can(Permission::MaintenanceManage->value)
            : $this->user()->can(Permission::HousekeepingManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')]];
    }

    public function assignee(): ?User
    {
        return $this->filled('assigned_to') ? User::query()->find($this->integer('assigned_to')) : null;
    }
}
