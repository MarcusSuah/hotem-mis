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
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Money received from a guest. Amounts are never edited; only a pending
 * payment may move to completed or failed.
 *
 * @property int $id
 * @property string $reference
 * @property int $folio_id
 * @property PaymentMethod $method
 * @property TransactionStatus $status
 * @property int $amount_minor
 * @property Currency $currency
 * @property int $applied_minor
 * @property int|null $exchange_rate_id
 * @property string|null $exchange_rate
 * @property string|null $external_reference
 * @property string|null $gateway
 * @property string|null $notes
 * @property string|null $failure_reason
 * @property int|null $received_by
 * @property CarbonImmutable $received_at
 * @property string|null $idempotency_key
 */
#[Fillable([
    'reference', 'folio_id', 'method', 'status', 'amount_minor', 'currency', 'applied_minor', 'exchange_rate_id',
    'exchange_rate', 'external_reference', 'gateway', 'notes', 'received_by', 'received_at', 'idempotency_key',
])]
class Payment extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            $locked = ['folio_id', 'amount_minor', 'currency', 'applied_minor', 'exchange_rate', 'method'];

            if ($payment->isDirty($locked) || ($payment->isDirty('status') && $payment->getOriginal('status') !== TransactionStatus::Pending)) {
                throw new LogicException('Recorded payments are immutable.');
            }
        });

        static::deleting(fn () => throw new LogicException('Payments cannot be deleted; record a refund instead.'));
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by')->withTrashed();
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    /**
     * Amount still refundable, in the payment currency.
     */
    public function refundable(): Money
    {
        $refunded = $this->relationLoaded('refunds')
            ? (int) $this->refunds->where('status', TransactionStatus::Completed)->sum('amount_minor')
            : (int) $this->refunds()->where('status', TransactionStatus::Completed->value)->sum('amount_minor');

        return Money::ofMinor(max(0, $this->amount_minor - $refunded), $this->currency);
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
            'exchange_rate' => 'decimal:8',
            'received_at' => 'immutable_datetime',
        ];
    }
}
