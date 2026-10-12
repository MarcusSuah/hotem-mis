<?php

namespace App\Services\Payments\Gateways;

use App\Services\Payments\GatewayEvent;
use App\Services\Payments\InvalidGatewayPayload;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\Request;

/**
 * A provider-neutral webhook format signed with a shared secret.
 *
 * Header:  X-HMS-Signature: t=<unix seconds>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">
 * Body:    {"id": "...", "type": "payment.succeeded", "data": {"reservation_reference": "...",
 *           "transaction_id": "...", "amount": "150.00", "currency": "USD"}}
 *
 * Use it for a payment partner or middleware that can sign requests, or as
 * the model for a provider-specific adapter.
 */
class SignedWebhookGateway implements PaymentGateway
{
    public function __construct(
        private string $name,
        private string $secret,
        private int $toleranceSeconds = 300,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function verify(Request $request): bool
    {
        $header = (string) $request->header('X-HMS-Signature', '');
        $parts = [];

        foreach (explode(',', $header) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$key] = $value;
        }

        $timestamp = ctype_digit($parts['t'] ?? '') ? (int) $parts['t'] : 0;

        if ($this->secret === '' || $timestamp === 0 || abs(now()->getTimestamp() - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $this->secret);

        return hash_equals($expected, (string) ($parts['v1'] ?? ''));
    }

    public function parse(Request $request): GatewayEvent
    {
        $body = json_decode($request->getContent(), true);

        if (! is_array($body) || ! is_string($body['id'] ?? null) || ! is_string($body['type'] ?? null) || $body['id'] === '' || strlen($body['id']) > 128) {
            throw new InvalidGatewayPayload('The webhook body must contain an "id" and a "type".');
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $text = fn (string $key, int $max): ?string => is_scalar($data[$key] ?? null) && (string) $data[$key] !== '' ? mb_substr((string) $data[$key], 0, $max) : null;

        return new GatewayEvent(
            id: $body['id'],
            type: mb_substr($body['type'], 0, 64),
            payload: $body,
            reservationReference: $text('reservation_reference', 32),
            transactionReference: $text('transaction_id', 128),
            amount: $text('amount', 20),
            currency: $text('currency', 3),
        );
    }
}
