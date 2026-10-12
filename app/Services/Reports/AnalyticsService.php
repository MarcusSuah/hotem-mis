<?php

namespace App\Services\Reports;

use App\Enums\BookingSource;
use App\Enums\ChargeCategory;
use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\TransactionStatus;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\MissingExchangeRateException;
use App\Support\Money\Money;
use BcMath\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hotel performance figures. Every amount is consolidated into the base
 * currency: room nights carry their base rate, and folio amounts are
 * converted back with the exchange rate agreed on the reservation.
 */
class AnalyticsService
{
    /**
     * Reservation statuses whose nights count as sold.
     *
     * @var list<string>
     */
    private const SOLD = ['confirmed', 'checked_in', 'checked_out'];

    public function __construct(private CurrencyConverter $converter) {}

    public function baseCurrency(): Currency
    {
        return $this->converter->baseCurrency();
    }

    /**
     * Room nights that could be sold on each date: rooms in the inventory
     * minus rooms blocked for maintenance or out of service that night.
     *
     * @return array<string, int>
     */
    public function availableNights(ReportPeriod $period): array
    {
        $rooms = Room::query()->count();
        $available = array_fill_keys($period->dates(), $rooms);

        $blocks = RoomBlock::query()
            ->where('start_date', '<=', $period->to->toDateString())
            ->where('end_date', '>', $period->from->toDateString())
            ->get(['start_date', 'end_date', 'released_at']);

        foreach ($blocks as $block) {
            foreach ($period->dates() as $date) {
                $night = CarbonImmutable::parse($date);
                $released = $block->released_at !== null && $block->released_at->startOfDay()->lte($night);

                if (! $released && $night->gte($block->start_date) && $night->lt($block->end_date)) {
                    $available[$date] = max(0, $available[$date] - 1);
                }
            }
        }

        return $available;
    }

