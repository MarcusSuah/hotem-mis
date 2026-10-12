<?php

namespace App\Http\Requests\Admin\Staff;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\Permission;
use App\Models\StaffProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Staff records, departments, shifts, attendance and leave. The route
 * decides which rules apply; all require the staff.manage permission.
 */
class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::StaffManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $staff = ['required', 'integer', Rule::exists('staff_profiles', 'id')->whereNull('deleted_at')];

        return match ($this->route()?->getName()) {
            'admin.staff.departments.store' => [
                'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')],
                'description' => ['nullable', 'string', 'max:255'],
            ],
            'admin.staff.shifts.store' => [
                'staff_profile_id' => $staff,
                'shift_date' => ['required', 'date'],
                'starts_at' => ['required', 'date_format:H:i'],
                'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
                'role' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:255'],
            ],
            'admin.staff.attendance.store' => [
                'staff_profile_id' => $staff,
                'work_date' => ['required', 'date', 'before_or_equal:today'],
                'status' => ['required', Rule::enum(AttendanceStatus::class)],
                'clock_in_at' => ['nullable', 'date_format:H:i'],
                'clock_out_at' => ['nullable', 'date_format:H:i', 'after:clock_in_at'],
                'notes' => ['nullable', 'string', 'max:255'],
            ],
            'admin.staff.leave.store' => [
                'staff_profile_id' => $staff,
                'type' => ['required', Rule::enum(LeaveType::class)],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'reason' => ['nullable', 'string', 'max:500'],
            ],
            'admin.staff.leave.decide' => [
                'status' => ['required', Rule::in([LeaveStatus::Approved->value, LeaveStatus::Rejected->value])],
                'decision_note' => ['nullable', 'required_if:status,rejected', 'string', 'max:500'],
            ],
            default => $this->profileRules(),
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'clock_out_at.after' => 'Clock-out must be after clock-in.',
            'end_date.after_or_equal' => 'Leave must end on or after its first day.',
            'decision_note.required_if' => 'Explain why the request is rejected.',
            'user_id.unique' => 'This login account already belongs to another staff record.',
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    private function profileRules(): array
    {
        /** @var StaffProfile|null $profile */
        $profile = $this->route('staff');

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'employee_number' => ['required', 'string', 'max:32', Rule::unique('staff_profiles', 'employee_number')->ignore($profile?->id)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'job_title' => ['required', 'string', 'max:150'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'hire_date' => ['required', 'date', 'before_or_equal:today'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('staff_profiles', 'user_id')->ignore($profile?->id)],
            'is_active' => ['boolean'],
        ];
    }
}
