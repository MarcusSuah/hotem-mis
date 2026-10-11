<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Immutable snapshot of a folio. Only voiding is allowed after issue.
 *
 * @property int $id
 * @property string $number
 * @property int $folio_id
 * @property int|null $reservation_id
 * @property int $guest_id
 * @property Currency $currency
 * @property InvoiceStatus $status
 * @property int $subtotal_minor
 * @property int $service_charge_minor
 * @property int $tax_minor
 * @property int $discount_minor
 * @property int $total_minor
 * @property int $paid_minor
 * @property int $balance_minor
 * @property string|null $exchange_rate
 * @property Currency $base_currency
 * @property array<string, mixed> $billing_details
 * @property int|null $issued_by
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property int|null $replaces_invoice_id
 */
#[Fillable([
    'number', 'folio_id', 'reservation_id', 'guest_id', 'currency', 'status', 'subtotal_minor', 'service_charge_minor',
    'tax_minor', 'discount_minor', 'total_minor', 'paid_minor', 'balance_minor', 'exchange_rate', 'base_currency',
    'billing_details', 'issued_by', 'issued_at', 'replaces_invoice_id',
])]
class Invoice extends Model
{
    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            $allowed = ['status', 'voided_at', 'voided_by', 'void_reason', 'updated_at'];

            if (array_diff(array_keys($invoice->getDirty()), $allowed) !== []) {
                throw new LogicException('Issued invoices are immutable; void and reissue instead.');
            }
        });

        static::deleting(fn () => throw new LogicException('Invoices cannot be deleted; void them instead.'));
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class)->withTrashed();
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by')->withTrashed();
    }

    public function money(int $minor): Money
    {
        return Money::ofMinor($minor, $this->currency);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'base_currency' => Currency::class,
            'status' => InvoiceStatus::class,
            'billing_details' => 'array',
            'exchange_rate' => 'decimal:8',
            'issued_at' => 'immutable_datetime',
            'voided_at' => 'immutable_datetime',
            'subtotal_minor' => 'integer',
            'service_charge_minor' => 'integer',
            'tax_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'balance_minor' => 'integer',
        ];
    }
}
