<?php

namespace App\Models;

use App\Enums\GatewayEventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A webhook received from a payment gateway. The (gateway, event_id) pair
 * is unique, so a redelivered event can never be applied twice.
 *
 * @property int $id
 * @property string $gateway
 * @property string $event_id
 * @property string $type
 * @property GatewayEventStatus $status
 * @property string|null $reservation_reference
 * @property string|null $transaction_reference
 * @property int|null $payment_id
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property string|null $error
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['gateway', 'event_id', 'type', 'reservation_reference', 'transaction_reference', 'payload'])]
class PaymentGatewayEvent extends Model
{
    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => GatewayEventStatus::class,
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
