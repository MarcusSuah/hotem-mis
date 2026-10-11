<?php

namespace App\Models;

use App\Enums\ChargeCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int|null $folio_charge_id
 * @property ChargeCategory $category
 * @property string $description
 * @property CarbonImmutable $service_date
 * @property int $quantity
 * @property int $unit_amount_minor
 * @property int $amount_minor
 * @property int $sort_order
 */
#[Fillable(['invoice_id', 'folio_charge_id', 'category', 'description', 'service_date', 'quantity', 'unit_amount_minor', 'amount_minor', 'sort_order'])]
class InvoiceItem extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'category' => ChargeCategory::class,
            'service_date' => 'immutable_date',
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'amount_minor' => 'integer',
        ];
    }
}
