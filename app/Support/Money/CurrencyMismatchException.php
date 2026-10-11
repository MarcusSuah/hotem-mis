<?php

namespace App\Support\Money;

use App\Enums\Currency;
use LogicException;

class CurrencyMismatchException extends LogicException
{
    public function __construct(Currency $expected, Currency $actual)
    {
        parent::__construct("Cannot combine {$expected->value} with {$actual->value} without an explicit conversion.");
    }
}
