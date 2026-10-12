<?php

namespace App\Models;

use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A stock-keeping unit. `quantity` is a cached balance changed only through movements.
 *
 * @property int $id
 * @property int $inventory_category_id
 * @property int|null $preferred_supplier_id
 * @property string $sku
 * @property string $name
 * @property string $unit
 * @property string $quantity
 * @property string $reorder_level
 * @property Currency $currency
 * @property int $average_cost_minor
 * @property bool $tracks_expiry
 * @property bool $is_active
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['inventory_category_id', 'preferred_supplier_id', 'sku', 'name', 'unit', 'reorder_level', 'currency', 'tracks_expiry', 'is_active'])]
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<InventoryCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function preferredSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'preferred_supplier_id')->withTrashed();
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest('id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reorder_level' => 'decimal:3',
            'currency' => Currency::class,
            'average_cost_minor' => 'integer',
            'tracks_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
