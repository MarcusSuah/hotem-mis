<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\HousekeepingTaskType;
use App\Enums\LostFoundStatus;
use App\Enums\MaintenanceCategory;
use App\Enums\Permission;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Operations\AssignWorkRequest;
use App\Http\Requests\Admin\Operations\CompleteTaskRequest;
use App\Http\Requests\Admin\Operations\HousekeepingTaskRequest;
use App\Http\Requests\Admin\Operations\InspectTaskRequest;
use App\Http\Requests\Admin\Operations\LostFoundRequest;
use App\Http\Requests\Admin\Operations\MaintenanceRequestForm;
use App\Http\Requests\Admin\Operations\WorkTaskRequest;
use App\Models\HousekeepingTask;
use App\Models\LostFoundItem;
use App\Models\Room;
use App\Models\User;
use App\Services\Currency\CurrencyConverter;
use App\Services\Operations\HousekeepingService;
use App\Services\Operations\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $manages = $user->can(Permission::HousekeepingManage->value);
        abort_unless($manages || $user->can(Permission::HousekeepingPerform->value), 403);

        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(HousekeepingTaskStatus::class)],
        ]);
        $date = CarbonImmutable::parse($filters['date'] ?? CarbonImmutable::today());

        $tasks = HousekeepingTask::query()
            ->with(['room:id,number,floor,housekeeping_status', 'assignee:id,name'])
            ->where(fn ($query) => $query->whereDate('scheduled_for', $date)
                ->orWhere(fn ($open) => $open->whereDate('scheduled_for', '<', $date)->whereIn('status', HousekeepingTaskStatus::openValues())))
            ->when(! $manages, fn ($query) => $query->where(fn ($own) => $own->where('assigned_to', $user->id)->orWhereNull('assigned_to')))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('scheduled_for')
            ->orderBy('id')
            ->get()
            ->map(fn (HousekeepingTask $task) => [
                'id' => $task->id,
                'room' => $task->room->number,
                'floor' => $task->room->floor,
                'room_status' => $task->room->housekeeping_status->value,
                'type' => $task->type->value,
                'type_label' => $task->type->label(),
                'priority' => $task->priority->value,
                'status' => $task->status->value,
                'status_label' => $task->status->label(),
                'scheduled_for' => $task->scheduled_for->toDateString(),
                'assignee' => $task->assignee?->only(['id', 'name']),
                'checklist' => $task->checklist ?? [],
                'notes' => $task->notes,
                'can_work' => $manages || in_array($task->assigned_to, [null, $user->id], true),
            ]);

        return Inertia::render('admin/housekeeping/index', [
            'date' => $date->toDateString(),
            'filters' => ['status' => $filters['status'] ?? ''],
            'tasks' => $tasks,
            'rooms' => $manages ? Room::query()->inService()->orderBy('floor')->orderBy('number')->get(['id', 'number', 'floor', 'housekeeping_status'])
                ->map(fn (Room $room) => ['id' => $room->id, 'number' => $room->number, 'floor' => $room->floor, 'status' => $room->housekeeping_status->value]) : [],
            'staff' => $manages ? User::query()->active()->permission(Permission::HousekeepingPerform->value)->orderBy('name')->get(['id', 'name']) : [],
            'lostFound' => LostFoundItem::query()->with('room:id,number')->orderByDesc('found_at')->limit(50)->get()
                ->map(fn (LostFoundItem $item) => [
                    ...$item->only(['id', 'reference', 'description', 'location_found', 'storage_location', 'claimed_by_name', 'notes', 'room_id']),
                    'room' => $item->room?->number,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'found_at' => $item->found_at->toIso8601String(),
                ]),
            'options' => [
                'types' => HousekeepingTaskType::options(),
                'priorities' => TaskPriority::options(),
                'statuses' => HousekeepingTaskStatus::options(),
                'roomStatuses' => collect(HousekeepingStatus::cases())->map(fn ($case) => ['value' => $case->value, 'label' => $case->label()]),
                'lostFoundStatuses' => LostFoundStatus::options(),
                'maintenanceCategories' => MaintenanceCategory::options(),
            ],
            'can' => ['manage' => $manages, 'reportIssue' => $user->can(Permission::MaintenanceRequest->value)],
        ]);
    }

    public function store(HousekeepingTaskRequest $request, HousekeepingService $housekeeping): RedirectResponse
    {
        $assignee = $request->filled('assigned_to') ? User::query()->find($request->integer('assigned_to')) : null;

        $housekeeping->create(
            Room::query()->findOrFail($request->integer('room_id')),
            HousekeepingTaskType::from($request->string('type')->toString()),
            CarbonImmutable::parse($request->string('scheduled_for')->toString()),
            TaskPriority::from($request->string('priority')->toString()),
            $assignee,
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
            actor: $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return back();
    }

    public function assign(AssignWorkRequest $request, HousekeepingTask $task, HousekeepingService $housekeeping): RedirectResponse
    {
        $housekeeping->assign($task, $request->assignee(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task assignment updated.')]);

        return back();
    }

    public function start(WorkTaskRequest $request, HousekeepingTask $task, HousekeepingService $housekeeping): RedirectResponse
    {
        $housekeeping->start($task, $request->user());

        return back();
    }

    public function complete(CompleteTaskRequest $request, HousekeepingTask $task, HousekeepingService $housekeeping): RedirectResponse
    {
        $housekeeping->complete($task, $request->checklist(), $request->filled('notes') ? $request->string('notes')->toString() : null, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room :room marked clean.', ['room' => $task->room->number])]);

        return back();
    }

    public function inspect(InspectTaskRequest $request, HousekeepingTask $task, HousekeepingService $housekeeping): RedirectResponse
    {
        $passed = $request->boolean('passed');
        $housekeeping->inspect($task, $passed, $request->filled('notes') ? $request->string('notes')->toString() : null, $request->user());

        Inertia::flash('toast', ['type' => $passed ? 'success' : 'warning', 'message' => $passed ? __('Room ready for arrival.') : __('Task reopened for re-cleaning.')]);

        return back();
    }

    public function escalate(MaintenanceRequestForm $request, HousekeepingTask $task, MaintenanceService $maintenance, CurrencyConverter $converter): RedirectResponse
    {
        $maintenance->create([
            ...$request->requestData($converter->baseCurrency()->value),
            'room_id' => $task->room_id,
            'housekeeping_task_id' => $task->id,
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Issue reported to maintenance.')]);

        return back();
    }

    public function storeLostFound(LostFoundRequest $request): RedirectResponse
    {
        LostFoundItem::create([
            ...$request->safe()->except(['status']),
            'reference' => 'LF-'.Str::upper(Str::random(6)),
            'status' => $request->string('status')->toString(),
            'found_by' => $request->user()->id,
            'resolved_at' => $request->string('status')->toString() === LostFoundStatus::Stored->value ? null : now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item logged.')]);

        return back();
    }

    public function updateLostFound(LostFoundRequest $request, LostFoundItem $item): RedirectResponse
    {
        $item->fill([
            ...$request->safe()->except(['status']),
            'status' => $request->string('status')->toString(),
        ]);

        if ($item->isDirty('status')) {
            $item->resolved_at = $item->status === LostFoundStatus::Stored ? null : now();
        }

        $item->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item updated.')]);

        return back();
    }
}
