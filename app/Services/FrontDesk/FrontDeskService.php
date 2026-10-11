<?php

namespace App\Services\FrontDesk;

use App\Enums\ChargeCategory;
use App\Enums\HousekeepingStatus;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Events\GuestCheckedOut;
use App\Models\Invoice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\FolioService;
use App\Services\Billing\InvoiceService;
use App\Services\Booking\BookingException;
use App\Services\Booking\ReservationService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Check-in and check-out. Check-out never erases a balance: the folio stays
 * open as a receivable unless the balance is settled.
 */
class FrontDeskService
{
    public function __construct(
        private ReservationService $reservations,
        private FolioService $folios,
        private InvoiceService $invoices,
        private SettingsRepository $settings,
        private AuditLogger $audit,
    ) {}

    /**
     * The room the guest sleeps in tonight (or on arrival night).
     */
    public function currentRoom(Reservation $reservation): ?Room
    {
        $today = CarbonImmutable::today();

        $allocation = $reservation->activeAllocations()
            ->with('room')
            ->where('start_date', '<=', max($today, $reservation->check_in_date)->toDateString())
            ->orderByDesc('start_date')
            ->first()
            ?? $reservation->activeAllocations()->with('room')->orderBy('start_date')->first();

        return $allocation?->room;
    }

    /**
     * Reasons the reservation cannot be checked in right now (empty when ready).
     *
     * @return array<string, string>
     */
    public function checkInBlockers(Reservation $reservation, ?User $actor = null, bool $waiveDeposit = false): array
    {
        $blockers = [];
        $today = CarbonImmutable::today();

        if ($reservation->status !== ReservationStatus::Confirmed) {
            $blockers['status'] = $reservation->status === ReservationStatus::Pending
                ? 'Confirm the reservation before checking the guest in.'
                : "A {$reservation->status->label()} reservation cannot be checked in.";

            return $blockers;
        }

        if ($reservation->check_in_date->greaterThan($today)) {
            $blockers['check_in_date'] = "Arrival is booked for {$reservation->check_in_date->format('d M Y')}. Modify the reservation to arrive earlier.";
        }

        if ($reservation->check_out_date->lessThanOrEqualTo($today)) {
            $blockers['check_out_date'] = 'The booked stay has already ended. Mark it as a no-show or extend it first.';
        }

        $room = $this->currentRoom($reservation);
        $requireInspection = (bool) $this->settings->get('booking.require_inspection_before_ready', true);

        if ($room === null) {
            $blockers['room_id'] = 'No room is assigned to this reservation.';
        } elseif (! $room->housekeeping_status->isReady($requireInspection)) {
            $blockers['room_id'] = "Room {$room->number} is not ready ({$room->housekeeping_status->label()}). Move the guest to a ready room or wait for housekeeping.";
        }

        $depositMet = $reservation->amount_paid_minor >= $reservation->deposit_due_minor;
        $mayWaive = $waiveDeposit && $actor?->can(Permission::FoliosOverride->value);

        if (! $depositMet && ! $mayWaive) {
            $outstanding = $reservation->money($reservation->deposit_due_minor - $reservation->amount_paid_minor);
            $blockers['deposit'] = "A deposit of {$outstanding->format()} is still required before check-in.";
        }

        return $blockers;
    }

