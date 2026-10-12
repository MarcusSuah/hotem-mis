<?php

namespace App\Http\Controllers\Account;

use App\Enums\InvoiceStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\SubmitReviewRequest;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Billing\FolioService;
use App\Services\Billing\InvoiceService;
use App\Services\Booking\CancellationCalculator;
use App\Services\Booking\ReservationService;
use App\Services\Website\ReviewService;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * A signed-in guest's own stays. Every lookup is scoped to the guest
 * profile linked to the user, and anything else answers 404.
 */
class AccountReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $guestId = $request->user()->loadMissing('guest')->guest?->id;

        $reservations = Reservation::query()
            ->where('guest_id', $guestId ?? 0)
            ->with(['activeAllocations.roomType:id,name', 'allocations.roomType:id,name'])
            ->orderByDesc('check_in_date')
            ->get()
            ->map(fn (Reservation $reservation) => $this->summary($reservation));

        return Inertia::render('account/reservations/index', [
            'reservations' => $reservations,
        ]);
    }

    public function show(Request $request, Reservation $reservation, CancellationCalculator $cancellations, ReviewService $reviews, FolioService $folios): Response
    {
        $this->ensureOwned($request->user(), $reservation);

        $reservation->load(['review', 'folio.invoices' => fn ($query) => $query->where('status', '!=', InvoiceStatus::Void->value)->orderByDesc('id')]);
        $cancellable = $this->isCancellable($reservation);

        return Inertia::render('account/reservations/show', [
            'reservation' => [
                ...$this->summary($reservation),
                'special_requests' => $reservation->special_requests,
                'room_total' => Money::ofMinor($reservation->room_total_minor, $reservation->currency),
                'service_charge' => Money::ofMinor($reservation->service_charge_minor, $reservation->currency),
                'tax' => Money::ofMinor($reservation->tax_minor, $reservation->currency),
                'deposit_due' => Money::ofMinor($reservation->deposit_due_minor, $reservation->currency),
                'amount_paid' => Money::ofMinor($reservation->amount_paid_minor, $reservation->currency),
                'balance' => $reservation->folio ? $folios->summary($reservation->folio)->balance : null,
                'cancellation_fee' => $reservation->cancellation_fee_minor > 0 ? Money::ofMinor($reservation->cancellation_fee_minor, $reservation->currency) : null,
            ],
            'cancellation' => $cancellable ? [
                'fee' => $cancellations->fee($reservation, CarbonImmutable::now()),
            ] : null,
            'invoices' => $reservation->folio?->invoices->map(fn (Invoice $invoice) => [
                'number' => $invoice->number,
                'total' => Money::ofMinor($invoice->total_minor, $invoice->currency),
                'issued_at' => $invoice->issued_at->toIso8601String(),
            ])->values() ?? [],
            'review' => $reservation->review ? [
                'rating' => $reservation->review->rating,
                'title' => $reservation->review->title,
                'body' => $reservation->review->body,
                'status_label' => $reservation->review->status->label(),
                'response' => $reservation->review->management_response,
            ] : null,
            'canReview' => $reviews->canReview($request->user(), $reservation),
        ]);
    }

    public function cancel(Request $request, Reservation $reservation, ReservationService $service): RedirectResponse
    {
        $this->ensureOwned($request->user(), $reservation);

        if (! $this->isCancellable($reservation)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This reservation can no longer be cancelled online. Please contact the hotel.')]);

            return back();
        }

        $service->cancel($reservation, 'Cancelled by the guest online', $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your reservation :reference has been cancelled.', ['reference' => $reservation->reference])]);

        return back();
    }

    public function review(SubmitReviewRequest $request, Reservation $reservation, ReviewService $reviews): RedirectResponse
    {
        $this->ensureOwned($request->user(), $reservation);

        $reviews->submit($request->user(), $reservation, $request->integer('rating'), $request->string('title')->toString(), $request->string('body')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you for your review. It will appear on our website once approved.')]);

        return back();
    }

    public function invoice(Request $request, Invoice $invoice, InvoiceService $invoices): HttpResponse
    {
        $guestId = $request->user()->loadMissing('guest')->guest?->id;

        abort_unless($guestId !== null && $invoice->guest_id === $guestId && $invoice->status !== InvoiceStatus::Void, 404);

        return $invoices->pdf($invoice);
    }

    private function ensureOwned(User $user, Reservation $reservation): void
    {
        $user->loadMissing('guest');

        abort_unless($user->guest !== null && $reservation->guest_id === $user->guest->id, 404);
    }

    private function isCancellable(Reservation $reservation): bool
    {
        return in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)
            && $reservation->check_in_date->isAfter(CarbonImmutable::today()->subDay());
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Reservation $reservation): array
    {
        $allocation = $reservation->activeAllocations->first() ?? $reservation->allocations->first();

        return [
            'reference' => $reservation->reference,
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'check_in_date' => $reservation->check_in_date->toDateString(),
            'check_out_date' => $reservation->check_out_date->toDateString(),
            'nights' => $reservation->nights,
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'room_type' => $allocation?->roomType?->name,
            'total' => Money::ofMinor($reservation->total_minor, $reservation->currency),
        ];
    }
}
