<?php

namespace App\Http\Controllers\Admin\Guests;

use App\Enums\Currency;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Guests\SaveGuestRequest;
use App\Http\Resources\ReservationSummaryResource;
use App\Models\Guest;
use App\Models\Reservation;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::GuestsView->value);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'segment' => ['nullable', 'in:vip,repeat,in_house,marketing'],
        ]);

        $guests = Guest::query()
            ->withCount(['reservations as stays_count' => fn ($query) => $query->where('status', ReservationStatus::CheckedOut->value)])
            ->withMax('reservations as last_stay', 'check_out_date')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->search($search))
            ->when(($filters['segment'] ?? null) === 'vip', fn ($query) => $query->where('is_vip', true))
            ->when(($filters['segment'] ?? null) === 'repeat', fn ($query) => $query->has('reservations', '>=', 2))
            ->when(($filters['segment'] ?? null) === 'marketing', fn ($query) => $query->where('marketing_consent', true))
            ->when(($filters['segment'] ?? null) === 'in_house', fn ($query) => $query->whereHas('reservations', fn ($reservations) => $reservations->where('status', ReservationStatus::CheckedIn->value)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Guest $guest) => $this->summary($guest) + [
                'stays_count' => (int) $guest->getAttribute('stays_count'),
                'last_stay' => $guest->getAttribute('last_stay'),
            ]);

        return Inertia::render('admin/guests/index', [
            'guests' => $guests,
            'filters' => ['search' => $filters['search'] ?? '', 'segment' => $filters['segment'] ?? ''],
            'currencies' => Currency::codes(),
            'can' => [
                'create' => $request->user()->can(Permission::GuestsCreate->value),
                'update' => $request->user()->can(Permission::GuestsUpdate->value),
                'viewIdentity' => $request->user()->can(Permission::GuestsViewIdentity->value),
            ],
        ]);
    }

    public function show(Request $request, Guest $guest): Response
    {
        Gate::authorize(Permission::GuestsView->value);

        $canSeeIdentity = $request->user()->can(Permission::GuestsViewIdentity->value);

        $reservations = Reservation::query()
            ->whereBelongsTo($guest)
            ->with(['guest', 'activeAllocations.room:id,number', 'activeAllocations.roomType:id,name', 'allocations.room:id,number', 'allocations.roomType:id,name'])
            ->orderByDesc('check_in_date')
            ->get();

        if ($canSeeIdentity && filled($guest->identity_number)) {
            app(AuditLogger::class)->record('guest.identity_viewed', $guest);
        }

        return Inertia::render('admin/guests/show', [
            'guest' => $this->summary($guest) + [
                'address' => $guest->address,
                'date_of_birth' => $guest->date_of_birth?->toDateString(),
                'notes' => $guest->notes,
                'preferred_currency' => $guest->preferred_currency?->value,
                'identity_type' => $canSeeIdentity ? $guest->identity_type : null,
                'identity_number' => $canSeeIdentity ? $guest->identity_number : null,
                'has_account' => $guest->user_id !== null,
            ],
            'reservations' => $reservations->map(fn (Reservation $reservation) => (new ReservationSummaryResource($reservation))->resolve()),
            'stats' => [
                'completed_stays' => $reservations->where('status', ReservationStatus::CheckedOut)->count(),
                'nights' => $reservations->where('status', ReservationStatus::CheckedOut)->sum('nights'),
                'cancellations' => $reservations->where('status', ReservationStatus::Cancelled)->count(),
                'no_shows' => $reservations->where('status', ReservationStatus::NoShow)->count(),
            ],
            'currencies' => Currency::codes(),
            'can' => [
                'update' => $request->user()->can(Permission::GuestsUpdate->value),
                'viewIdentity' => $canSeeIdentity,
            ],
        ]);
    }

    public function store(SaveGuestRequest $request, AuditLogger $audit): RedirectResponse
    {
        $guest = Guest::create($request->guestAttributes());
        $audit->record('guest.created', $guest, newValues: ['name' => $guest->full_name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guest profile created.')]);

        return back();
    }

    public function update(SaveGuestRequest $request, Guest $guest, AuditLogger $audit): RedirectResponse
    {
        $guest->update($request->guestAttributes());
        $audit->recordChanges('guest.updated', $guest);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Guest profile updated.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Guest $guest): array
    {
        return [
            'id' => $guest->id,
            'first_name' => $guest->first_name,
            'last_name' => $guest->last_name,
            'name' => $guest->full_name,
            'email' => $guest->email,
            'phone' => $guest->phone,
            'country_code' => $guest->country_code,
            'is_vip' => $guest->is_vip,
            'marketing_consent' => $guest->marketing_consent,
            'initials' => Str::upper(Str::substr($guest->first_name, 0, 1).Str::substr($guest->last_name, 0, 1)),
        ];
    }
}
