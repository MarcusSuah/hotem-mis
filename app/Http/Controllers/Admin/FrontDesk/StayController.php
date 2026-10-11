<?php

namespace App\Http\Controllers\Admin\FrontDesk;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FrontDesk\CheckInRequest;
use App\Http\Requests\Admin\FrontDesk\CheckOutRequest;
use App\Http\Requests\Admin\FrontDesk\ExtendStayRequest;
use App\Http\Resources\ReservationDetailResource;
use App\Models\Reservation;
use App\Services\Billing\FolioService;
use App\Services\Booking\ReservationService;
use App\Services\FrontDesk\FrontDeskService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Check-in, check-out, stay extension and the registration card.
 */
class StayController extends Controller
{
    public function showCheckIn(Request $request, Reservation $reservation, FrontDeskService $frontDesk): InertiaResponse
    {
        Gate::authorize(Permission::CheckinManage->value);

        $reservation->load(['guest', 'ratePlan', 'bookedBy', 'folio', 'activeAllocations.room', 'activeAllocations.roomType', 'allocations.room', 'allocations.roomType', 'nightRecords.room', 'statusHistory.user', 'occupants']);
        $room = $frontDesk->currentRoom($reservation);
        $user = $request->user();

        return Inertia::render('admin/front-desk/check-in', [
            'reservation' => (new ReservationDetailResource($reservation))->resolve(),
            'room' => $room ? [
                'number' => $room->number,
                'housekeeping_status' => $room->housekeeping_status->value,
                'housekeeping_label' => $room->housekeeping_status->label(),
            ] : null,
            'guestDetails' => [
                'country_code' => $reservation->guest->country_code,
                'address' => $reservation->guest->address,
                'identity_type' => $reservation->guest->identity_type,
                'has_identity' => filled($reservation->guest->identity_number),
            ],
            'blockers' => $frontDesk->checkInBlockers($reservation, $user),
            'folio' => $reservation->folio?->number,
            'depositOutstanding' => $reservation->money(max(0, $reservation->deposit_due_minor - $reservation->amount_paid_minor)),
            'paymentOptions' => $this->paymentOptions(),
            'can' => [
                'waiveDeposit' => $user->can(Permission::FoliosOverride->value),
                'recordIdentity' => $user->can(Permission::GuestsViewIdentity->value),
                'recordPayment' => $user->can(Permission::PaymentsCreate->value),
                'assignRoom' => $user->can(Permission::BookingsAssignRoom->value),
            ],
        ]);
    }

    public function checkIn(CheckInRequest $request, Reservation $reservation, FrontDeskService $frontDesk): RedirectResponse
    {
        $frontDesk->checkIn($reservation, $request->details($reservation->currency->value), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':guest checked in.', ['guest' => $reservation->contact_name])]);

        return to_route('admin.front-desk.index');
    }

    public function showCheckOut(Request $request, Reservation $reservation, FolioService $folios): InertiaResponse
    {
        Gate::authorize(Permission::CheckoutManage->value);

        $reservation->load(['guest', 'ratePlan', 'bookedBy', 'activeAllocations.room', 'activeAllocations.roomType', 'allocations.room', 'allocations.roomType', 'nightRecords.room', 'statusHistory.user']);
        $folio = $folios->openFor($reservation);
        $summary = $folios->summary($folio);
        $postedKeys = $folio->charges()->whereNotNull('idempotency_key')->pluck('idempotency_key')->flip();
        $unposted = $reservation->nightRecords
            ->filter(fn ($night) => $night->night_date->lessThan($reservation->check_out_date))
            ->reject(fn ($night) => $postedKeys->has("stay:{$reservation->id}:{$night->night_date->toDateString()}:room"));
        $unpostedTotal = (int) $unposted->sum(fn ($night) => $night->rate_minor + $night->service_charge_minor + $night->tax_minor);
        $today = CarbonImmutable::today();

        return Inertia::render('admin/front-desk/check-out', [
            'reservation' => (new ReservationDetailResource($reservation))->resolve(),
            'folio' => ['number' => $folio->number, 'summary' => $summary->toArray()],
            'paymentOptions' => $this->paymentOptions(),
            'projected' => [
                'unposted_nights' => $unposted->count(),
                'unposted_total' => $reservation->money($unpostedTotal),
                'balance_after_posting' => $summary->balance->add($reservation->money($unpostedTotal)),
                'early_departure' => $today->lessThan($reservation->check_out_date) && $today->greaterThan($reservation->check_in_date),
            ],
            'can' => [
                'settleLater' => $request->user()->can(Permission::FoliosOverride->value),
                'recordPayment' => $request->user()->can(Permission::PaymentsCreate->value),
            ],
        ]);
    }

    public function checkOut(CheckOutRequest $request, Reservation $reservation, FrontDeskService $frontDesk): RedirectResponse
    {
        $result = $frontDesk->checkOut(
            $reservation,
            $request->user(),
            $request->filled('late_check_out_fee') ? Money::of($request->string('late_check_out_fee')->toString(), $reservation->currency) : null,
            $request->boolean('settle_later'),
            $request->filled('settle_later_reason') ? $request->string('settle_later_reason')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':guest checked out. Invoice :invoice issued.', [
            'guest' => $reservation->contact_name,
            'invoice' => $result['invoice']->number,
        ])]);

        return to_route('admin.front-desk.index');
    }

    public function registrationCard(Request $request, Reservation $reservation, FrontDeskService $frontDesk, SettingsRepository $settings): Response
    {
        Gate::authorize(Permission::CheckinManage->value);

        $reservation->load(['guest', 'occupants']);
        $room = $frontDesk->currentRoom($reservation)?->load('roomType');

        return Pdf::loadView('pdf.registration-card', [
            'reservation' => $reservation,
            'room' => $room,
            'hotel' => $settings->group('hotel'),
            'checkInTime' => $settings->get('booking.check_in_time'),
            'checkOutTime' => $settings->get('booking.check_out_time'),
            'showIdentity' => $request->user()->can(Permission::GuestsViewIdentity->value),
        ])->setPaper('a4')->stream("registration-{$reservation->reference}.pdf");
    }

    public function extend(ExtendStayRequest $request, Reservation $reservation, ReservationService $reservations): RedirectResponse
    {
        $reservations->extendStay($reservation, $request->checkOutDate(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stay extended.')]);

        return back();
    }

    /**
     * @return array{methods: mixed, currencies: list<string>}
     */
    private function paymentOptions(): array
    {
        return [
            'methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'requires_reference' => $method->requiresReference(),
            ]),
            'currencies' => Currency::codes(),
        ];
    }
}
