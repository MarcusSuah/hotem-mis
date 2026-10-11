<?php

namespace App\Http\Controllers\Admin\Reservations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reservations\CancelReservationRequest;
use App\Models\Reservation;
use App\Services\Booking\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Status transitions that are not part of check-in/check-out.
 */
class ReservationStatusController extends Controller
{
    public function confirm(Request $request, Reservation $reservation, ReservationService $reservations): RedirectResponse
    {
        Gate::authorize('update', $reservation);

        $reservations->confirm($reservation, $request->user(), 'Confirmed by staff');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reservation confirmed.')]);

        return back();
    }

    public function cancel(CancelReservationRequest $request, Reservation $reservation, ReservationService $reservations): RedirectResponse
    {
        $cancelled = $reservations->cancel(
            $reservation,
            $request->string('reason')->toString(),
            $request->user(),
            waiveFee: $request->boolean('waive_fee'),
        );

        $fee = $cancelled->money($cancelled->cancellation_fee_minor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $fee->isZero()
                ? __('Reservation cancelled without a fee.')
                : __('Reservation cancelled. Cancellation fee: :fee.', ['fee' => $fee->format()]),
        ]);

        return back();
    }

    public function noShow(Request $request, Reservation $reservation, ReservationService $reservations): RedirectResponse
    {
        Gate::authorize('markNoShow', $reservation);

        $reservations->markNoShow($reservation, $request->user());

        Inertia::flash('toast', ['type' => 'warning', 'message' => __('Reservation marked as a no-show and the room released.')]);

        return back();
    }
}
