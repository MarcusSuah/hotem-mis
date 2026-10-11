<?php

namespace App\Support\Money;

use App\Enums\Currency;
use BcMath\Number;
use InvalidArgumentException;
use JsonSerializable;
use RoundingMode;

/**
 * Immutable monetary amount stored in integer minor units (e.g. cents).
 * Floating point arithmetic is never used for money.
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $minor,
        public Currency $currency,
    ) {}

    public static function zero(Currency|string $currency): self
    {
        return new self(0, self::currency($currency));
    }

    public static function ofMinor(int $minor, Currency|string $currency): self
    {
        return new self($minor, self::currency($currency));
    }

    /**
     * Create money from a decimal string or integer major amount ("150.25", 150).
     * Values with more precision than the currency supports are rounded half away from zero.
     */
    public static function of(string|int $amount, Currency|string $currency): self
    {
        $currency = self::currency($currency);
        $amount = trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid monetary amount [{$amount}].");
        }

        $minor = (new Number(self::numeric($amount)))
            ->mul(10 ** $currency->minorUnits())
            ->round(0, RoundingMode::HalfAwayFromZero);

        return new self((int) $minor->value, $currency);
    }

    /**
     * Sum a list of amounts that all share the given currency.
     *
     * @param  iterable<Money>  $amounts
     */
    public static function sum(iterable $amounts, Currency|string $currency): self
    {
        $total = self::zero($currency);

        foreach ($amounts as $amount) {
            $total = $total->add($amount);
        }

        return $total;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /**
     * Multiply by an integer or decimal string factor, rounding half away from zero.
     */
    public function multiply(int|string $factor): self
    {
        $result = (new Number($this->minor))
            ->mul(new Number(self::numeric((string) $factor)))
            ->round(0, RoundingMode::HalfAwayFromZero);

        return new self((int) $result->value, $this->currency);
    }

    /**
     * Calculate a percentage of the amount ("10" or "12.5" percent).
     */
    public function percentage(string|int $percent): self
    {
        $result = (new Number($this->minor))
            ->mul(new Number(self::numeric((string) $percent)))
            ->div(100, 10)
            ->round(0, RoundingMode::HalfAwayFromZero);

        return new self((int) $result->value, $this->currency);
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function absolute(): self
    {
        return new self(abs($this->minor), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor < $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    /**
     * Decimal string representation without grouping, e.g. "25000.00".
     */
    public function toDecimal(): string
    {
        $units = $this->currency->minorUnits();
        $digits = str_pad((string) abs($this->minor), $units + 1, '0', STR_PAD_LEFT);
        $major = $units > 0 ? substr($digits, 0, -$units) : $digits;
        $fraction = $units > 0 ? '.'.substr($digits, -$units) : '';

        return ($this->minor < 0 ? '-' : '').$major.$fraction;
    }

    /**
     * Display string with the ISO code and digit grouping, e.g. "LRD 25,000.00".
     */
    public function format(): string
    {
        $parts = explode('.', ltrim($this->toDecimal(), '-'));
        $grouped = strrev(implode(',', str_split(strrev($parts[0]), 3)));
        $fraction = isset($parts[1]) ? '.'.$parts[1] : '';

        return $this->currency->value.' '.($this->minor < 0 ? '-' : '').$grouped.$fraction;
    }

    /**
     * @return array{amount: string, minor: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->toDecimal(),
            'minor' => $this->minor,
            'currency' => $this->currency->value,
            'formatted' => $this->format(),
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException($this->currency, $other->currency);
        }
    }

    /**
     * @return numeric-string
     */
    public static function numeric(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("[{$value}] is not a numeric value.");
        }

        return $value;
    }

    private static function currency(Currency|string $currency): Currency
    {
        return $currency instanceof Currency ? $currency : Currency::from($currency);
    }
}
