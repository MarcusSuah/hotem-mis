<?php

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\MissingExchangeRateException;
use App\Support\Money\Money;

it('converts the base currency into the quote currency', function () {
    ExchangeRate::factory()->rate('190.50')->create(['effective_at' => now()->subDay()]);

    $result = app(CurrencyConverter::class)->convert(Money::of('150', Currency::USD), Currency::LRD);

    expect($result->converted->format())->toBe('LRD 28,575.00')
        ->and($result->original->format())->toBe('USD 150.00')
        ->and($result->wasConverted())->toBeTrue();
});

it('converts in the inverse direction using the reciprocal rate', function () {
    ExchangeRate::factory()->rate('200')->create(['effective_at' => now()->subDay()]);

    $result = app(CurrencyConverter::class)->convert(Money::of('25000', Currency::LRD), Currency::USD);

    expect($result->converted->format())->toBe('USD 125.00');
});

it('uses the rate that was in force at the requested moment', function () {
    ExchangeRate::factory()->rate('180')->create(['effective_at' => '2026-01-01 00:00:00']);
    ExchangeRate::factory()->rate('195')->create(['effective_at' => '2026-06-01 00:00:00']);
    $this->travelTo('2026-09-01 00:00:00');

    $converter = app(CurrencyConverter::class);

    expect($converter->convert(Money::of('10', Currency::USD), Currency::LRD, now()->setDate(2026, 3, 1))->converted->toDecimal())->toBe('1800.00')
        ->and($converter->convert(Money::of('10', Currency::USD), Currency::LRD)->converted->toDecimal())->toBe('1950.00');
});

it('reproduces historical amounts from a stored rate snapshot after the rate changes', function () {
    ExchangeRate::factory()->rate('190')->create(['effective_at' => now()->subMonth()]);
    $converter = app(CurrencyConverter::class);
    $snapshot = $converter->convert(Money::of('100', Currency::USD), Currency::LRD)->rate;

    ExchangeRate::factory()->rate('250')->create(['effective_at' => now()->subMinute()]);

    expect($converter->convertWithSnapshot(Money::of('100', Currency::USD), Currency::LRD, $snapshot)->toDecimal())
        ->toBe('19000.00');
});

it('does not report a conversion when the currencies match', function () {
    $result = app(CurrencyConverter::class)->convert(Money::of('10', Currency::USD), Currency::USD);

    expect($result->wasConverted())->toBeFalse()
        ->and($result->converted->format())->toBe('USD 10.00');
});

it('fails loudly when no rate is configured', function () {
    app(CurrencyConverter::class)->convert(Money::of('10', Currency::USD), Currency::LRD);
})->throws(MissingExchangeRateException::class);
