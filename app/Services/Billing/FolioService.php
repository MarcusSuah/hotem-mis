<?php

namespace App\Services\Billing;

use App\Enums\ChargeCategory;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TransactionStatus;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingException;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The guest folio is an append-only ledger:
 *
 *   balance = charges − completed payments + completed refunds
 *
 * Every posting is idempotent when given a key, so retried requests and
 * re-run night audits never double-charge a guest.
 */
class FolioService
{
    public function __construct(private AuditLogger $audit) {}

    public function openFor(Reservation $reservation): Folio
    {
        return Folio::query()->firstOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'number' => 'F-'.$reservation->reference,
                'guest_id' => $reservation->guest_id,
                'currency' => $reservation->currency,
                'status' => 'open',
            ],
        );
    }

    /**
     * Post a charge (positive) or credit (negative) to the folio.
     */
    public function post(
        Folio $folio,
        ChargeCategory $category,
        string $description,
        Money $unitAmount,
        int $quantity = 1,
        ?CarbonImmutable $serviceDate = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?Model $source = null,
        ?string $note = null,
    ): FolioCharge {
        if ($idempotencyKey !== null) {
            $existing = FolioCharge::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        if (! $folio->isOpen()) {
            throw new BookingException("Folio {$folio->number} is closed; no further charges can be posted.", 'folio');
        }

        if ($unitAmount->currency !== $folio->currency) {
            throw new BookingException("Charges on this folio must be in {$folio->currency->value}.", 'amount');
        }

        if ($quantity < 1) {
            throw new BookingException('Quantity must be at least 1.', 'quantity');
        }

        try {
            $charge = FolioCharge::create([
                'folio_id' => $folio->id,
                'category' => $category,
                'description' => $description,
                'quantity' => $quantity,
                'unit_amount_minor' => $unitAmount->minor,
                'amount_minor' => $unitAmount->multiply($quantity)->minor,
                'currency' => $folio->currency,
                'service_date' => $serviceDate ?? CarbonImmutable::today(),
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'idempotency_key' => $idempotencyKey,
                'posted_by' => $actor?->id,
                'note' => $note,
            ]);
        } catch (UniqueConstraintViolationException) {
            return FolioCharge::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        $this->syncReservation($folio);

        if (! in_array($category, [ChargeCategory::Room, ChargeCategory::ServiceCharge, ChargeCategory::Tax], true)) {
            $this->audit->record('folio.charge_posted', $folio, newValues: [
                'charge_id' => $charge->id,
                'category' => $category->value,
                'description' => $description,
                'amount' => $charge->amount()->format(),
            ], actor: $actor);
        }

        return $charge;
    }

    /**
     * Cancel a posted line by posting its exact negative. A line can be reversed once.
     */
    public function reverse(FolioCharge $charge, string $reason, ?User $actor = null): FolioCharge
    {
        if ($charge->reversal_of_id !== null) {
            throw new BookingException('A reversal cannot itself be reversed; post a new charge instead.', 'charge');
        }

        if ($charge->reversal()->exists()) {
            throw new BookingException('This charge has already been reversed.', 'charge');
        }

        return DB::transaction(function () use ($charge, $reason, $actor): FolioCharge {
            $folio = $charge->folio;

            if (! $folio->isOpen()) {
                throw new BookingException("Folio {$folio->number} is closed.", 'folio');
            }

            $reversal = FolioCharge::create([
                'folio_id' => $folio->id,
                'category' => $charge->category,
                'description' => 'Reversal: '.$charge->description,
                'quantity' => $charge->quantity,
                'unit_amount_minor' => -$charge->unit_amount_minor,
                'amount_minor' => -$charge->amount_minor,
                'currency' => $charge->currency,
                'service_date' => $charge->service_date,
                'reversal_of_id' => $charge->id,
                'posted_by' => $actor?->id,
                'note' => $reason,
            ]);

            $this->syncReservation($folio);
            $this->audit->record('folio.charge_reversed', $folio, ['charge_id' => $charge->id, 'amount' => $charge->amount()->format()], [
                'reversal_id' => $reversal->id,
                'reason' => $reason,
            ], $actor);

            return $reversal;
        });
    }

    /**
     * Post accommodation, service charge and tax for every held night before
     * `$until` (exclusive). Safe to call repeatedly.
     */
    public function postStayNights(Reservation $reservation, CarbonImmutable $until, ?User $actor = null): int
    {
        $folio = $this->openFor($reservation);
        $posted = 0;

        $nights = $reservation->nightRecords()
            ->with('room:id,number')
            ->where('night_date', '<', $until->toDateString())
            ->get();

        foreach ($nights as $night) {
            $date = $night->night_date;
            $key = "stay:{$reservation->id}:{$date->toDateString()}";
            $room = $night->room?->number;

            $lines = [
                [ChargeCategory::Room, "Room {$room} — night of {$date->format('d M Y')}", $night->rate_minor, 'room'],
                [ChargeCategory::ServiceCharge, "Service charge — {$date->format('d M Y')}", $night->service_charge_minor, 'service'],
                [ChargeCategory::Tax, "Tax — {$date->format('d M Y')}", $night->tax_minor, 'tax'],
            ];

            foreach ($lines as [$category, $description, $minor, $suffix]) {
                if ($minor === 0 && $category !== ChargeCategory::Room) {
                    continue;
                }

                $alreadyPosted = FolioCharge::query()->where('idempotency_key', "{$key}:{$suffix}")->exists();

                $this->post($folio, $category, $description, Money::ofMinor($minor, $folio->currency), 1, $date, $actor, "{$key}:{$suffix}");

                $posted += $alreadyPosted ? 0 : 1;
            }
        }

        return $posted;
    }

    public function summary(Folio $folio): FolioSummary
    {
        $currency = $folio->currency;

        $byCategory = FolioCharge::query()
            ->where('folio_id', $folio->id)
            ->selectRaw('category, sum(amount_minor) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($total) => Money::ofMinor((int) $total, $currency))
            ->all();

        $charges = Money::sum($byCategory, $currency);
        $payments = Money::ofMinor((int) $folio->payments()->where('status', TransactionStatus::Completed->value)->sum('applied_minor'), $currency);
        $refunds = Money::ofMinor((int) $folio->refunds()->where('status', TransactionStatus::Completed->value)->sum('applied_minor'), $currency);

        return new FolioSummary(
            charges: $charges,
            payments: $payments,
            refunds: $refunds,
            balance: $charges->subtract($payments)->add($refunds),
            byCategory: $byCategory,
        );
    }

    /**
     * Amount the guest is expected to pay: the agreed stay total until the
     * stay is completed, then whatever has actually been charged.
     */
    public function amountDue(Reservation $reservation, FolioSummary $summary): Money
    {
        $agreed = Money::ofMinor($reservation->total_minor, $reservation->currency);

        if (in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true)) {
            return $summary->charges->greaterThan($agreed) ? $summary->charges : $agreed;
        }

        return $summary->charges;
    }

    /**
     * Keep the reservation's paid amount and payment status in step with the ledger.
     */
    public function syncReservation(Folio $folio): void
    {
        $reservation = $folio->reservation;

        if ($reservation === null) {
            return;
        }

        $summary = $this->summary($folio);
        $netPaid = $summary->netPaid();
        $due = $this->amountDue($reservation, $summary);

        $status = match (true) {
            $summary->refunds->isPositive() && ! $netPaid->isPositive() => PaymentStatus::Refunded,
            $summary->refunds->isPositive() => PaymentStatus::PartiallyRefunded,
            ! $netPaid->isPositive() => PaymentStatus::Unpaid,
            ! $netPaid->lessThan($due) => PaymentStatus::Paid,
            default => PaymentStatus::PartiallyPaid,
        };

        $reservation->forceFill([
            'amount_paid_minor' => $netPaid->minor,
            'payment_status' => $status,
        ])->save();
    }

    public function close(Folio $folio): void
    {
        $folio->forceFill(['status' => 'closed', 'closed_at' => now()])->save();
    }
}
