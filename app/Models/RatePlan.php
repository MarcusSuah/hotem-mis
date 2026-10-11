<?php

namespace App\Models;

use Database\Factories\RatePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int $cancellation_policy_id
 * @property bool $is_refundable
 * @property string $adjustment_percent
 * @property bool $includes_breakfast
 * @property int $min_nights
 * @property string|null $deposit_percent
 * @property bool $is_public
 * @property bool $is_active
 * @property bool $is_default
 * @property int $display_order
 */
#[Fillable([
    'name', 'code', 'description', 'cancellation_policy_id', 'is_refundable', 'adjustment_percent',
    'includes_breakfast', 'min_nights', 'deposit_percent', 'is_public', 'is_active', 'is_default', 'display_order',
])]
class RatePlan extends Model
{
    /** @use HasFactory<RatePlanFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CancellationPolicy, $this>
     */
    public function cancellationPolicy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class);
    }

    /**
     * @param  Builder<RatePlan>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'is_refundable' => 'boolean',
            'adjustment_percent' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'includes_breakfast' => 'boolean',
            'min_nights' => 'integer',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'display_order' => 'integer',
        ];
    }
}
