<?php

namespace App\Services\Currency;

use App\Models\ExchangeRate;
use App\Support\Money\Money;

final readonly class ConversionResult
{
    /**
     * @param  string|null  $rate  Units of the target currency per unit of the source currency.
     */
    public function __construct(
        public Money $original,
        public Money $converted,
        public ?string $rate,
        public ?ExchangeRate $exchangeRate,
    ) {}

    public function wasConverted(): bool
    {
        return $this->rate !== null;
    }
}
