<?php

namespace App\Services\Currency;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use BcMath\Number;
use DateTimeInterface;
use RoundingMode;

class CurrencyConverter
{
    public function __construct(private SettingsRepository $settings) {}

    public function baseCurrency(): Currency
    {
        return Currency::from((string) $this->settings->get('currency.base', 'USD'));
    }

    /**
     * Find the rate in force at the given moment for the currency pair, in either direction.
     */
    public function rateAt(Currency $from, Currency $to, ?DateTimeInterface $at = null): ?ExchangeRate
    {
        if ($from === $to) {
            return null;
        }

        return ExchangeRate::query()
            ->where(fn ($query) => $query
                ->where(fn ($pair) => $pair->where('base_currency', $from->value)->where('quote_currency', $to->value))
                ->orWhere(fn ($pair) => $pair->where('base_currency', $to->value)->where('quote_currency', $from->value)))
            ->where('effective_at', '<=', $at ?? now())
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Convert money using the rate in force now (or at the given moment).
     *
     * @throws MissingExchangeRateException
     */
    public function convert(Money $money, Currency $to, ?DateTimeInterface $at = null): ConversionResult
    {
        if ($money->currency === $to) {
            return new ConversionResult($money, $money, null, null);
        }

        $rate = $this->rateAt($money->currency, $to, $at)
            ?? throw new MissingExchangeRateException($money->currency, $to);

        return $this->convertWithRate($money, $to, $rate);
    }

    /**
     * Convert money with a specific, previously recorded exchange rate.
     */
    public function convertWithRate(Money $money, Currency $to, ExchangeRate $rate): ConversionResult
    {
        $factor = $this->factor($money->currency, $to, $rate);

        return new ConversionResult(
            original: $money,
            converted: $this->applyFactor($money, $to, $factor),
            rate: $factor,
            exchangeRate: $rate,
        );
    }

    /**
     * Convert with a stored rate snapshot (units of `$to` per one unit of the source currency).
     */
    public function convertWithSnapshot(Money $money, Currency $to, string $snapshotRate): Money
    {
        if ($money->currency === $to) {
            return $money;
        }

        return $this->applyFactor($money, $to, $snapshotRate);
    }

    /**
     * Multiplier that turns one unit of `$from` into units of `$to` for the stored rate.
     */
    public function factor(Currency $from, Currency $to, ExchangeRate $rate): string
    {
        if ($rate->base_currency === $from && $rate->quote_currency === $to) {
            return (string) $rate->rate;
        }

        return (new Number(1))->div(new Number(Money::numeric((string) $rate->rate)), 12)->value;
    }

    private function applyFactor(Money $money, Currency $to, string $factor): Money
    {
        $scale = 10 ** ($to->minorUnits() - $money->currency->minorUnits());

        $minor = (new Number($money->minor))
            ->mul(new Number(Money::numeric($factor)))
            ->mul($scale)
            ->round(0, RoundingMode::HalfAwayFromZero);

        return Money::ofMinor((int) $minor->value, $to);
    }
}
