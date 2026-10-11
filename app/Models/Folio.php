<?php

namespace App\Models;

use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property int|null $reservation_id
 * @property int $guest_id
 * @property Currency $currency
 * @property string $status
 * @property CarbonImmutable|null $closed_at
 */
#[Fillable(['number', 'reservation_id', 'guest_id', 'currency', 'status'])]
class Folio extends Model
{
    public function getRouteKeyName(): string
    {
        return 'number';
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
     * @return HasMany<FolioCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(FolioCharge::class)->orderBy('service_date')->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('received_at')->orderBy('id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('processed_at')->orderBy('id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('issued_at')->orderByDesc('id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'closed_at' => 'immutable_datetime',
        ];
    }
}
