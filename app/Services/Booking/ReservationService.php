<?php

namespace App\Services\Booking;

use App\Enums\ChargeCategory;
use App\Enums\ReservationStatus;
use App\Models\CancellationPolicy;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\FolioService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Creates and changes reservations. Every inventory change runs in a database
 * transaction that locks the affected room rows, re-checks availability, and
 * writes one reservation_nights row per room-night; the unique
 * (room_id, night_date) index rejects any double booking that slips through.
 */
class ReservationService
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private CancellationCalculator $cancellations,
        private ReferenceGenerator $references,
        private SettingsRepository $settings,
        private FolioService $folios,
        private AuditLogger $audit,
    ) {}

    public function create(NewReservation $data, ?User $actor = null): Reservation
    {
        if ($data->idempotencyKey !== null) {
            $existing = Reservation::query()->where('idempotency_key', $data->idempotencyKey)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $this->ensurePartyFits($data->roomType, $data->adults, $data->children);
        $this->ensureStayIsAllowed($data->stay, $data->ratePlan);

        $ratePlan = ($data->ratePlan ?? RatePlan::query()->active()->where('is_default', true)->first())?->loadMissing('cancellationPolicy');
        $policy = $ratePlan->cancellationPolicy ?? CancellationPolicy::query()->where('is_default', true)->first();

        try {
            return DB::transaction(function () use ($data, $actor, $ratePlan, $policy): Reservation {
                $room = $this->lockAvailableRoom($data->roomType, $data->stay, $data->roomId);

                $quote = $this->pricing->quote(
                    $data->roomType, $ratePlan, $data->stay, $data->adults, $data->children,
                    $data->currency, $data->complimentary,
                );

                $status = $data->confirmImmediately ? ReservationStatus::Confirmed : ReservationStatus::Pending;

                $reservation = Reservation::create([
                    'reference' => $this->references->generate(),
                    'property_id' => $data->roomType->property_id,
                    'guest_id' => $data->guest->id,
                    'rate_plan_id' => $ratePlan?->id,
                    'booked_by' => $actor?->id,
                    'source' => $data->source,
                    'status' => $status,
                    'check_in_date' => $data->stay->checkIn,
                    'check_out_date' => $data->stay->checkOut,
                    'arrival_time' => $data->arrivalTime,
                    'nights' => $data->stay->nights(),
                    'adults' => $data->adults,
                    'children' => $data->children,
                    'contact_name' => $data->contactName ?? $data->guest->full_name,
                    'contact_email' => $data->contactEmail ?? $data->guest->email,
                    'contact_phone' => $data->contactPhone ?? $data->guest->phone,
                    'special_requests' => $data->specialRequests,
                    'currency' => $quote->currency,
                    'base_currency' => $quote->baseCurrency,
                    'exchange_rate_id' => $quote->exchangeRateRecord?->id,
                    'exchange_rate' => $quote->exchangeRate,
                    'tax_rate' => $quote->taxRate,
                    'service_charge_rate' => $quote->serviceChargeRate,
                    'room_total_minor' => $quote->roomTotal->minor,
                    'service_charge_minor' => $quote->serviceCharge->minor,
                    'tax_minor' => $quote->tax->minor,
                    'total_minor' => $quote->total->minor,
                    'base_total_minor' => $quote->baseTotal->minor,
                    'deposit_due_minor' => $quote->depositDue->minor,
                    'is_complimentary' => $data->complimentary,
                    'cancellation_policy_snapshot' => $policy?->snapshot() ?? ['penalty_type' => 'none'],
                    'idempotency_key' => $data->idempotencyKey,
                    'expires_at' => $status === ReservationStatus::Pending
                        ? now()->addMinutes($data->holdMinutes ?? (int) $this->settings->get('booking.pending_expiry_minutes', 60))
                        : null,
                ]);

                if ($status === ReservationStatus::Confirmed) {
                    $reservation->forceFill(['confirmed_at' => now()])->save();
                }

                $this->allocate($reservation, $room, $data->stay, $quote, $actor);
                $this->folios->openFor($reservation);

                $reservation->occupants()->create([
                    'guest_id' => $data->guest->id,
                    'full_name' => $data->guest->full_name,
                    'age_category' => 'adult',
                    'is_primary' => true,
                ]);

                $this->recordStatus($reservation, null, $status, $actor, 'Reservation created via '.$data->source->label());
                $this->audit->record('reservation.created', $reservation, newValues: [
                    'reference' => $reservation->reference,
                    'room' => $room->number,
                    'check_in' => $data->stay->checkIn->toDateString(),
                    'check_out' => $data->stay->checkOut->toDateString(),
                    'total' => $quote->total->format(),
                ], actor: $actor);

                return $reservation;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A concurrent request with the same idempotency key won the race.
            if ($data->idempotencyKey !== null && str_contains($exception->getMessage(), 'idempotency_key')) {
                return Reservation::query()->where('idempotency_key', $data->idempotencyKey)->firstOrFail();
            }

            throw BookingException::roomUnavailable($data->roomId ? 'room_id' : 'room_type_id');
        }
    }

    public function confirm(Reservation $reservation, ?User $actor = null, ?string $note = null): Reservation
    {
        return $this->transition($reservation, ReservationStatus::Confirmed, $actor, $note, function (Reservation $locked): void {
            $locked->forceFill(['confirmed_at' => now(), 'expires_at' => null]);
        });
    }

    /**
     * Cancel and release the room. Any fee is posted to the folio as a charge,
     * so prepaid amounts above the fee become refundable credit.
     */
    public function cancel(Reservation $reservation, string $reason, ?User $actor = null, bool $waiveFee = false): Reservation
    {
        return DB::transaction(function () use ($reservation, $reason, $actor, $waiveFee): Reservation {
            $cancelled = $this->transition($reservation, ReservationStatus::Cancelled, $actor, $reason, function (Reservation $locked) use ($reason, $waiveFee): void {
                $fee = $waiveFee ? 0 : $this->cancellations->fee($locked, CarbonImmutable::now())->minor;

                $locked->forceFill([
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    'cancellation_fee_minor' => $fee,
                    'expires_at' => null,
                ]);

                $this->releaseAllocations($locked, 'Reservation cancelled');
            });

            $this->postFee($cancelled, ChargeCategory::CancellationFee, 'Cancellation fee', $actor);

            return $cancelled;
        });
    }

    public function markNoShow(Reservation $reservation, ?User $actor = null): Reservation
    {
        if ($reservation->check_in_date->isFuture()) {
            throw new BookingException('A reservation can only be marked as a no-show on or after its arrival date.', 'status');
        }

        return DB::transaction(function () use ($reservation, $actor): Reservation {
            $noShow = $this->transition($reservation, ReservationStatus::NoShow, $actor, 'Guest did not arrive', function (Reservation $locked): void {
                $locked->forceFill([
                    'no_show_at' => now(),
                    'cancellation_fee_minor' => $this->cancellations->noShowFee($locked)->minor,
                ]);

                $this->releaseAllocations($locked, 'No-show');
            });

            $this->postFee($noShow, ChargeCategory::NoShowFee, 'No-show fee', $actor);

            return $noShow;
        });
    }

    private function postFee(Reservation $reservation, ChargeCategory $category, string $description, ?User $actor): void
    {
        $folio = $this->folios->openFor($reservation);

        if ($reservation->cancellation_fee_minor > 0) {
            $this->folios->post(
                $folio,
                $category,
                "{$description} — {$reservation->reference}",
                $reservation->money($reservation->cancellation_fee_minor),
                actor: $actor,
                idempotencyKey: "{$category->value}:{$reservation->id}",
            );
        } else {
            $this->folios->syncReservation($folio);
        }
    }

    /**
     * Change dates and/or party size. The current room is kept when it is free
     * for the new dates; otherwise another room of the same type is assigned.
     * The stay is re-priced with the exchange rate agreed at booking.
     */
    public function modify(Reservation $reservation, StayPeriod $stay, int $adults, int $children, ?User $actor = null, ?int $roomId = null): Reservation
    {
        if (! $reservation->status->isModifiable()) {
            throw new BookingException("A {$reservation->status->label()} reservation cannot be modified.", 'status');
        }

        $allocation = $reservation->activeAllocations()->with('roomType')->firstOrFail();
        $roomType = $allocation->roomType;

        $this->ensurePartyFits($roomType, $adults, $children);
        $this->ensureStayIsAllowed($stay, $reservation->ratePlan);

        return DB::transaction(function () use ($reservation, $stay, $adults, $children, $actor, $roomId, $allocation, $roomType): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $before = $this->summary($locked);

            $preferredRoom = $roomId ?? $allocation->room_id;
            $room = $this->lockAvailableRoom($roomType, $stay, $preferredRoom, $locked, strict: $roomId !== null);

            $quote = $this->pricing->quote(
                $roomType, $locked->ratePlan, $stay, $adults, $children, $locked->currency, $locked->is_complimentary,
                snapshotRate: $locked->exchange_rate, snapshotRecord: $locked->exchangeRateRecord,
            );

            $this->releaseAllocations($locked, 'Reservation modified');

            $locked->forceFill([
                'check_in_date' => $stay->checkIn,
                'check_out_date' => $stay->checkOut,
                'nights' => $stay->nights(),
                'adults' => $adults,
                'children' => $children,
                'tax_rate' => $quote->taxRate,
                'service_charge_rate' => $quote->serviceChargeRate,
                'room_total_minor' => $quote->roomTotal->minor,
                'service_charge_minor' => $quote->serviceCharge->minor,
                'tax_minor' => $quote->tax->minor,
                'total_minor' => $quote->total->minor + $locked->extras_total_minor - $locked->discount_minor,
                'base_total_minor' => $quote->baseTotal->minor,
                'deposit_due_minor' => $quote->depositDue->minor,
            ])->save();

            $this->allocate($locked, $room, $stay, $quote, $actor);

            $this->audit->record('reservation.modified', $locked, $before, $this->summary($locked->fresh() ?? $locked), $actor);

            return $locked;
        });
    }

    /**
     * Add nights to an in-house stay in the guest's current room. The extra
     * nights are priced at current rates with the exchange rate agreed at booking.
     */
    public function extendStay(Reservation $reservation, CarbonImmutable $newCheckOut, ?User $actor = null): Reservation
    {
        if ($reservation->status !== ReservationStatus::CheckedIn) {
            throw new BookingException('Only in-house stays can be extended here; modify upcoming reservations instead.', 'check_out_date');
        }

        if ($newCheckOut->lessThanOrEqualTo($reservation->check_out_date)) {
            throw new BookingException('The new departure date must be after the current one. Use check-out for early departures.', 'check_out_date');
        }

        return DB::transaction(function () use ($reservation, $newCheckOut, $actor): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $allocation = $locked->activeAllocations()->with(['room', 'roomType'])->orderByDesc('end_date')->firstOrFail();
            $extra = new StayPeriod($locked->check_out_date, $newCheckOut);

            $this->ensureStayIsAllowed(new StayPeriod($locked->check_in_date, $newCheckOut), null);

            Room::query()->whereKey($allocation->room_id)->lockForUpdate()->first();

            if (! $this->availability->isRoomAvailable($allocation->room, $extra, $locked)) {
                throw new BookingException("Room {$allocation->room->number} is not free for the extra nights. Move the guest to another room first.", 'check_out_date');
            }

            $quote = $this->pricing->quote(
                $allocation->roomType, $locked->ratePlan, $extra, $locked->adults, $locked->children, $locked->currency,
                $locked->is_complimentary, snapshotRate: $locked->exchange_rate, snapshotRecord: $locked->exchangeRateRecord,
            );

            foreach ($quote->nights as $night) {
                $allocation->nights()->create([
                    'reservation_id' => $locked->id,
                    'room_id' => $allocation->room_id,
                    'room_type_id' => $allocation->room_type_id,
                    'night_date' => $night['date'],
                    'rate_minor' => $night['amount']->minor,
                    'service_charge_minor' => $night['service']->minor,
                    'tax_minor' => $night['tax']->minor,
                    'base_rate_minor' => $night['base']->minor,
                ]);
            }

            $allocation->forceFill([
                'end_date' => $newCheckOut,
                'room_total_minor' => $allocation->room_total_minor + $quote->roomTotal->minor,
            ])->save();

            $previousCheckOut = $locked->check_out_date->toDateString();

            $locked->forceFill([
                'check_out_date' => $newCheckOut,
                'nights' => $locked->nights + $extra->nights(),
                'room_total_minor' => $locked->room_total_minor + $quote->roomTotal->minor,
                'service_charge_minor' => $locked->service_charge_minor + $quote->serviceCharge->minor,
                'tax_minor' => $locked->tax_minor + $quote->tax->minor,
                'total_minor' => $locked->total_minor + $quote->total->minor,
                'base_total_minor' => $locked->base_total_minor + $quote->baseTotal->minor,
            ])->save();

            $this->folios->syncReservation($this->folios->openFor($locked));

            $this->audit->record('reservation.extended', $locked, ['check_out' => $previousCheckOut], [
                'check_out' => $newCheckOut->toDateString(),
                'additional' => $quote->total->format(),
            ], $actor);

            return $locked;
        });
    }

    /**
     * Shorten an in-house stay that ends early: nights from `$departure`
     * onwards are released and removed from the agreed total.
     */
    public function releaseNightsFrom(Reservation $reservation, CarbonImmutable $departure): void
    {
        $released = $reservation->nightRecords()->where('night_date', '>=', $departure->toDateString())->get();

        if ($released->isEmpty()) {
            return;
        }

        $room = (int) $released->sum('rate_minor');
        $service = (int) $released->sum('service_charge_minor');
        $tax = (int) $released->sum('tax_minor');
        $releasedTotal = $reservation->money($room + $service + $tax);

        // Express the released amount in base currency with the booking's own rate snapshot.
        $base = $reservation->exchange_rate === null
            ? $releasedTotal->minor
            : $releasedTotal->multiply((new Number(1))->div(new Number(Money::numeric((string) $reservation->exchange_rate)), 12)->value)->minor;

        $reservation->nightRecords()->where('night_date', '>=', $departure->toDateString())->delete();

        foreach ($reservation->activeAllocations()->get() as $allocation) {
            if ($allocation->start_date->greaterThanOrEqualTo($departure)) {
                $allocation->forceFill(['released_at' => now(), 'release_reason' => 'Early departure'])->save();
            } elseif ($allocation->end_date->greaterThan($departure)) {
                $allocation->forceFill([
                    'end_date' => $departure,
                    'room_total_minor' => (int) $allocation->nights()->sum('rate_minor'),
                ])->save();
            }
        }

        $reservation->forceFill([
            'check_out_date' => $departure,
            'nights' => (int) $reservation->check_in_date->diffInDays($departure),
            'room_total_minor' => $reservation->room_total_minor - $room,
            'service_charge_minor' => $reservation->service_charge_minor - $service,
            'tax_minor' => $reservation->tax_minor - $tax,
            'total_minor' => $reservation->total_minor - $room - $service - $tax,
            'base_total_minor' => max(0, $reservation->base_total_minor - $base),
        ])->save();
    }

    /**
     * Move the remaining stay to another room. For in-house guests the move
     * takes effect from today; earlier nights stay on the original room.
     * Room moves do not change the agreed price.
     */
    public function reassignRoom(Reservation $reservation, Room $newRoom, ?User $actor = null, ?string $reason = null): Reservation
    {
        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true)) {
            throw new BookingException("Rooms cannot be reassigned for a {$reservation->status->label()} reservation.", 'room_id');
        }

        $this->ensurePartyFits($newRoom->roomType, $reservation->adults, $reservation->children, 'room_id');

        return DB::transaction(function () use ($reservation, $newRoom, $actor, $reason): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $current = $locked->activeAllocations()->orderByDesc('start_date')->firstOrFail();

            if ($current->room_id === $newRoom->id) {
                throw new BookingException('The reservation is already assigned to this room.', 'room_id');
            }

            $effectiveFrom = $locked->status === ReservationStatus::CheckedIn
                ? max(CarbonImmutable::today(), $current->start_date)
                : $current->start_date;

            if ($effectiveFrom->greaterThanOrEqualTo($current->end_date)) {
                throw new BookingException('There are no remaining nights to move.', 'room_id');
            }

            $stay = new StayPeriod($effectiveFrom, $current->end_date);

            Room::query()->whereKey($newRoom->id)->lockForUpdate()->first();

            if (! $this->availability->isRoomAvailable($newRoom, $stay, $locked)) {
                throw BookingException::roomUnavailable();
            }

            $movingNights = $current->nights()->where('night_date', '>=', $effectiveFrom->toDateString())->orderBy('night_date')->get();
            $movedTotal = (int) $movingNights->sum('rate_minor');

            $current->nights()->where('night_date', '>=', $effectiveFrom->toDateString())->delete();

            if ($effectiveFrom->equalTo($current->start_date)) {
                $current->forceFill(['released_at' => now(), 'release_reason' => $reason ?? 'Room reassigned'])->save();
            } else {
                $current->forceFill([
                    'end_date' => $effectiveFrom,
                    'room_total_minor' => $current->room_total_minor - $movedTotal,
                ])->save();
            }

            $allocation = ReservationRoom::create([
                'reservation_id' => $locked->id,
                'room_id' => $newRoom->id,
                'room_type_id' => $newRoom->room_type_id,
                'start_date' => $effectiveFrom,
                'end_date' => $stay->checkOut,
                'room_total_minor' => $movedTotal,
                'assigned_by' => $actor?->id,
            ]);

            foreach ($movingNights as $night) {
                $allocation->nights()->create([
                    'reservation_id' => $locked->id,
                    'room_id' => $newRoom->id,
                    'room_type_id' => $newRoom->room_type_id,
                    'night_date' => $night->night_date,
                    'rate_minor' => $night->rate_minor,
                    'service_charge_minor' => $night->service_charge_minor,
                    'tax_minor' => $night->tax_minor,
                    'base_rate_minor' => $night->base_rate_minor,
                ]);
            }

            $this->audit->record('reservation.room_reassigned', $locked, ['room_id' => $current->room_id], [
                'room_id' => $newRoom->id,
                'room' => $newRoom->number,
                'effective_from' => $effectiveFrom->toDateString(),
                'reason' => $reason,
            ], $actor);

            return $locked;
        });
    }

    /**
     * Cancel pending reservations whose hold has expired. Returns the count.
     */
    public function expireStalePending(): int
    {
        $expired = Reservation::query()
            ->where('status', ReservationStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->get();

        foreach ($expired as $reservation) {
            $this->cancel($reservation, 'Pending reservation expired before confirmation', waiveFee: true);
        }

        return $expired->count();
    }

    /**
     * Apply a validated status change under a row lock and record its history.
     *
     * @param  callable(Reservation): void  $apply
     */
    public function transition(Reservation $reservation, ReservationStatus $to, ?User $actor, ?string $note, callable $apply): Reservation
    {
        return DB::transaction(function () use ($reservation, $to, $actor, $note, $apply): Reservation {
            $locked = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw BookingException::invalidTransition($from->label(), $to->label());
            }

            $apply($locked);
            $locked->status = $to;
            $locked->save();

            $this->recordStatus($locked, $from, $to, $actor, $note);
            $this->audit->record('reservation.status_changed', $locked, ['status' => $from->value], ['status' => $to->value, 'note' => $note], $actor);

            return $locked;
        });
    }

    /**
     * Lock and return a free room: the requested one, or the first available
     * room of the type. With `strict`, only the requested room is accepted.
     */
    private function lockAvailableRoom(RoomType $roomType, StayPeriod $stay, ?int $roomId, ?Reservation $ignore = null, bool $strict = true): Room
    {
        if ($roomId !== null) {
            $room = Room::query()->where('room_type_id', $roomType->id)->whereKey($roomId)->lockForUpdate()->first();

            if ($room !== null && $this->availability->isRoomAvailable($room, $stay, $ignore)) {
                return $room;
            }

            if ($strict) {
                throw BookingException::roomUnavailable();
            }
        }

        // Lock the type's rooms in a stable order so concurrent requests queue
        // behind each other instead of deadlocking.
        $candidateIds = Room::query()
            ->inService()
            ->where('room_type_id', $roomType->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        $room = $this->availability->availableRooms($stay, $roomType->id, $ignore)
            ->whereIn('id', $candidateIds)
            ->orderBy('floor')
            ->orderBy('number')
            ->first();

        return $room ?? throw BookingException::noAvailability();
    }

    private function allocate(Reservation $reservation, Room $room, StayPeriod $stay, StayQuote $quote, ?User $actor): ReservationRoom
    {
        $allocation = ReservationRoom::create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'room_type_id' => $room->room_type_id,
            'start_date' => $stay->checkIn,
            'end_date' => $stay->checkOut,
            'room_total_minor' => $quote->roomTotal->minor,
            'assigned_by' => $actor?->id,
        ]);

        foreach ($quote->nights as $night) {
            $allocation->nights()->create([
                'reservation_id' => $reservation->id,
                'room_id' => $room->id,
                'room_type_id' => $room->room_type_id,
                'night_date' => $night['date'],
                'rate_minor' => $night['amount']->minor,
                'service_charge_minor' => $night['service']->minor,
                'tax_minor' => $night['tax']->minor,
                'base_rate_minor' => $night['base']->minor,
            ]);
        }

        return $allocation;
    }

    /**
     * Free the reservation's held nights. Allocations are kept as history.
     */
    private function releaseAllocations(Reservation $reservation, string $reason): void
    {
        $reservation->nightRecords()->delete();

        $reservation->allocations()
            ->whereNull('released_at')
            ->update(['released_at' => now(), 'release_reason' => $reason]);
    }

    private function recordStatus(Reservation $reservation, ?ReservationStatus $from, ReservationStatus $to, ?User $actor, ?string $note): void
    {
        $reservation->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor?->id,
            'note' => $note,
        ]);
    }

    public function ensurePartyFits(RoomType $roomType, int $adults, int $children, string $field = 'adults'): void
    {
        if ($adults < 1) {
            throw new BookingException('At least one adult is required.', 'adults');
        }

        if ($adults > $roomType->max_adults) {
            throw new BookingException("The {$roomType->name} accommodates at most {$roomType->max_adults} adults.", $field);
        }

        if ($children > $roomType->max_children) {
            throw new BookingException("The {$roomType->name} accommodates at most {$roomType->max_children} children.", $field === 'adults' ? 'children' : $field);
        }

        if ($adults + $children > $roomType->max_occupancy) {
            throw new BookingException("The {$roomType->name} accommodates at most {$roomType->max_occupancy} guests in total.", $field);
        }
    }

    private function ensureStayIsAllowed(StayPeriod $stay, ?RatePlan $ratePlan): void
    {
        $maxNights = (int) $this->settings->get('booking.max_stay_nights', 30);
        $maxAdvance = (int) $this->settings->get('booking.max_advance_days', 365);

        if ($stay->nights() > $maxNights) {
            throw new BookingException("Stays are limited to {$maxNights} nights.", 'check_out_date');
        }

        if ($stay->checkIn->greaterThan(CarbonImmutable::today()->addDays($maxAdvance))) {
            throw new BookingException("Reservations can be made up to {$maxAdvance} days in advance.", 'check_in_date');
        }

        if ($ratePlan !== null && $stay->nights() < $ratePlan->min_nights) {
            throw new BookingException("The {$ratePlan->name} rate requires a minimum stay of {$ratePlan->min_nights} nights.", 'rate_plan_id');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Reservation $reservation): array
    {
        return [
            'check_in' => $reservation->check_in_date->toDateString(),
            'check_out' => $reservation->check_out_date->toDateString(),
            'adults' => $reservation->adults,
            'children' => $reservation->children,
            'total_minor' => $reservation->total_minor,
            'currency' => $reservation->currency->value,
        ];
    }
}
