<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $maintenance_request_id
 * @property int|null $inventory_item_id
 * @property string $description
 * @property string $quantity
 * @property int $unit_cost_minor
 * @property int|null $added_by
 * @property CarbonImmutable $created_at
 */
#[Fillable(['maintenance_request_id', 'inventory_item_id', 'description', 'quantity', 'unit_cost_minor', 'added_by'])]
class MaintenancePart extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<MaintenanceRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id')->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost_minor' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
