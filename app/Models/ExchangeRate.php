<?php

namespace App\Models;

use App\Enums\Currency;
use Carbon\CarbonImmutable;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One unit of the base currency equals `rate` units of the quote currency.
 * Rows are append-only so historical transactions keep the rate they used.
 *
 * @property int $id
 * @property Currency $base_currency
 * @property Currency $quote_currency
 * @property string $rate
 * @property CarbonImmutable $effective_at
 * @property int|null $created_by
 * @property string|null $notes
 */
#[Fillable(['base_currency', 'quote_currency', 'rate', 'effective_at', 'created_by', 'notes'])]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_currency' => Currency::class,
            'quote_currency' => Currency::class,
            'rate' => 'decimal:8',
            'effective_at' => 'datetime',
        ];
    }
}
