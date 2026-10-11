<?php

namespace App\Services\Currency;

use App\Enums\Currency;
use RuntimeException;

class MissingExchangeRateException extends RuntimeException
{
    public function __construct(Currency $from, Currency $to)
    {
        parent::__construct("No exchange rate is configured for {$from->value} to {$to->value}.");
    }
}
