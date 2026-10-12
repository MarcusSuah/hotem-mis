<?php

namespace App\Http\Controllers\Admin\Staff;

use App\Enums\AttendanceStatus;
use App\Enums\EmploymentType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Staff\StaffRequest;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff records, rota, attendance and leave. Employment records are kept
 * separate from login accounts; emergency contacts are encrypted.
 */
class StaffController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        Gate::authorize(Permission::StaffManage->value);

        $filters = $request->validate([
            'week' => ['nullable', 'date'],
            'department' => ['nullable', 'integer'],
        ]);
        $weekStart = CarbonImmutable::parse($filters['week'] ?? CarbonImmutable::today())->startOfWeek();
        $weekEnd = $weekStart->addDays(6);

        $staff = StaffProfile::query()
            ->with(['department:id,name', 'user:id,name,email'])
            ->when($filters['department'] ?? null, fn ($query, int $department) => $query->where('department_id', $department))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('admin/staff/index', [
            'week' => $weekStart->toDateString(),
            'days' => collect(range(0, 6))->map(fn (int $offset) => $weekStart->addDays($offset)->toDateString()),
            'filters' => ['department' => isset($filters['department']) ? (string) $filters['department'] : ''],
            'staff' => $staff->map(fn (StaffProfile $profile) => [
                ...$profile->only(['id', 'employee_number', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'department_id', 'user_id', 'is_active']),
                'name' => $profile->full_name,
                'department' => $profile->department->name,
                'employment_type' => $profile->employment_type->value,
                'employment_label' => $profile->employment_type->label(),
                'hire_date' => $profile->hire_date->toDateString(),
                'emergency_contact_name' => $profile->emergency_contact_name,
                'emergency_contact_phone' => $profile->emergency_contact_phone,
                'account' => $profile->user?->only(['id', 'name', 'email']),
            ]),
            'shifts' => Shift::query()
                ->whereBetween('shift_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->whereIn('staff_profile_id', $staff->modelKeys())
                ->orderBy('starts_at')
                ->get()
                ->map(fn (Shift $shift) => [
                    'id' => $shift->id,
                    'staff_profile_id' => $shift->staff_profile_id,
                    'date' => $shift->shift_date->toDateString(),
                    'starts_at' => substr((string) $shift->starts_at, 0, 5),
                    'ends_at' => substr((string) $shift->ends_at, 0, 5),
                    'role' => $shift->role,
                ]),
            'attendance' => AttendanceRecord::query()
                ->whereBetween('work_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->get()
                ->map(fn (AttendanceRecord $record) => [
                    'staff_profile_id' => $record->staff_profile_id,
                    'date' => $record->work_date->toDateString(),
                    'status' => $record->status->value,
                    'status_label' => $record->status->label(),
                ]),
            'leave' => LeaveRequest::query()->with('staff:id,first_name,last_name')
                ->where(fn ($query) => $query->where('status', LeaveStatus::Pending->value)->orWhere('end_date', '>=', $weekStart->toDateString()))
                ->orderBy('start_date')
                ->get()
                ->map(fn (LeaveRequest $leave) => [
                    'id' => $leave->id,
                    'staff' => $leave->staff->full_name,
                    'type_label' => $leave->type->label(),
                    'start_date' => $leave->start_date->toDateString(),
                    'end_date' => $leave->end_date->toDateString(),
                    'reason' => $leave->reason,
                    'status' => $leave->status->value,
                    'status_label' => $leave->status->label(),
                    'decision_note' => $leave->decision_note,
                ]),
            'departments' => Department::query()->withCount('staff')->orderBy('name')->get(['id', 'name', 'description']),
            'accounts' => User::query()->active()->whereDoesntHave('staffProfile')->orderBy('name')->get(['id', 'name', 'email']),
            'options' => [
                'employmentTypes' => EmploymentType::options(),
                'attendance' => AttendanceStatus::options(),
                'leaveTypes' => LeaveType::options(),
            ],
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $profile = StaffProfile::create([...$request->safe()->except(['is_active']), 'is_active' => $request->boolean('is_active')]);
        $this->audit->record('staff.created', $profile, newValues: ['employee_number' => $profile->employee_number]);

        return $this->saved(__('Staff record created.'));
    }

    public function update(StaffRequest $request, StaffProfile $staff): RedirectResponse
    {
        $staff->update([...$request->safe()->except(['is_active']), 'is_active' => $request->boolean('is_active')]);
        $this->audit->recordChanges('staff.updated', $staff);

        return $this->saved(__('Staff record updated.'));
    }

    public function storeDepartment(StaffRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return $this->saved(__('Department created.'));
    }

    public function storeShift(StaffRequest $request): RedirectResponse
    {
        Shift::create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->saved(__('Shift added to the rota.'));
    }

    public function destroyShift(Shift $shift): RedirectResponse
    {
        Gate::authorize(Permission::StaffManage->value);

        $shift->delete();

        return $this->saved(__('Shift removed.'));
    }

    public function storeAttendance(StaffRequest $request): RedirectResponse
    {
        $date = CarbonImmutable::parse($request->string('work_date')->toString());
        $time = fn (string $field): ?CarbonImmutable => $request->filled($field)
            ? $date->setTimeFromTimeString($request->string($field)->toString())
            : null;

        AttendanceRecord::query()->updateOrCreate(
            ['staff_profile_id' => $request->integer('staff_profile_id'), 'work_date' => $date->toDateString()],
            [
                'status' => $request->string('status')->toString(),
                'clock_in_at' => $time('clock_in_at'),
                'clock_out_at' => $time('clock_out_at'),
                'notes' => $request->filled('notes') ? $request->string('notes')->toString() : null,
                'recorded_by' => $request->user()->id,
            ],
        );

        return $this->saved(__('Attendance recorded.'));
    }

    public function storeLeave(StaffRequest $request): RedirectResponse
    {
        LeaveRequest::create([...$request->validated(), 'status' => LeaveStatus::Pending]);

        return $this->saved(__('Leave request recorded.'));
    }

    public function decideLeave(StaffRequest $request, LeaveRequest $leave): RedirectResponse
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'This request has already been decided.']);
        }

        $leave->forceFill([
            'status' => LeaveStatus::from($request->string('status')->toString()),
            'decision_note' => $request->filled('decision_note') ? $request->string('decision_note')->toString() : null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ])->save();

        $this->audit->record('staff.leave_decided', $leave, newValues: ['status' => $leave->status->value]);

        return $this->saved(__('Leave request :status.', ['status' => strtolower($leave->status->label())]));
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
