<?php

namespace App\Http\Requests\Admin\Operations;

use App\Enums\MaintenanceStatus;
use App\Enums\Permission;
use App\Models\MaintenanceRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Work updates on a maintenance request: status changes, comments, parts and
 * photos. Technicians may update requests assigned to them (or unassigned);
 * maintenance managers may update any request.
 */
class MaintenanceWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var MaintenanceRequest $request */
        $request = $this->route('maintenance');
        $user = $this->user();

        return $user->can(Permission::MaintenanceManage->value)
            || ($user->can(Permission::MaintenancePerform->value) && in_array($request->assigned_to, [null, $user->id], true));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.maintenance.status' => [
                'status' => ['required', Rule::enum(MaintenanceStatus::class)],
                'note' => ['nullable', 'string', 'max:1000'],
                'actual_cost' => ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            ],
            'admin.maintenance.comments' => [
                'body' => ['required', 'string', 'max:2000'],
            ],
            'admin.maintenance.parts' => [
                'inventory_item_id' => ['nullable', 'integer', Rule::exists('inventory_items', 'id')->whereNull('deleted_at')],
                'description' => ['nullable', 'required_without:inventory_item_id', 'string', 'max:255'],
                'quantity' => ['required', 'regex:/^\d{1,8}(\.\d{1,3})?$/', 'not_regex:/^0+(\.0+)?$/'],
                'unit_cost' => ['nullable', 'required_without:inventory_item_id', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            ],
            'admin.maintenance.attachments' => [
                'photos' => ['required', 'array', 'max:5'],
                'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('hms.uploads.max_image_kb')],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required_without' => 'Describe the part, or pick it from stock.',
            'unit_cost.required_without' => 'Enter the unit cost for parts not taken from stock.',
            'quantity.regex' => 'Enter a quantity with up to three decimal places.',
            'quantity.not_regex' => 'The quantity must be greater than zero.',
            'actual_cost.regex' => 'Enter an amount with up to two decimal places.',
        ];
    }
}
