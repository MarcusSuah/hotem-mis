<?php

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Services\Booking\PricingService;
use App\Services\Booking\StayPeriod;
use App\Services\Currency\MissingExchangeRateException;
use App\Services\Settings\SettingsRepository;

function setTaxes(string $tax = '0', string $service = '0'): void
{
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => $tax, 'service_charge_rate' => $service, 'deposit_percent' => '0']);
}

function nightlyAmounts(RoomType $type, string $checkIn, string $checkOut, int $adults = 2, int $children = 0, ?RatePlan $plan = null): array
{
    $quote = app(PricingService::class)->quote($type, $plan, new StayPeriod($checkIn, $checkOut), $adults, $children, Currency::USD);

    return array_map(fn (array $night) => $night['amount']->minor, $quote->nights);
}

it('charges the standard rate on weekdays and the weekend rate on Friday and Saturday nights', function () {
    setTaxes();
    $type = RoomType::factory()->rate(10000, 13000)->create();

    // 2026-11-12 is a Thursday: Thu, Fri, Sat, Sun nights.
    expect(nightlyAmounts($type, '2026-11-12', '2026-11-16'))->toBe([10000, 13000, 13000, 10000]);
});

it('applies the highest-priority seasonal rate to the nights it covers', function () {
    setTaxes();
    $type = RoomType::factory()->rate(10000)->create();
    SeasonalRate::factory()->for($type)->create(['start_date' => '2026-12-20', 'end_date' => '2026-12-31', 'rate_minor' => 18000, 'priority' => 1]);
    SeasonalRate::factory()->for($type)->create(['start_date' => '2026-12-24', 'end_date' => '2026-12-25', 'rate_minor' => 25000, 'priority' => 5]);

    expect(nightlyAmounts($type, '2026-12-19', '2026-12-26'))
        ->toBe([10000, 18000, 18000, 18000, 18000, 25000, 25000]);
});

it('adds charges for guests beyond the included occupancy', function () {
    setTaxes();
    $type = RoomType::factory()->rate(10000)->capacity(3, 2, 4)->create([
        'included_adults' => 2,
        'included_children' => 0,
        'extra_adult_minor' => 3000,
        'extra_child_minor' => 1500,
    ]);

    expect(nightlyAmounts($type, '2026-11-16', '2026-11-17', adults: 3, children: 1))->toBe([14500]);
});

it('applies the rate plan adjustment to each night', function () {
    setTaxes();
    $type = RoomType::factory()->rate(10000)->create();
    $saver = RatePlan::factory()->adjustment('-15')->create();

    expect(nightlyAmounts($type, '2026-11-16', '2026-11-18', plan: $saver))->toBe([8500, 8500]);
});

it('adds service charge to the room total and tax to both', function () {
    setTaxes(tax: '10', service: '5');
    $type = RoomType::factory()->rate(10000)->create();

    $quote = app(PricingService::class)->quote($type, null, new StayPeriod('2026-11-16', '2026-11-18'), 2, 0, Currency::USD);

    expect($quote->roomTotal->format())->toBe('USD 200.00')
        ->and($quote->serviceCharge->format())->toBe('USD 10.00')
        ->and($quote->tax->format())->toBe('USD 21.00')
        ->and($quote->total->format())->toBe('USD 231.00')
        ->and($quote->isConverted())->toBeFalse();
});

it('converts every nightly line with one rate snapshot when charging in Liberian dollars', function () {
    setTaxes(tax: '10');
    ExchangeRate::factory()->rate('190.5')->create(['effective_at' => now()->subDay()]);
    $type = RoomType::factory()->rate(10050)->create();

    $quote = app(PricingService::class)->quote($type, null, new StayPeriod('2026-11-16', '2026-11-18'), 2, 0, Currency::LRD);

    expect($quote->exchangeRate)->toBe('190.50000000')
        ->and(array_map(fn ($night) => $night['amount']->format(), $quote->nights))->toBe(['LRD 19,145.25', 'LRD 19,145.25'])
        ->and($quote->roomTotal->format())->toBe('LRD 38,290.50')
        ->and($quote->nights[0]['tax']->format())->toBe('LRD 1,914.53')
        ->and($quote->tax->format())->toBe('LRD 3,829.06')
        ->and($quote->total->format())->toBe('LRD 42,119.56')
        ->and($quote->baseTotal->format())->toBe('USD 221.10');
});

it('refuses to quote another currency without a configured exchange rate', function () {
    $type = RoomType::factory()->create();

    app(PricingService::class)->quote($type, null, new StayPeriod('2026-11-16', '2026-11-18'), 2, 0, Currency::LRD);
})->throws(MissingExchangeRateException::class);

it('prices complimentary stays at zero', function () {
    setTaxes(tax: '10', service: '5');
    $type = RoomType::factory()->rate(10000)->create();

    $quote = app(PricingService::class)->quote($type, null, new StayPeriod('2026-11-16', '2026-11-18'), 2, 0, Currency::USD, complimentary: true);

    expect($quote->total->isZero())->toBeTrue();
});
