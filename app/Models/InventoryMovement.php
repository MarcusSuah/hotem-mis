<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Immutable stock ledger line.
 */
/**
 * @property int $id
 * @property int $inventory_item_id
 * @property InventoryMovementType $type
 * @property string $quantity
 * @property string $balance_after
 * @property int|null $unit_cost_minor
 * @property string|null $reference
 * @property string|null $source_type
 * @property int|null $source_id
 * @property CarbonImmutable|null $expires_on
 * @property string|null $notes
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 */
#[Fillable(['inventory_item_id', 'type', 'quantity', 'balance_after', 'unit_cost_minor', 'reference', 'source_type', 'source_id', 'expires_on', 'notes', 'user_id'])]
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Ledger entries cannot be deleted.'));
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost_minor' => 'integer',
            'expires_on' => 'immutable_date',
            'created_at' => 'immutable_datetime',
        ];
    }
}
