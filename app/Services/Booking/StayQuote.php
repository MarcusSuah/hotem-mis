<?php

namespace App\Services\Booking;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;

/**
 * Price of a stay. Amounts are in the transaction currency; base amounts are
 * in the hotel's base currency. When the currencies differ, `exchangeRate`
 * (units of transaction currency per base unit) records the conversion used.
 */
final readonly class StayQuote
{
    /**
     * @param  list<array{date: CarbonImmutable, base: Money, base_total: Money, amount: Money, service: Money, tax: Money}>  $nights
     */
    public function __construct(
        public array $nights,
        public Money $roomTotal,
        public Money $serviceCharge,
        public Money $tax,
        public Money $total,
        public Money $baseTotal,
        public Money $depositDue,
        public Currency $currency,
        public Currency $baseCurrency,
        public ?string $exchangeRate,
        public ?ExchangeRate $exchangeRateRecord,
        public string $taxRate,
        public string $serviceChargeRate,
    ) {}

    public function isConverted(): bool
    {
        return $this->exchangeRate !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nights' => array_map(fn (array $night): array => [
                'date' => $night['date']->toDateString(),
                'amount' => $night['amount'],
                'service_charge' => $night['service'],
                'tax' => $night['tax'],
                'base' => $night['base'],
            ], $this->nights),
            'room_total' => $this->roomTotal,
            'service_charge' => $this->serviceCharge,
            'tax' => $this->tax,
            'total' => $this->total,
            'base_total' => $this->baseTotal,
            'deposit_due' => $this->depositDue,
            'currency' => $this->currency->value,
            'base_currency' => $this->baseCurrency->value,
            'exchange_rate' => $this->exchangeRate,
            'tax_rate' => $this->taxRate,
            'service_charge_rate' => $this->serviceChargeRate,
        ];
    }
}
