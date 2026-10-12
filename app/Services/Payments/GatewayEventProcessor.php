<?php

namespace App\Services\Payments;

use App\Enums\Currency;
use App\Enums\GatewayEventStatus;
use App\Enums\PaymentMethod;
use App\Models\PaymentGatewayEvent;
use App\Models\Reservation;
use App\Services\Billing\PaymentService;
use App\Services\Booking\BookingException;
use App\Services\Currency\MissingExchangeRateException;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Applies a stored gateway event. Successful payments are recorded on the
 * reservation's folio through the normal payment service, keyed by the
 * gateway event so a retry or redelivery can never post the money twice.
 */
class GatewayEventProcessor
{
    public function __construct(private PaymentService $payments) {}

    public function process(PaymentGatewayEvent $event): PaymentGatewayEvent
    {
        if ($event->status === GatewayEventStatus::Processed || $event->status === GatewayEventStatus::Ignored) {
            return $event;
        }

        $event->increment('attempts');

        try {
            match ($event->type) {
                GatewayEvent::PAYMENT_SUCCEEDED => $this->recordPayment($event),
                default => $this->finish($event, GatewayEventStatus::Ignored, "Event type \"{$event->type}\" needs no action."),
            };
        } catch (BookingException|InvalidArgumentException|MissingExchangeRateException $exception) {
            $this->finish($event, GatewayEventStatus::Failed, $exception->getMessage());
            Log::warning('Payment gateway event could not be applied.', ['gateway' => $event->gateway, 'event_id' => $event->event_id, 'reason' => $exception->getMessage()]);
        }

        return $event->refresh();
    }

    private function recordPayment(PaymentGatewayEvent $event): void
    {
        $data = is_array($event->payload['data'] ?? null) ? $event->payload['data'] : [];

        $reservation = Reservation::query()->with('folio')->where('reference', $event->reservation_reference ?? '')->first()
            ?? throw new BookingException("No reservation matches reference \"{$event->reservation_reference}\".", 'reservation');

        $folio = $reservation->folio ?? throw new BookingException("Reservation {$reservation->reference} has no folio.", 'folio');

        $currency = Currency::tryFrom(strtoupper((string) ($data['currency'] ?? '')))
            ?? throw new InvalidArgumentException('The payment currency is missing or not supported.');

        $amount = Money::of((string) ($data['amount'] ?? ''), $currency);

        if (blank($event->transaction_reference)) {
            throw new InvalidArgumentException('The gateway did not send a transaction reference.');
        }

        $method = PaymentMethod::tryFrom((string) config("hms.payments.gateways.{$event->gateway}.method", PaymentMethod::Card->value)) ?? PaymentMethod::Card;

        $payment = $this->payments->record(
            folio: $folio,
            method: $method,
            amount: $amount,
            externalReference: $event->transaction_reference,
            notes: "Online payment via {$event->gateway}",
            idempotencyKey: "gateway:{$event->gateway}:{$event->event_id}",
            gateway: $event->gateway,
        );

        $event->forceFill(['payment_id' => $payment->id])->save();
        $this->finish($event, GatewayEventStatus::Processed, null);
    }

    private function finish(PaymentGatewayEvent $event, GatewayEventStatus $status, ?string $message): void
    {
        $event->forceFill([
            'status' => $status,
            'error' => $status === GatewayEventStatus::Failed ? $message : null,
            'processed_at' => $status === GatewayEventStatus::Failed ? null : now(),
        ])->save();
    }
}
