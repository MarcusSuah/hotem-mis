<?php

namespace App\Http\Requests\Admin\Operations;

use App\Enums\Permission;
use App\Models\HousekeepingTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Housekeepers may work their own (or unassigned) tasks; supervisors may work any task.
 */
class WorkTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var HousekeepingTask $task */
        $task = $this->route('task');
        $user = $this->user();

        return $user->can(Permission::HousekeepingManage->value)
            || ($user->can(Permission::HousekeepingPerform->value) && in_array($task->assigned_to, [null, $user->id], true));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
