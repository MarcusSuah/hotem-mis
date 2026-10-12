<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $restaurant_order_id
 * @property int|null $menu_item_id
 * @property string $name
 * @property int $quantity
 * @property int $unit_price_minor
 * @property int $amount_minor
 * @property string|null $notes
 */
#[Fillable(['restaurant_order_id', 'menu_item_id', 'name', 'quantity', 'unit_price_minor', 'amount_minor', 'notes'])]
class RestaurantOrderItem extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'restaurant_order_id');
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'amount_minor' => 'integer',
        ];
    }
}
