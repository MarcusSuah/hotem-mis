<?php

namespace App\Services\Booking;

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\SeasonalRate;
use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\MissingExchangeRateException;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Prices a stay night by night in the base currency:
 *
 *   nightly rate = seasonal rate (highest priority) > weekend rate > standard rate
 *                + extra adults/children beyond the included occupancy
 *                ± the rate plan's percentage adjustment
 *   service charge = room total × service charge rate
 *   tax            = (room total + service charge) × tax rate
 *
 * Friday and Saturday nights are weekend nights. When the guest is charged in
 * another currency, each line is converted with one rate snapshot so the
 * converted lines always add up to the converted total.
 */
class PricingService
{
    public function __construct(
        private SettingsRepository $settings,
        private CurrencyConverter $converter,
    ) {}

    /**
     * @param  string|null  $snapshotRate  Reuse a previously agreed exchange rate (e.g. when modifying a booking).
     *
     * @throws MissingExchangeRateException
     */
    public function quote(
        RoomType $roomType,
        ?RatePlan $ratePlan,
        StayPeriod $stay,
        int $adults,
        int $children,
        Currency $currency,
        bool $complimentary = false,
        ?string $snapshotRate = null,
        ?ExchangeRate $snapshotRecord = null,
    ): StayQuote {
        $baseCurrency = $this->converter->baseCurrency();
        $seasons = $this->seasonsFor($roomType, $stay);

        $baseNights = [];

        foreach ($stay->eachNight() as $night) {
            $amount = $complimentary
                ? Money::zero($baseCurrency)
                : $this->nightlyRate($roomType, $ratePlan, $seasons, $night, $adults, $children, $baseCurrency);

            $baseNights[] = ['date' => $night, 'base' => $amount];
        }

        $taxRate = (string) $this->settings->get('booking.tax_rate', '0');
        $serviceRate = (string) $this->settings->get('booking.service_charge_rate', '0');

        [$rate, $rateRecord] = $this->exchangeRate($baseCurrency, $currency, $snapshotRate, $snapshotRecord);
        $convert = fn (Money $money): Money => $rate === null ? $money : $this->converter->convertWithSnapshot($money, $currency, $rate);

        // Each night carries its own service charge and tax so folio postings
        // per night always add up exactly to the quoted total.
        $nights = array_map(function (array $night) use ($serviceRate, $taxRate, $convert): array {
            $baseService = $night['base']->percentage($serviceRate);
            $baseTax = $night['base']->add($baseService)->percentage($taxRate);

            return [
                'date' => $night['date'],
                'base' => $night['base'],
                'base_total' => $night['base']->add($baseService)->add($baseTax),
                'amount' => $convert($night['base']),
                'service' => $convert($baseService),
                'tax' => $convert($baseTax),
            ];
        }, $baseNights);

        $roomTotal = Money::sum(array_column($nights, 'amount'), $currency);
        $service = Money::sum(array_column($nights, 'service'), $currency);
        $tax = Money::sum(array_column($nights, 'tax'), $currency);
        $total = $roomTotal->add($service)->add($tax);
        $baseTotal = Money::sum(array_column($nights, 'base_total'), $baseCurrency);

        $depositPercent = $ratePlan->deposit_percent ?? (string) $this->settings->get('booking.deposit_percent', '0');

        return new StayQuote(
            nights: $nights,
            roomTotal: $roomTotal,
            serviceCharge: $service,
            tax: $tax,
            total: $total,
            baseTotal: $baseTotal,
            depositDue: $total->percentage((string) $depositPercent),
            currency: $currency,
            baseCurrency: $baseCurrency,
            exchangeRate: $rate,
            exchangeRateRecord: $rateRecord,
            taxRate: $taxRate,
            serviceChargeRate: $serviceRate,
        );
    }

    /**
     * @param  Collection<int, SeasonalRate>  $seasons
     */
    private function nightlyRate(
        RoomType $roomType,
        ?RatePlan $ratePlan,
        Collection $seasons,
        CarbonImmutable $night,
        int $adults,
        int $children,
        Currency $baseCurrency,
    ): Money {
        $isWeekend = in_array($night->dayOfWeekIso, [5, 6], true);
        $season = $seasons->first(fn (SeasonalRate $rate): bool => $rate->covers($night));

        $rateMinor = match (true) {
            $season !== null => $isWeekend && $season->weekend_rate_minor !== null ? $season->weekend_rate_minor : $season->rate_minor,
            $isWeekend && $roomType->weekend_rate_minor !== null => $roomType->weekend_rate_minor,
            default => $roomType->base_rate_minor,
        };

        $extraAdults = max(0, $adults - $roomType->included_adults);
        $extraChildren = max(0, $children - $roomType->included_children);

        $nightly = Money::ofMinor($rateMinor, $baseCurrency)
            ->add(Money::ofMinor($roomType->extra_adult_minor * $extraAdults, $baseCurrency))
            ->add(Money::ofMinor($roomType->extra_child_minor * $extraChildren, $baseCurrency));

        if ($ratePlan === null) {
            return $nightly;
        }

        return $nightly->add($nightly->percentage((string) $ratePlan->adjustment_percent));
    }

    /**
     * Seasons overlapping the stay, highest priority (then most recent) first.
     *
     * @return Collection<int, SeasonalRate>
     */
    private function seasonsFor(RoomType $roomType, StayPeriod $stay): Collection
    {
        return SeasonalRate::query()
            ->where('room_type_id', $roomType->id)
            ->where('is_active', true)
            ->where('start_date', '<', $stay->checkOut->toDateString())
            ->where('end_date', '>=', $stay->checkIn->toDateString())
            ->orderByDesc('priority')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{0: string|null, 1: ExchangeRate|null}
     */
    private function exchangeRate(Currency $base, Currency $currency, ?string $snapshotRate, ?ExchangeRate $snapshotRecord): array
    {
        if ($base === $currency) {
            return [null, null];
        }

        if ($snapshotRate !== null) {
            return [$snapshotRate, $snapshotRecord];
        }

        $record = $this->converter->rateAt($base, $currency)
            ?? throw new MissingExchangeRateException($base, $currency);

        return [$this->converter->factor($base, $currency, $record), $record];
    }
}