    public function checkIn(Reservation $reservation, CheckInDetails $details, ?User $actor = null): Reservation
    {
        // Evaluate the rules against current data, not a possibly stale instance.
        $reservation->refresh();

        $blockers = $this->checkInBlockers($reservation, $actor, $details->waiveDeposit);

        if ($blockers !== []) {
            throw new BookingException(reset($blockers), (string) array_key_first($blockers));
        }

        return DB::transaction(function () use ($reservation, $details, $actor): Reservation {
            $checkedIn = $this->reservations->transition($reservation, ReservationStatus::CheckedIn, $actor, 'Guest checked in', function (Reservation $locked): void {
                $locked->forceFill(['checked_in_at' => now()]);
            });

            $guest = $checkedIn->guest;
            $guest->fill(array_filter([
                'identity_type' => $details->identityType,
                'identity_number' => $details->identityNumber,
                'country_code' => $details->countryCode,
                'address' => $details->address,
            ], fn ($value) => filled($value)))->save();

            foreach ($details->additionalGuests as $occupant) {
                $checkedIn->occupants()->create([
                    'full_name' => $occupant['name'],
                    'age_category' => $occupant['age_category'],
                    'is_primary' => false,
                ]);
            }

            $folio = $this->folios->openFor($checkedIn);

            if ($details->earlyCheckInFee !== null && $details->earlyCheckInFee->isPositive()) {
                $this->folios->post($folio, ChargeCategory::EarlyCheckIn, 'Early check-in', $details->earlyCheckInFee, actor: $actor, idempotencyKey: "early:{$checkedIn->id}");
            }

            if ($details->waiveDeposit && $checkedIn->amount_paid_minor < $checkedIn->deposit_due_minor) {
                $this->audit->record('reservation.deposit_waived', $checkedIn, newValues: [
                    'deposit_due' => $checkedIn->money($checkedIn->deposit_due_minor)->format(),
                    'paid' => $checkedIn->money($checkedIn->amount_paid_minor)->format(),
                ], actor: $actor);
            }

            $this->audit->record('reservation.checked_in', $checkedIn, newValues: [
                'room' => $this->currentRoom($checkedIn)?->number,
                'additional_guests' => count($details->additionalGuests),
            ], actor: $actor);

            return $checkedIn;
        });
    }

    /**
     * Complete the stay. Every remaining night is posted to the folio; leaving
     * with an unpaid balance requires an authorised "settle later" decision.
     *
     * @return array{reservation: Reservation, invoice: Invoice}
     */
    public function checkOut(Reservation $reservation, ?User $actor = null, ?Money $lateCheckOutFee = null, bool $settleLater = false, ?string $settleLaterReason = null): array
    {
        if ($reservation->status !== ReservationStatus::CheckedIn) {
            throw new BookingException("A {$reservation->status->label()} reservation cannot be checked out.", 'status');
        }

        if ($settleLater && ! $actor?->can(Permission::FoliosOverride->value)) {
            throw new BookingException('You are not allowed to check out guests with an unpaid balance.', 'settle_later');
        }

        return DB::transaction(function () use ($reservation, $actor, $lateCheckOutFee, $settleLater, $settleLaterReason): array {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $today = CarbonImmutable::today();
            $room = $this->currentRoom($locked);
            $earlyDeparture = $today->lessThan($locked->check_out_date) && $today->greaterThan($locked->check_in_date);

            if ($earlyDeparture) {
                $this->reservations->releaseNightsFrom($locked, $today);
            }

            $folio = $this->folios->openFor($locked);
            $this->folios->postStayNights($locked, $locked->check_out_date, $actor);

            if ($lateCheckOutFee !== null && $lateCheckOutFee->isPositive()) {
                $this->folios->post($folio, ChargeCategory::LateCheckOut, 'Late check-out', $lateCheckOutFee, actor: $actor, idempotencyKey: "late:{$locked->id}");
            }

            $balance = $this->folios->summary($folio)->balance;

            if ($balance->isPositive() && ! $settleLater) {
                throw new BookingException("The guest still owes {$balance->format()}. Record the payment or authorise settling later.", 'balance');
            }

            $checkedOut = $this->reservations->transition($locked, ReservationStatus::CheckedOut, $actor, $earlyDeparture ? 'Checked out early' : 'Guest checked out', function (Reservation $model): void {
                $model->forceFill(['checked_out_at' => now()]);
            });

            $this->folios->syncReservation($folio);

            if (! $balance->isPositive()) {
                $this->folios->close($folio);
            } else {
                $this->audit->record('reservation.settle_later', $checkedOut, newValues: [
                    'balance' => $balance->format(),
                    'reason' => $settleLaterReason,
                ], actor: $actor);
            }

            $invoice = $this->invoices->issue($folio->fresh() ?? $folio, $actor);

            if ($room !== null) {
                $room->forceFill(['housekeeping_status' => HousekeepingStatus::Dirty])->save();
                GuestCheckedOut::dispatch($checkedOut, $room);
            }

            $this->audit->record('reservation.checked_out', $checkedOut, newValues: [
                'room' => $room?->number,
                'invoice' => $invoice->number,
                'balance' => $balance->format(),
            ], actor: $actor);

            return ['reservation' => $checkedOut, 'invoice' => $invoice];
        });
    }
}
