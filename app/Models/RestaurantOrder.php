<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\Settlement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $reference
 * @property OrderType $type
 * @property OrderStatus $status
 * @property int|null $reservation_id
 * @property string|null $table_or_room
 * @property string|null $customer_name
 * @property Currency $currency
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property Settlement|null $settlement
 * @property PaymentMethod|null $payment_method
 * @property string|null $payment_reference
 * @property int|null $folio_charge_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $closed_by
 * @property CarbonImmutable|null $closed_at
 * @property string|null $idempotency_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reference', 'type', 'status', 'reservation_id', 'table_or_room', 'customer_name', 'currency', 'subtotal_minor', 'tax_minor', 'total_minor', 'settlement', 'payment_method', 'payment_reference', 'notes', 'created_by', 'idempotency_key'])]
class RestaurantOrder extends Model
{
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return HasMany<RestaurantOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RestaurantOrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<FolioCharge, $this>
     */
    public function folioCharge(): BelongsTo
    {
        return $this->belongsTo(FolioCharge::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'currency' => Currency::class,
            'settlement' => Settlement::class,
            'payment_method' => PaymentMethod::class,
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
