<?php

use App\Enums\Currency;
use App\Support\Money\CurrencyMismatchException;
use App\Support\Money\Money;

it('parses decimal strings into minor units', function (string $input, int $minor) {
    expect(Money::of($input, Currency::USD)->minor)->toBe($minor);
})->with([
    'whole amount' => ['150', 15000],
    'two decimals' => ['150.25', 15025],
    'one decimal' => ['0.5', 50],
    'rounds half away from zero' => ['10.005', 1001],
    'rounds down below half' => ['10.004', 1000],
    'negative rounds away from zero' => ['-10.005', -1001],
]);

it('rejects non-numeric amounts', function () {
    Money::of('12,50', Currency::USD);
})->throws(InvalidArgumentException::class);

it('formats amounts with the ISO code and digit grouping', function (Money $money, string $formatted) {
    expect($money->format())->toBe($formatted);
})->with([
    'liberian dollars' => [Money::ofMinor(2_500_000, Currency::LRD), 'LRD 25,000.00'],
    'us dollars' => [Money::ofMinor(15_000, Currency::USD), 'USD 150.00'],
    'small amount' => [Money::ofMinor(5, Currency::USD), 'USD 0.05'],
    'negative amount' => [Money::ofMinor(-123_456_789, Currency::USD), 'USD -1,234,567.89'],
]);

it('adds and subtracts amounts in the same currency', function () {
    $total = Money::of('100.10', Currency::USD)
        ->add(Money::of('0.20', Currency::USD))
        ->subtract(Money::of('50', Currency::USD));

    expect($total->toDecimal())->toBe('50.30');
});

it('refuses to combine different currencies', function () {
    Money::of('10', Currency::USD)->add(Money::of('10', Currency::LRD));
})->throws(CurrencyMismatchException::class);

it('calculates percentages with half away from zero rounding', function () {
    expect(Money::of('99.99', Currency::USD)->percentage('10')->toDecimal())->toBe('10.00')
        ->and(Money::of('0.05', Currency::USD)->percentage('10')->minor)->toBe(1)
        ->and(Money::of('120', Currency::USD)->percentage('12.5')->toDecimal())->toBe('15.00');
});

it('multiplies by integer and decimal factors', function () {
    expect(Money::of('150', Currency::USD)->multiply(3)->toDecimal())->toBe('450.00')
        ->and(Money::of('10', Currency::USD)->multiply('0.333')->toDecimal())->toBe('3.33');
});

it('serializes to a currency-labelled payload', function () {
    expect(Money::ofMinor(15_050, Currency::USD)->jsonSerialize())->toBe([
        'amount' => '150.50',
        'minor' => 15050,
        'currency' => 'USD',
        'formatted' => 'USD 150.50',
    ]);
});
