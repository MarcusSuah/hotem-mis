<?php

namespace App\Services\Billing;

use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\TransactionStatus;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingException;
use App\Services\Booking\ReservationService;
use App\Services\Currency\CurrencyConverter;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records money received and returned. A payment made in a currency other
 * than the folio's is converted with the current rate, and that rate is
 * stored with the payment so the applied amount never changes afterwards.
 */
class PaymentService
{
    public function __construct(
        private FolioService $folios,
        private CurrencyConverter $converter,
        private ReservationService $reservations,
        private AuditLogger $audit,
    ) {}

    public function record(
        Folio $folio,
        PaymentMethod $method,
        Money $amount,
        ?User $actor = null,
        ?string $externalReference = null,
        ?string $notes = null,
        ?string $idempotencyKey = null,
        ?CarbonImmutable $receivedAt = null,
    ): Payment {
        if ($idempotencyKey !== null && ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        if (! $amount->isPositive()) {
            throw new BookingException('The payment amount must be greater than zero.', 'amount');
        }

        if ($method->requiresReference() && blank($externalReference)) {
            throw new BookingException("Enter the {$method->label()} transaction reference.", 'external_reference');
        }

        $conversion = $this->converter->convert($amount, $folio->currency);

        try {
            $payment = DB::transaction(function () use ($folio, $method, $amount, $actor, $externalReference, $notes, $idempotencyKey, $receivedAt, $conversion): Payment {
                $payment = Payment::create([
                    'reference' => $this->reference('PAY'),
                    'folio_id' => $folio->id,
                    'method' => $method,
                    'status' => TransactionStatus::Completed,
                    'amount_minor' => $amount->minor,
                    'currency' => $amount->currency,
                    'applied_minor' => $conversion->converted->minor,
                    'exchange_rate_id' => $conversion->exchangeRate?->id,
                    'exchange_rate' => $conversion->rate,
                    'external_reference' => $externalReference,
                    'notes' => $notes,
                    'received_by' => $actor?->id,
                    'received_at' => $receivedAt ?? now(),
                    'idempotency_key' => $idempotencyKey,
                ]);

                $this->folios->syncReservation($folio);

                $this->audit->record('payment.recorded', $payment, newValues: [
                    'folio' => $folio->number,
                    'method' => $method->value,
                    'amount' => $amount->format(),
                    'applied' => $conversion->converted->format(),
                    'exchange_rate' => $conversion->rate,
                    'external_reference' => $externalReference,
                ], actor: $actor);

                return $payment;
            });
        } catch (UniqueConstraintViolationException) {
            return Payment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        $this->confirmWhenDepositCovered($folio, $actor);

        return $payment;
    }

    /**
     * Return money from a completed payment, in the payment's own currency.
     */
    public function refund(
        Payment $payment,
        Money $amount,
        string $reason,
        PaymentMethod $method,
        ?User $actor = null,
        ?string $externalReference = null,
        ?string $idempotencyKey = null,
    ): Refund {
        if ($idempotencyKey !== null && ($existing = Refund::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        if ($payment->status !== TransactionStatus::Completed) {
            throw new BookingException('Only completed payments can be refunded.', 'payment');
        }

        if ($amount->currency !== $payment->currency) {
            throw new BookingException("Refunds of this payment must be in {$payment->currency->value}.", 'amount');
        }

        if (! $amount->isPositive()) {
            throw new BookingException('The refund amount must be greater than zero.', 'amount');
        }

        return DB::transaction(function () use ($payment, $amount, $reason, $method, $actor, $externalReference, $idempotencyKey): Refund {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $refundable = $locked->refundable();

            if ($amount->greaterThan($refundable)) {
                throw new BookingException("At most {$refundable->format()} can be refunded from this payment.", 'amount');
            }

            $folio = $locked->folio;
            $applied = $locked->exchange_rate === null
                ? $amount
                : $this->converter->convertWithSnapshot($amount, $folio->currency, (string) $locked->exchange_rate);

            $refund = Refund::create([
                'reference' => $this->reference('RFD'),
                'payment_id' => $locked->id,
                'folio_id' => $folio->id,
                'amount_minor' => $amount->minor,
                'currency' => $amount->currency,
                'applied_minor' => $applied->minor,
                'method' => $method,
                'status' => TransactionStatus::Completed,
                'reason' => $reason,
                'external_reference' => $externalReference,
                'processed_by' => $actor?->id,
                'processed_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->folios->syncReservation($folio);

            $this->audit->record('payment.refunded', $locked, newValues: [
                'refund' => $refund->reference,
                'amount' => $amount->format(),
                'reason' => $reason,
            ], actor: $actor);

            return $refund;
        });
    }

    /**
     * A pending reservation becomes confirmed once its required deposit is paid.
     */
    private function confirmWhenDepositCovered(Folio $folio, ?User $actor): void
    {
        $reservation = $folio->reservation()->first();

        if ($reservation === null || $reservation->status !== ReservationStatus::Pending) {
            return;
        }

        if ($reservation->amount_paid_minor >= $reservation->deposit_due_minor) {
            $this->reservations->confirm($reservation, $actor, 'Confirmed automatically: deposit received');
        }
    }

    private function reference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.Str::upper(Str::random(8));
        } while (Payment::query()->where('reference', $reference)->exists() || Refund::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
