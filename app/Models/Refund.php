<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property string $reference
 * @property int $payment_id
 * @property int $folio_id
 * @property int $amount_minor
 * @property Currency $currency
 * @property int $applied_minor
 * @property PaymentMethod $method
 * @property TransactionStatus $status
 * @property string $reason
 * @property string|null $external_reference
 * @property int|null $processed_by
 * @property CarbonImmutable $processed_at
 * @property string|null $idempotency_key
 */
#[Fillable([
    'reference', 'payment_id', 'folio_id', 'amount_minor', 'currency', 'applied_minor', 'method', 'status',
    'reason', 'external_reference', 'processed_by', 'processed_at', 'idempotency_key',
])]
class Refund extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Refund $refund): void {
            if ($refund->isDirty(['amount_minor', 'applied_minor', 'currency', 'payment_id', 'folio_id'])) {
                throw new LogicException('Recorded refunds are immutable.');
            }
        });

        static::deleting(fn () => throw new LogicException('Refunds cannot be deleted.'));
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by')->withTrashed();
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => TransactionStatus::class,
            'currency' => Currency::class,
            'amount_minor' => 'integer',
            'applied_minor' => 'integer',
            'processed_at' => 'immutable_datetime',
        ];
    }
}
