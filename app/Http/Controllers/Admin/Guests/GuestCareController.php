<?php

namespace App\Http\Controllers\Admin\Guests;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Guests\GuestCareRequest;
use App\Models\Complaint;
use App\Models\Guest;
use App\Models\GuestPreference;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Guests\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Complaint handling, guest preferences and loyalty adjustments.
 */
class GuestCareController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function complaints(Request $request): Response
    {
        Gate::authorize(Permission::GuestsView->value);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(ComplaintStatus::class)]]);

        return Inertia::render('admin/guests/complaints', [
            'complaints' => Complaint::query()
                ->with(['guest:id,first_name,last_name', 'reservation:id,reference', 'assignee:id,name'])
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->orderByRaw("FIELD(status, 'open', 'in_progress', 'resolved', 'closed')")
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Complaint $complaint) => [
                    ...$complaint->only(['reference', 'subject', 'description', 'resolution', 'assigned_to']),
                    'guest' => $complaint->guest?->only(['id', 'full_name']),
                    'reservation' => $complaint->reservation?->reference,
                    'category_label' => $complaint->category->label(),
                    'severity' => $complaint->severity->value,
                    'status' => $complaint->status->value,
                    'status_label' => $complaint->status->label(),
                    'assignee' => $complaint->assignee?->name,
                    'created_at' => $complaint->created_at?->toIso8601String(),
                    'resolved_at' => $complaint->resolved_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $filters['status'] ?? ''],
            'options' => [
                'categories' => ComplaintCategory::options(),
                'severities' => TaskPriority::options(),
                'statuses' => ComplaintStatus::options(),
            ],
            'staff' => User::query()->active()->permission(Permission::GuestsUpdate->value)->orderBy('name')->get(['id', 'name']),
            'recentStays' => Reservation::query()
                ->whereIn('status', [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value])
                ->where('check_out_date', '>=', now()->subDays(30)->toDateString())
                ->whereNotNull('guest_id')
                ->orderByDesc('check_in_date')
                ->limit(100)
                ->get(['id', 'reference', 'guest_id', 'contact_name'])
                ->map(fn (Reservation $reservation) => ['id' => $reservation->id, 'guest_id' => $reservation->guest_id, 'label' => "{$reservation->reference} — {$reservation->contact_name}"]),
            'can' => ['manage' => $request->user()->can(Permission::GuestsUpdate->value)],
        ]);
    }

    public function storeComplaint(GuestCareRequest $request): RedirectResponse
    {
        $complaint = Complaint::create([
            ...$request->validated(),
            'reference' => 'CMP-'.Str::upper(Str::random(6)),
            'status' => ComplaintStatus::Open,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->record('complaint.created', $complaint, newValues: ['subject' => $complaint->subject]);

        return $this->saved(__('Complaint :reference logged.', ['reference' => $complaint->reference]));
    }

    public function updateComplaint(GuestCareRequest $request, Complaint $complaint): RedirectResponse
    {
        $status = ComplaintStatus::from($request->string('status')->toString());

        $complaint->fill([
            'status' => $status,
            'assigned_to' => $request->filled('assigned_to') ? $request->integer('assigned_to') : null,
            'resolution' => $request->filled('resolution') ? $request->string('resolution')->toString() : $complaint->resolution,
        ]);

        if (in_array($status, [ComplaintStatus::Resolved, ComplaintStatus::Closed], true) && $complaint->resolved_at === null) {
            $complaint->resolved_at = now();
        }

        $complaint->save();
        $this->audit->recordChanges('complaint.updated', $complaint);

        return $this->saved(__('Complaint updated.'));
    }

    public function storePreference(GuestCareRequest $request, Guest $guest): RedirectResponse
    {
        $guest->preferences()->firstOrCreate($request->validated());

        return $this->saved(__('Preference saved.'));
    }

    public function destroyPreference(Guest $guest, GuestPreference $preference): RedirectResponse
    {
        Gate::authorize(Permission::GuestsUpdate->value);
        abort_unless($preference->guest_id === $guest->id, 404);

        $preference->delete();

        return $this->saved(__('Preference removed.'));
    }

    public function adjustLoyalty(GuestCareRequest $request, Guest $guest, LoyaltyService $loyalty): RedirectResponse
    {
        $loyalty->adjust($guest, $request->integer('points'), $request->string('reason')->toString(), $request->user());

        return $this->saved(__('Loyalty points adjusted.'));
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
