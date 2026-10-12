<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\ServiceOrderStatus;
use App\Enums\Settlement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $reference
 * @property int $additional_service_id
 * @property int|null $reservation_id
 * @property string|null $customer_name
 * @property int $quantity
 * @property Currency $currency
 * @property int $unit_price_minor
 * @property int $total_minor
 * @property CarbonImmutable|null $scheduled_for
 * @property ServiceOrderStatus $status
 * @property Settlement|null $settlement
 * @property PaymentMethod|null $payment_method
 * @property string|null $payment_reference
 * @property int|null $folio_charge_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $completed_at
 * @property string|null $idempotency_key
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['reference', 'additional_service_id', 'reservation_id', 'customer_name', 'quantity', 'currency', 'unit_price_minor', 'total_minor', 'scheduled_for', 'status', 'settlement', 'payment_method', 'payment_reference', 'notes', 'created_by', 'idempotency_key'])]
class ServiceOrder extends Model
{
    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<AdditionalService, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(AdditionalService::class, 'additional_service_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
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
            'status' => ServiceOrderStatus::class,
            'currency' => Currency::class,
            'settlement' => Settlement::class,
            'payment_method' => PaymentMethod::class,
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'total_minor' => 'integer',
            'scheduled_for' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
