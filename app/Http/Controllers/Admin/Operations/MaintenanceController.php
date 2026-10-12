<?php

namespace App\Http\Controllers\Admin\Operations;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\Permission;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Operations\AssignWorkRequest;
use App\Http\Requests\Admin\Operations\MaintenanceRequestForm;
use App\Http\Requests\Admin\Operations\MaintenanceScheduleRequest;
use App\Http\Requests\Admin\Operations\MaintenanceWorkRequest;
use App\Models\InventoryItem;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceComment;
use App\Models\MaintenancePart;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\User;
use App\Services\Currency\CurrencyConverter;
use App\Services\Operations\MaintenanceService;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->ensureCanView($user);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,'.implode(',', array_column(MaintenanceStatus::cases(), 'value'))],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'category' => ['nullable', Rule::enum(MaintenanceCategory::class)],
            'mine' => ['nullable', 'boolean'],
        ]);
        $status = $filters['status'] ?? 'active';

        $requests = MaintenanceRequest::query()
            ->with(['room:id,number', 'assignee:id,name', 'roomBlock'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($inner) => $inner
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('room', fn ($rooms) => $rooms->where('number', $search))))
            ->when($status === 'active', fn ($query) => $query->whereIn('status', MaintenanceStatus::activeValues()))
            ->when($status !== 'active', fn ($query) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when((bool) ($filters['mine'] ?? false), fn ($query) => $query->where('assigned_to', $user->id))
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MaintenanceRequest $maintenance) => [
                'reference' => $maintenance->reference,
                'title' => $maintenance->title,
                'room' => $maintenance->room?->number,
                'location' => $maintenance->location,
                'category_label' => $maintenance->category->label(),
                'priority' => $maintenance->priority->value,
                'status' => $maintenance->status->value,
                'status_label' => $maintenance->status->label(),
                'assignee' => $maintenance->assignee?->name,
                'blocks_room' => $maintenance->roomBlock !== null && $maintenance->roomBlock->released_at === null,
                'created_at' => $maintenance->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/maintenance/index', [
            'requests' => $requests,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $status,
                'priority' => $filters['priority'] ?? '',
                'category' => $filters['category'] ?? '',
                'mine' => (bool) ($filters['mine'] ?? false) ? '1' : '',
            ],
            'schedules' => $user->can(Permission::MaintenanceManage->value)
                ? MaintenanceSchedule::query()->with(['room:id,number', 'assignee:id,name'])->orderBy('next_due_on')->get()
                    ->map(fn (MaintenanceSchedule $schedule) => [
                        ...$schedule->only(['id', 'title', 'description', 'room_id', 'location', 'frequency_days', 'assigned_to', 'is_active']),
                        'category' => $schedule->category->value,
                        'priority' => $schedule->priority->value,
                        'next_due_on' => $schedule->next_due_on->toDateString(),
                        'room' => $schedule->room?->number,
                        'assignee' => $schedule->assignee?->name,
                    ])
                : [],
            ...$this->formOptions(),
            'can' => [
                'report' => $user->can(Permission::MaintenanceRequest->value) || $user->can(Permission::MaintenanceManage->value),
                'manage' => $user->can(Permission::MaintenanceManage->value),
            ],
        ]);
    }

    public function store(MaintenanceRequestForm $request, MaintenanceService $maintenance, CurrencyConverter $converter): RedirectResponse
    {
        $assignee = $request->filled('assigned_to') && $request->user()->can(Permission::MaintenanceManage->value)
            ? User::query()->find($request->integer('assigned_to'))
            : null;

        $created = $maintenance->create($request->requestData($converter->baseCurrency()->value), $request->user(), $assignee, $request->blockPeriod());

        foreach ($request->photos() as $photo) {
            $maintenance->attach($created, $photo, $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Maintenance request :reference logged.', ['reference' => $created->reference])]);

        return to_route('admin.maintenance.show', $created);
    }

    public function show(Request $request, MaintenanceRequest $maintenance): Response
    {
        $user = $request->user();
        $this->ensureCanView($user);

        $maintenance->load(['room', 'reporter:id,name', 'assignee:id,name', 'roomBlock', 'comments.user:id,name', 'attachments', 'parts.item:id,name,unit', 'schedule:id,title']);
        $canWork = $user->can(Permission::MaintenanceManage->value)
            || ($user->can(Permission::MaintenancePerform->value) && in_array($maintenance->assigned_to, [null, $user->id], true));

        return Inertia::render('admin/maintenance/show', [
            'request' => [
                'reference' => $maintenance->reference,
                'title' => $maintenance->title,
                'description' => $maintenance->description,
                'room' => $maintenance->room?->number,
                'location' => $maintenance->location,
                'category_label' => $maintenance->category->label(),
                'priority' => $maintenance->priority->value,
                'status' => $maintenance->status->value,
                'status_label' => $maintenance->status->label(),
                'reporter' => $maintenance->reporter?->name,
                'assignee' => $maintenance->assignee?->only(['id', 'name']),
                'schedule' => $maintenance->schedule?->title,
                'estimated_cost' => $maintenance->estimated_cost_minor === null ? null : Money::ofMinor($maintenance->estimated_cost_minor, $maintenance->currency),
                'actual_cost' => $maintenance->actual_cost_minor === null ? null : Money::ofMinor($maintenance->actual_cost_minor, $maintenance->currency),
                'block' => $maintenance->roomBlock ? [
                    'start_date' => $maintenance->roomBlock->start_date->toDateString(),
                    'end_date' => $maintenance->roomBlock->end_date->toDateString(),
                    'released' => $maintenance->roomBlock->released_at !== null,
                ] : null,
                'created_at' => $maintenance->created_at?->toIso8601String(),
                'started_at' => $maintenance->started_at?->toIso8601String(),
                'completed_at' => $maintenance->completed_at?->toIso8601String(),
                'comments' => $maintenance->comments->map(fn (MaintenanceComment $comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'event' => $comment->event,
                    'user' => $comment->user?->name,
                    'created_at' => $comment->created_at->toIso8601String(),
                ]),
                'attachments' => $maintenance->attachments->map(fn (MaintenanceAttachment $attachment) => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'url' => route('admin.maintenance.attachments.show', [$maintenance, $attachment], false),
                ]),
                'parts' => $maintenance->parts->map(fn (MaintenancePart $part) => [
                    'id' => $part->id,
                    'description' => $part->description,
                    'quantity' => (string) $part->quantity,
                    'unit' => $part->item?->unit,
                    'unit_cost' => Money::ofMinor($part->unit_cost_minor, $maintenance->currency),
                    'from_stock' => $part->inventory_item_id !== null,
                ]),
            ],
            ...$this->formOptions(),
            'stockItems' => $canWork ? InventoryItem::query()->where('is_active', true)->where('quantity', '>', 0)->orderBy('name')->get(['id', 'name', 'unit', 'quantity']) : [],
            'can' => [
                'work' => $canWork && $maintenance->status->isActive(),
                'assign' => $user->can(Permission::MaintenanceManage->value) && $maintenance->status->isActive(),
                'close' => $user->can(Permission::MaintenanceManage->value),
            ],
        ]);
    }

    public function assign(AssignWorkRequest $request, MaintenanceRequest $maintenance, MaintenanceService $service): RedirectResponse
    {
        $service->assign($maintenance, $request->assignee(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment updated.')]);

        return back();
    }

    public function status(MaintenanceWorkRequest $request, MaintenanceRequest $maintenance, MaintenanceService $service): RedirectResponse
    {
        $status = MaintenanceStatus::from($request->string('status')->toString());

        if (in_array($status, [MaintenanceStatus::Closed, MaintenanceStatus::Cancelled], true)) {
            Gate::authorize(Permission::MaintenanceManage->value);
        }

        $service->changeStatus(
            $maintenance,
            $status,
            $request->user(),
            $request->filled('note') ? $request->string('note')->toString() : null,
            $request->filled('actual_cost') ? Money::of($request->string('actual_cost')->toString(), $maintenance->currency) : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status changed to :status.', ['status' => $status->label()])]);

        return back();
    }

    public function comment(MaintenanceWorkRequest $request, MaintenanceRequest $maintenance, MaintenanceService $service): RedirectResponse
    {
        $service->comment($maintenance, $request->string('body')->toString(), $request->user());

        return back();
    }

    public function part(MaintenanceWorkRequest $request, MaintenanceRequest $maintenance, MaintenanceService $service): RedirectResponse
    {
        $item = $request->filled('inventory_item_id') ? InventoryItem::query()->findOrFail($request->integer('inventory_item_id')) : null;

        $service->addPart(
            $maintenance,
            $request->string('description')->toString(),
            $request->string('quantity')->toString(),
            $item,
            $request->filled('unit_cost') ? Money::of($request->string('unit_cost')->toString(), $maintenance->currency) : null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Part recorded.')]);

        return back();
    }

    public function attachments(MaintenanceWorkRequest $request, MaintenanceRequest $maintenance, MaintenanceService $service): RedirectResponse
    {
        foreach ((array) $request->file('photos', []) as $photo) {
            $service->attach($maintenance, $photo, $request->user());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photos uploaded.')]);

        return back();
    }

    /**
     * Stream a private attachment to authorised staff only.
     */
    public function attachment(Request $request, MaintenanceRequest $maintenance, MaintenanceAttachment $attachment, MaintenanceService $service): BinaryFileResponse
    {
        $this->ensureCanView($request->user());
        abort_unless($attachment->maintenance_request_id === $maintenance->id, 404);

        return response()->file($service->attachmentPath($attachment), [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($attachment->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeSchedule(MaintenanceScheduleRequest $request): RedirectResponse
    {
        MaintenanceSchedule::create($request->scheduleAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preventive schedule created.')]);

        return back();
    }

    public function updateSchedule(MaintenanceScheduleRequest $request, MaintenanceSchedule $schedule): RedirectResponse
    {
        $schedule->update($request->scheduleAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preventive schedule updated.')]);

        return back();
    }

    private function ensureCanView(User $user): void
    {
        abort_unless(
            $user->can(Permission::MaintenanceManage->value)
            || $user->can(Permission::MaintenancePerform->value)
            || $user->can(Permission::MaintenanceRequest->value),
            403,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'options' => [
                'categories' => MaintenanceCategory::options(),
                'priorities' => TaskPriority::options(),
                'statuses' => MaintenanceStatus::options(),
            ],
            'rooms' => Room::query()->orderBy('floor')->orderBy('number')->get(['id', 'number']),
            'technicians' => User::query()->active()->permission(Permission::MaintenancePerform->value)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
