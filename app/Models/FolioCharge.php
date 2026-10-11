<?php

namespace App\Models;

use App\Enums\ChargeCategory;
use App\Enums\Currency;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * An immutable folio line. Mistakes are corrected with a reversing line.
 *
 * @property int $id
 * @property int $folio_id
 * @property ChargeCategory $category
 * @property string $description
 * @property int $quantity
 * @property int $unit_amount_minor
 * @property int $amount_minor
 * @property Currency $currency
 * @property CarbonImmutable $service_date
 * @property int|null $reversal_of_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $idempotency_key
 * @property int|null $posted_by
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'folio_id', 'category', 'description', 'quantity', 'unit_amount_minor', 'amount_minor', 'currency',
    'service_date', 'reversal_of_id', 'source_type', 'source_id', 'idempotency_key', 'posted_by', 'note',
])]
class FolioCharge extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Folio charges are immutable; post a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Folio charges cannot be deleted; post a reversal instead.'));
    }

    /**
     * @return BelongsTo<Folio, $this>
     */
    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    /**
     * @return BelongsTo<FolioCharge, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * @return HasOne<FolioCharge, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by')->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'category' => ChargeCategory::class,
            'currency' => Currency::class,
            'service_date' => 'immutable_date',
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'amount_minor' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
