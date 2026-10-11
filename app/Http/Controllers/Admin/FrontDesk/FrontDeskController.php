<?php

namespace App\Http\Controllers\Admin\FrontDesk;

use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\FrontDesk\NightAuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Today's arrivals, in-house guests and departures.
 */
class FrontDeskController extends Controller
{
    public function __invoke(Request $request, NightAuditService $nightAudit): Response
    {
        abort_unless($request->user()->can(Permission::CheckinManage->value) || $request->user()->can(Permission::CheckoutManage->value), 403);

        $search = trim((string) $request->query('search', ''));
        $today = CarbonImmutable::today();

        $base = fn () => Reservation::query()
            ->with(['guest', 'activeAllocations.room:id,number,housekeeping_status', 'activeAllocations.roomType:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('contact_email', 'like', "%{$search}%")
                ->orWhere('contact_phone', 'like', "%{$search}%")
                ->orWhereHas('guest', fn (Builder $guests) => $guests->search($search))));

        $arrivals = $base()
            ->whereIn('status', [ReservationStatus::Confirmed->value, ReservationStatus::Pending->value])
            ->whereDate('check_in_date', '<=', $today)
            ->whereDate('check_out_date', '>', $today)
            ->orderBy('check_in_date')
            ->orderBy('contact_name')
            ->get();

        $inHouse = $base()
            ->where('status', ReservationStatus::CheckedIn->value)
            ->orderBy('check_out_date')
            ->orderBy('contact_name')
            ->get();

        return Inertia::render('admin/front-desk/index', [
            'today' => $today->toDateString(),
            'businessDate' => $nightAudit->businessDate()->toDateString(),
            'search' => $search,
            'arrivals' => $arrivals->map(fn (Reservation $reservation) => $this->row($reservation)),
            'inHouse' => $inHouse->map(fn (Reservation $reservation) => $this->row($reservation) + [
                'departing_today' => $reservation->check_out_date->lessThanOrEqualTo($today),
            ]),
            'can' => [
                'checkIn' => $request->user()->can(Permission::CheckinManage->value),
                'checkOut' => $request->user()->can(Permission::CheckoutManage->value),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Reservation $reservation): array
    {
        $allocation = $reservation->activeAllocations->sortBy('start_date')->last();

        return [
            'reference' => $reservation->reference,
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'guest' => $reservation->guest->full_name,
            'is_vip' => $reservation->guest->is_vip,
            'phone' => $reservation->contact_phone,
            'check_in_date' => $reservation->check_in_date->toDateString(),
            'check_out_date' => $reservation->check_out_date->toDateString(),
            'nights' => $reservation->nights,
            'party' => $reservation->adults + $reservation->children,
            'room' => $allocation?->room?->number,
            'room_type' => $allocation?->roomType?->name,
            'room_ready' => $allocation?->room?->housekeeping_status->isReady(true),
            'housekeeping_status' => $allocation?->room?->housekeeping_status->value,
            'balance' => $reservation->balanceDue(),
            'deposit_outstanding' => $reservation->money(max(0, $reservation->deposit_due_minor - $reservation->amount_paid_minor)),
            'special_requests' => $reservation->special_requests,
        ];
    }
}
