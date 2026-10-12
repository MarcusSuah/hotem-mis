<?php

namespace App\Services\Payments;

/**
 * A gateway notification translated into the hotel's own vocabulary.
 */
final readonly class GatewayEvent
{
    public const PAYMENT_SUCCEEDED = 'payment.succeeded';

    public const PAYMENT_FAILED = 'payment.failed';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $payload,
        public ?string $reservationReference = null,
        public ?string $transactionReference = null,
        public ?string $amount = null,
        public ?string $currency = null,
    ) {}
}
