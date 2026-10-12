<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PurchaseOrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property int $supplier_id
 * @property PurchaseOrderStatus $status
 * @property Currency $currency
 * @property int $total_minor
 * @property CarbonImmutable|null $expected_on
 * @property CarbonImmutable|null $ordered_at
 * @property CarbonImmutable|null $received_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['number', 'supplier_id', 'status', 'currency', 'total_minor', 'expected_on', 'notes', 'created_by'])]
class PurchaseOrder extends Model
{
    public function getRouteKeyName(): string
    {
        return 'number';
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'currency' => Currency::class,
            'total_minor' => 'integer',
            'expected_on' => 'immutable_date',
            'ordered_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
        ];
    }
}