    /**
     * Sold nights and base-currency room revenue per date.
     *
     * @param  list<string>  $statuses
     * @return array<string, array{sold: int, revenue_minor: int}>
     */
    public function soldNights(ReportPeriod $period, array $statuses = self::SOLD): array
    {
        $today = CarbonImmutable::today()->toDateString();

        $rows = DB::table('reservation_nights as n')
            ->join('reservations as r', 'r.id', '=', 'n.reservation_id')
            ->whereIn('r.status', $statuses)
            // A confirmed night already in the past means the guest has not
            // arrived; it is not sold until check-in (or becomes a no-show).
            ->where(fn ($query) => $query->where('r.status', '!=', ReservationStatus::Confirmed->value)->orWhere('n.night_date', '>=', $today))
            ->whereBetween('n.night_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->groupBy('n.night_date')
            ->selectRaw('n.night_date as night, count(*) as sold, coalesce(sum(n.base_rate_minor), 0) as revenue')
            ->get()
            ->keyBy(fn (object $row): string => CarbonImmutable::parse($row->night)->toDateString());

        $result = [];

        foreach ($period->dates() as $date) {
            $row = $rows->get($date);
            $result[$date] = ['sold' => (int) ($row->sold ?? 0), 'revenue_minor' => (int) ($row->revenue ?? 0)];
        }

        return $result;
    }

    /**
     * Occupancy, ADR and RevPAR per date.
     *
     * @return list<array{date: string, available: int, sold: int, occupancy: float, revenue: Money, adr: Money, revpar: Money}>
     */
    public function dailyPerformance(ReportPeriod $period): array
    {
        $available = $this->availableNights($period);
        $sold = $this->soldNights($period);

        return array_map(fn (string $date): array => $this->kpis($date, $available[$date], $sold[$date]['sold'], $sold[$date]['revenue_minor']), $period->dates());
    }

    /**
     * Totals for the period with the same figures for the previous period.
     *
     * @return array{current: array<string, mixed>, previous: array<string, mixed>}
     */
    public function summary(ReportPeriod $period): array
    {
        return [
            'current' => $this->totals($period),
            'previous' => $this->totals($period->previous()),
        ];
    }

    /**
     * Folio charges by category, converted to the base currency.
     * Reversals are negative postings, so they net off automatically.
     *
     * @return list<array{category: string, label: string, amount: Money}>
     */
    public function revenueByCategory(ReportPeriod $period): array
    {
        $rows = DB::table('folio_charges as c')
            ->join('folios as f', 'f.id', '=', 'c.folio_id')
            ->leftJoin('reservations as r', 'r.id', '=', 'f.reservation_id')
            ->whereBetween('c.service_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->groupBy('c.category', 'c.currency', 'r.exchange_rate')
            ->selectRaw('c.category, c.currency, r.exchange_rate, sum(c.amount_minor) as total')
            ->get();

        return array_values($rows->groupBy('category')
            ->map(function (Collection $group, string $category): array {
                $minor = $group->sum(fn (object $row): int => $this->toBaseMinor((int) $row->total, (string) $row->currency, $row->exchange_rate));
                $case = ChargeCategory::tryFrom($category);

                return ['category' => $category, 'label' => $case?->label() ?? $category, 'amount' => Money::ofMinor($minor, $this->baseCurrency())];
            })
            ->sortByDesc(fn (array $row): int => $row['amount']->minor)
            ->all());
    }

    /**
     * Completed payments less completed refunds, per method, in the base currency.
     *
     * @return list<array{method: string, label: string, count: int, amount: Money}>
     */
    public function paymentsByMethod(ReportPeriod $period): array
    {
        $range = [$period->from->startOfDay(), $period->to->endOfDay()];
        $payments = DB::table('payments as p')
            ->join('folios as f', 'f.id', '=', 'p.folio_id')
            ->leftJoin('reservations as r', 'r.id', '=', 'f.reservation_id')
            ->where('p.status', TransactionStatus::Completed->value)
            ->whereBetween('p.received_at', $range)
            ->groupBy('p.method', 'f.currency', 'r.exchange_rate')
            ->selectRaw('p.method, f.currency, r.exchange_rate, count(*) as total_count, sum(p.applied_minor) as total')
            ->get();
        $refunds = DB::table('refunds as x')
            ->join('folios as f', 'f.id', '=', 'x.folio_id')
            ->leftJoin('reservations as r', 'r.id', '=', 'f.reservation_id')
            ->where('x.status', TransactionStatus::Completed->value)
            ->whereBetween('x.processed_at', $range)
            ->groupBy('x.method', 'f.currency', 'r.exchange_rate')
            ->selectRaw('x.method, f.currency, r.exchange_rate, sum(x.applied_minor) as total')
            ->get();

        return array_values(collect(PaymentMethod::cases())
            ->map(function (PaymentMethod $method) use ($payments, $refunds): array {
                $in = $payments->where('method', $method->value);
                $out = $refunds->where('method', $method->value);
                $minor = $in->sum(fn (object $row): int => $this->toBaseMinor((int) $row->total, (string) $row->currency, $row->exchange_rate))
                    - $out->sum(fn (object $row): int => $this->toBaseMinor((int) $row->total, (string) $row->currency, $row->exchange_rate));

                return ['method' => $method->value, 'label' => $method->label(), 'count' => (int) $in->sum('total_count'), 'amount' => Money::ofMinor($minor, $this->baseCurrency())];
            })
            ->filter(fn (array $row): bool => $row['count'] > 0 || $row['amount']->minor !== 0)
            ->all());
    }

    /**
     * Reservations created in the period, by channel.
     *
     * @return list<array{source: string, label: string, bookings: int, nights: int, value: Money}>
     */
    public function bookingsBySource(ReportPeriod $period): array
    {
        $rows = DB::table('reservations')
            ->whereBetween('created_at', [$period->from->startOfDay(), $period->to->endOfDay()])
            ->whereNotIn('status', [ReservationStatus::Cancelled->value])
            ->groupBy('source')
            ->selectRaw('source, count(*) as bookings, sum(nights) as nights, sum(base_total_minor) as value')
            ->get()
            ->keyBy('source');

        return array_values(collect(BookingSource::cases())
            ->map(fn (BookingSource $source): array => [
                'source' => $source->value,
                'label' => $source->label(),
                'bookings' => (int) ($rows->get($source->value)->bookings ?? 0),
                'nights' => (int) ($rows->get($source->value)->nights ?? 0),
                'value' => Money::ofMinor((int) ($rows->get($source->value)->value ?? 0), $this->baseCurrency()),
            ])
            ->filter(fn (array $row): bool => $row['bookings'] > 0)
            ->sortByDesc('bookings')
            ->all());
    }

    /**
     * On-the-books outlook: nights already reserved for upcoming dates,
     * split into confirmed business and pending requests.
     *
     * @return list<array{date: string, available: int, confirmed: int, pending: int, occupancy: float, revenue: Money}>
     */
    public function forecast(ReportPeriod $period): array
    {
        $available = $this->availableNights($period);
        $confirmed = $this->soldNights($period, ['confirmed', 'checked_in']);
        $pending = $this->soldNights($period, ['pending']);

        return array_map(fn (string $date): array => [
            'date' => $date,
            'available' => $available[$date],
            'confirmed' => $confirmed[$date]['sold'],
            'pending' => $pending[$date]['sold'],
            'occupancy' => $this->percentage($confirmed[$date]['sold'], $available[$date]),
            'revenue' => Money::ofMinor($confirmed[$date]['revenue_minor'], $this->baseCurrency()),
        ], $period->dates());
    }

    /**
     * Room-by-day usage grid for the heatmap.
     *
     * @return array{dates: list<string>, rooms: list<array{id: int, number: string, room_type: string, cells: list<string>, utilisation: float}>, summary: array<string, int>}
     */
    public function heatmap(ReportPeriod $period): array
    {
        $dates = $period->dates();

        $nights = DB::table('reservation_nights as n')
            ->join('reservations as r', 'r.id', '=', 'n.reservation_id')
            ->whereIn('r.status', ['pending', 'confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('n.night_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->get(['n.room_id', 'n.night_date', 'r.status'])
            ->groupBy('room_id')
            ->map(fn (Collection $rows) => $rows->mapWithKeys(fn (object $row): array => [CarbonImmutable::parse($row->night_date)->toDateString() => $row->status]));

        $blocks = RoomBlock::query()
            ->where('start_date', '<=', $period->to->toDateString())
            ->where('end_date', '>', $period->from->toDateString())
            ->get(['room_id', 'start_date', 'end_date', 'released_at'])
            ->groupBy('room_id');

        $summary = ['occupied' => 0, 'reserved' => 0, 'pending' => 0, 'blocked' => 0, 'free' => 0];

        $rooms = Room::query()->with('roomType:id,name')->orderBy('number')->get()->map(function (Room $room) use ($dates, $nights, $blocks, &$summary): array {
            $roomNights = $nights->get($room->id, collect());
            $roomBlocks = $blocks->get($room->id, collect());
            $cells = [];

            foreach ($dates as $date) {
                $status = $roomNights->get($date);
                $night = CarbonImmutable::parse($date);
                $blocked = $roomBlocks->contains(fn (RoomBlock $block): bool => $night->gte($block->start_date) && $night->lt($block->end_date)
                    && ($block->released_at === null || $block->released_at->startOfDay()->gt($night)));

                $cell = match (true) {
                    in_array($status, ['checked_in', 'checked_out'], true) => 'occupied',
                    $status === 'confirmed' => 'reserved',
                    $status === 'pending' => 'pending',
                    $blocked => 'blocked',
                    default => 'free',
                };

                $summary[$cell]++;
                $cells[] = $cell;
            }

            $used = count(array_filter($cells, fn (string $cell): bool => in_array($cell, ['occupied', 'reserved'], true)));

            return [
                'id' => $room->id,
                'number' => $room->number,
                'room_type' => $room->roomType->name,
                'cells' => $cells,
                'utilisation' => $this->percentage($used, count($dates)),
            ];
        })->all();

        return ['dates' => $dates, 'rooms' => array_values($rooms), 'summary' => $summary];
    }

    /**
     * Open folios that still have money owing, in the base currency.
     *
     * @return list<array{folio: string, reservation: string|null, guest: string, status: string|null, check_out: string|null, balance: Money, balance_local: Money}>
     */
    public function outstandingBalances(): array
    {
        $charges = DB::table('folio_charges')->selectRaw('folio_id, sum(amount_minor) as total')->groupBy('folio_id');
        $payments = DB::table('payments')->where('status', TransactionStatus::Completed->value)->selectRaw('folio_id, sum(applied_minor) as total')->groupBy('folio_id');
        $refunds = DB::table('refunds')->where('status', TransactionStatus::Completed->value)->selectRaw('folio_id, sum(applied_minor) as total')->groupBy('folio_id');

        return array_values(DB::table('folios as f')
            ->join('guests as g', 'g.id', '=', 'f.guest_id')
            ->leftJoin('reservations as r', 'r.id', '=', 'f.reservation_id')
            ->leftJoinSub($charges, 'c', 'c.folio_id', '=', 'f.id')
            ->leftJoinSub($payments, 'p', 'p.folio_id', '=', 'f.id')
            ->leftJoinSub($refunds, 'x', 'x.folio_id', '=', 'f.id')
            ->selectRaw('f.number, f.currency, r.reference, r.status, r.check_out_date, r.exchange_rate, g.first_name, g.last_name, coalesce(c.total, 0) - coalesce(p.total, 0) + coalesce(x.total, 0) as balance')
            ->havingRaw('balance > 0')
            ->orderByDesc('balance')
            ->get()
            ->map(fn (object $row): array => [
                'folio' => (string) $row->number,
                'reservation' => $row->reference,
                'guest' => trim($row->first_name.' '.$row->last_name),
                'status' => $row->status,
                'check_out' => $row->check_out_date ? CarbonImmutable::parse($row->check_out_date)->toDateString() : null,
                'balance' => Money::ofMinor($this->toBaseMinor((int) $row->balance, (string) $row->currency, $row->exchange_rate), $this->baseCurrency()),
                'balance_local' => Money::ofMinor((int) $row->balance, Currency::from((string) $row->currency)),
            ])
            ->all());
    }

    /**
     * Convert an amount in a folio's currency to the base currency using the
     * rate agreed on the reservation, or today's rate when there is none.
     */
    public function toBaseMinor(int $minor, string $currency, mixed $snapshotRate): int
    {
        $from = Currency::from($currency);
        $base = $this->baseCurrency();

        if ($from === $base || $minor === 0) {
            return $minor;
        }

        if (is_numeric($snapshotRate) && (float) $snapshotRate > 0) {
            $inverse = (new Number(1))->div(new Number(Money::numeric((string) $snapshotRate)), 12)->value;

            return $this->converter->convertWithSnapshot(Money::ofMinor($minor, $from), $base, $inverse)->minor;
        }

        try {
            return $this->converter->convert(Money::ofMinor($minor, $from), $base)->converted->minor;
        } catch (MissingExchangeRateException) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function totals(ReportPeriod $period): array
    {
        $available = array_sum($this->availableNights($period));
        $sold = $this->soldNights($period);
        $soldNights = array_sum(array_column($sold, 'sold'));
        $revenue = array_sum(array_column($sold, 'revenue_minor'));

        return [
            'from' => $period->from->toDateString(),
            'to' => $period->to->toDateString(),
            ...$this->kpis(null, $available, $soldNights, $revenue),
        ];
    }

    /**
     * @return array{date: string, available: int, sold: int, occupancy: float, revenue: Money, adr: Money, revpar: Money}
     */
    private function kpis(?string $date, int $available, int $sold, int $revenueMinor): array
    {
        $base = $this->baseCurrency();

        return [
            'date' => (string) $date,
            'available' => $available,
            'sold' => $sold,
            'occupancy' => $this->percentage($sold, $available),
            'revenue' => Money::ofMinor($revenueMinor, $base),
            'adr' => Money::ofMinor($sold > 0 ? intdiv($revenueMinor + intdiv($sold, 2), $sold) : 0, $base),
            'revpar' => Money::ofMinor($available > 0 ? intdiv($revenueMinor + intdiv($available, 2), $available) : 0, $base),
        ];
    }

    private function percentage(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
