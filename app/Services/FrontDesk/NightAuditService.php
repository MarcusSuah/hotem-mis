<?php

namespace App\Services\FrontDesk;

use App\Enums\ChargeCategory;
use App\Enums\ReservationStatus;
use App\Enums\TransactionStatus;
use App\Models\FolioCharge;
use App\Models\NightAudit;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\FolioService;
use App\Services\Booking\BookingException;
use App\Services\Booking\CancellationCalculator;
use App\Services\Booking\ReservationService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Closes a business day: posts that night's room, service and tax charges
 * for every in-house guest, optionally marks unarrived guests as no-shows,
 * freezes a summary report and advances the business date.
 */
class NightAuditService
{
    public function __construct(
        private FolioService $folios,
        private ReservationService $reservations,
        private SettingsRepository $settings,
        private AuditLogger $audit,
        private CancellationCalculator $cancellations,
    ) {}

    public function businessDate(): CarbonImmutable
    {
        $stored = $this->settings->get('operations.business_date');

        return $stored ? CarbonImmutable::parse((string) $stored)->startOfDay() : CarbonImmutable::today();
    }

    /**
     * Live preview of what the audit for the current business date will do.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        $date = $this->businessDate();

        return [
            'business_date' => $date->toDateString(),
            'in_house' => Reservation::query()->where('status', ReservationStatus::CheckedIn->value)->count(),
            'pending_arrivals' => Reservation::query()
                ->where('status', ReservationStatus::Confirmed->value)
                ->whereDate('check_in_date', '<=', $date)
                ->with(['guest:id,first_name,last_name', 'activeAllocations.room:id,number'])
                ->orderBy('check_in_date')
                ->get()
                ->map(fn (Reservation $reservation) => [
                    'reference' => $reservation->reference,
                    'guest' => $reservation->guest->full_name,
                    'phone' => $reservation->contact_phone,
                    'room' => $reservation->activeAllocations->first()?->room?->number,
                    'nights' => $reservation->nights,
                    'source' => $reservation->source->label(),
                    'check_in_date' => $reservation->check_in_date->toDateString(),
                    'no_show_fee' => $this->cancellations->noShowFee($reservation),
                    'deposit_paid' => Money::ofMinor($reservation->amount_paid_minor, $reservation->currency),
                ]),
            'overdue_departures' => Reservation::query()
                ->where('status', ReservationStatus::CheckedIn->value)
                ->whereDate('check_out_date', '<=', $date)
                ->pluck('reference'),
            'can_run' => $date->lessThanOrEqualTo(CarbonImmutable::today()),
        ];
    }

    /**
     * Close the business day. Guests who have not arrived are only marked as
     * no-shows when named individually; everyone else stays expected.
     *
     * @param  list<string>  $noShowReferences
     */
    public function run(?User $actor = null, array $noShowReferences = []): NightAudit
    {
        $date = $this->businessDate();

        if ($date->greaterThan(CarbonImmutable::today())) {
            throw new BookingException("The audit for {$date->format('d M Y')} can only run on or after that date.", 'business_date');
        }

        if (NightAudit::query()->whereDate('business_date', $date)->exists()) {
            throw new BookingException("The business day {$date->format('d M Y')} has already been closed.", 'business_date');
        }

        return DB::transaction(function () use ($date, $actor, $noShowReferences): NightAudit {
            $inHouse = Reservation::query()
                ->where('status', ReservationStatus::CheckedIn->value)
                ->orderBy('id')
                ->get();

            $postedLines = 0;

            foreach ($inHouse as $reservation) {
                $postedLines += $this->folios->postStayNights($reservation, $date->addDay(), $actor);
            }

            $noShows = [];

            if ($noShowReferences !== []) {
                $unarrived = Reservation::query()
                    ->where('status', ReservationStatus::Confirmed->value)
                    ->whereDate('check_in_date', '<=', $date)
                    ->whereIn('reference', $noShowReferences)
                    ->orderBy('id')
                    ->get();

                $unknown = array_diff($noShowReferences, $unarrived->pluck('reference')->all());

                if ($unknown !== []) {
                    throw new BookingException('These bookings are not awaiting arrival and cannot be marked as no-shows: '.implode(', ', $unknown).'.', 'no_shows');
                }

                foreach ($unarrived as $reservation) {
                    $this->reservations->markNoShow($reservation, $actor);
                    $noShows[] = $reservation->reference;
                }
            }

            $audit = NightAudit::create([
                'business_date' => $date,
                'run_by' => $actor?->id,
                'summary' => $this->summary($date, $inHouse->count(), $postedLines, $noShows),
                'completed_at' => now(),
            ]);

            $this->settings->set('operations.business_date', $date->addDay()->toDateString());

            $this->audit->record('night_audit.completed', $audit, newValues: [
                'business_date' => $date->toDateString(),
                'posted_lines' => $postedLines,
                'no_shows' => $noShows,
            ], actor: $actor);

            return $audit;
        });
    }

    /**
     * Frozen end-of-day figures. Amounts are reported per currency; they are
     * never added across currencies.
     *
     * @param  list<string>  $noShows
     * @return array<string, mixed>
     */
    private function summary(CarbonImmutable $date, int $inHouse, int $postedLines, array $noShows): array
    {
        $sellable = Room::query()->inService()->count();

        $charges = FolioCharge::query()
            ->whereDate('service_date', $date)
            ->selectRaw('currency, category, sum(amount_minor) as total')
            ->groupBy('currency', 'category')
            ->toBase()
            ->get()
            ->groupBy('currency')
            ->map(fn ($rows) => $rows->mapWithKeys(fn (object $row) => [(string) $row->category => (int) $row->total]));

        $payments = Payment::query()
            ->where('status', TransactionStatus::Completed->value)
            ->whereDate('received_at', $date)
            ->selectRaw('currency, method, sum(amount_minor) as total, count(*) as count')
            ->groupBy('currency', 'method')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'currency' => (string) $row->currency,
                'method' => (string) $row->method,
                'total_minor' => (int) $row->total,
                'count' => (int) $row->count,
            ]);

        return [
            'rooms_occupied' => $inHouse,
            'rooms_sellable' => $sellable,
            'occupancy_percent' => $sellable > 0 ? round($inHouse / $sellable * 100, 1) : 0,
            'arrivals' => Reservation::query()->whereDate('checked_in_at', $date)->count(),
            'departures' => Reservation::query()->whereDate('checked_out_at', $date)->count(),
            'posted_lines' => $postedLines,
            'no_shows' => $noShows,
            'charges_by_currency' => $charges,
            'room_revenue_by_currency' => $charges->map(fn ($categories) => (int) ($categories[ChargeCategory::Room->value] ?? 0)),
            'payments' => $payments,
        ];
    }
}
