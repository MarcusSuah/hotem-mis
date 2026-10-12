<?php

namespace App\Services\Reports;

use App\Enums\ReservationStatus;
use App\Models\InventoryItem;
use App\Models\Reservation;
use App\Services\Inventory\InventoryService;
use App\Support\Money\Money;
use InvalidArgumentException;

/**
 * Every tabular report, shaped once for both the screen and file exports.
 * Rows hold plain values (numbers, strings, Money) so exports keep numbers
 * numeric and screens can still format money.
 */
class ReportCatalog
{
    /**
     * @var array<string, array{title: string, description: string, periodic: bool}>
     */
    public const REPORTS = [
        'performance' => ['title' => 'Daily performance', 'description' => 'Occupancy, ADR and RevPAR for each night.', 'periodic' => true],
        'revenue' => ['title' => 'Revenue by category', 'description' => 'Folio postings by service date, net of reversals.', 'periodic' => true],
        'payments' => ['title' => 'Payments by method', 'description' => 'Money received less refunds, by payment method.', 'periodic' => true],
        'sources' => ['title' => 'Bookings by channel', 'description' => 'Reservations made in the period, by booking source.', 'periodic' => true],
        'arrivals' => ['title' => 'Reservations by arrival', 'description' => 'Every reservation arriving in the period.', 'periodic' => true],
        'forecast' => ['title' => 'On-the-books forecast', 'description' => 'Rooms already reserved for each upcoming night.', 'periodic' => true],
        'balances' => ['title' => 'Outstanding balances', 'description' => 'Folios with money still owing, as of now.', 'periodic' => false],
        'inventory' => ['title' => 'Stock valuation', 'description' => 'Stock on hand at moving average cost, as of now.', 'periodic' => false],
    ];

    public function __construct(
        private AnalyticsService $analytics,
        private InventoryService $inventory,
    ) {}

    /**
     * @return array{key: string, title: string, description: string, columns: list<array{key: string, label: string, type: string}>, rows: list<array<string, mixed>>, totals: array<string, mixed>|null}
     */
    public function build(string $key, ReportPeriod $period): array
    {
        $meta = self::REPORTS[$key] ?? throw new InvalidArgumentException("Unknown report [{$key}].");
        $base = $this->analytics->baseCurrency()->value;

        [$columns, $rows, $totals] = match ($key) {
            'performance' => $this->performance($period, $base),
            'revenue' => $this->revenue($period, $base),
            'payments' => $this->payments($period, $base),
            'sources' => $this->sources($period, $base),
            'arrivals' => $this->arrivals($period),
            'forecast' => $this->forecast($period, $base),
            'balances' => $this->balances($base),
            default => $this->stock($base),
        };

        return ['key' => $key, ...$meta, 'columns' => $columns, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<array<string, mixed>>, 2: array<string, mixed>|null}
     */
    private function performance(ReportPeriod $period, string $base): array
    {
        $days = $this->analytics->dailyPerformance($period);
        $totals = $this->analytics->summary($period)['current'];

        return [
            [
                $this->column('date', 'Night', 'date'),
                $this->column('available', 'Rooms available', 'number'),
                $this->column('sold', 'Rooms sold', 'number'),
                $this->column('occupancy', 'Occupancy %', 'percent'),
                $this->column('revenue', "Room revenue ({$base})", 'money'),
                $this->column('adr', "ADR ({$base})", 'money'),
                $this->column('revpar', "RevPAR ({$base})", 'money'),
            ],
            array_map(fn (array $day): array => array_intersect_key($day, array_flip(['date', 'available', 'sold', 'occupancy', 'revenue', 'adr', 'revpar'])), $days),
            ['date' => 'Total', ...array_intersect_key($totals, array_flip(['available', 'sold', 'occupancy', 'revenue', 'adr', 'revpar']))],
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<array<string, mixed>>, 2: array<string, mixed>|null}
     */
    private function revenue(ReportPeriod $period, string $base): array
    {
        $rows = array_map(fn (array $row): array => ['label' => $row['label'], 'amount' => $row['amount']], $this->analytics->revenueByCategory($period));

        return [
            [$this->column('label', 'Category', 'text'), $this->column('amount', "Amount ({$base})", 'money')],
            $rows,
            ['label' => 'Total', 'amount' => $this->sum($rows, 'amount', $base)],
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<array<string, mixed>>, 2: array<string, mixed>|null}
     */
    private function payments(ReportPeriod $period, string $base): array
    {
        $rows = array_map(fn (array $row): array => ['label' => $row['label'], 'count' => $row['count'], 'amount' => $row['amount']], $this->analytics->paymentsByMethod($period));

        return [
            [$this->column('label', 'Method', 'text'), $this->column('count', 'Payments', 'number'), $this->column('amount', "Net received ({$base})", 'money')],
            $rows,
            ['label' => 'Total', 'count' => array_sum(array_column($rows, 'count')), 'amount' => $this->sum($rows, 'amount', $base)],
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<array<string, mixed>>, 2: array<string, mixed>|null}
     */
    private function sources(ReportPeriod $period, string $base): array
    {
        $rows = array_map(fn (array $row): array => ['label' => $row['label'], 'bookings' => $row['bookings'], 'nights' => $row['nights'], 'value' => $row['value']], $this->analytics->bookingsBySource($period));

        return [
            [$this->column('label', 'Channel', 'text'), $this->column('bookings', 'Bookings', 'number'), $this->column('nights', 'Room nights', 'number'), $this->column('value', "Booked value ({$base})", 'money')],
            $rows,
            ['label' => 'Total', 'bookings' => array_sum(array_column($rows, 'bookings')), 'nights' => array_sum(array_column($rows, 'nights')), 'value' => $this->sum($rows, 'value', $base)],
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<array<string, mixed>>, 2: array<string, mixed>|null}
     */
    private function arrivals(ReportPeriod $period): array
    {
        $rows = Reservation::query()
            ->with(['activeAllocations.room:id,number', 'allocations.room:id,number'])
            ->whereBetween('check_in_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->orderBy('check_in_date')
            ->orderBy('reference')
            ->limit(5000)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'reference' => $reservation->reference,
                'guest' => $reservation->contact_name,
                'check_in' => $reservation->check_in_date->toDateString(),
                'check_out' => $reservation->check_out_date->toDateString(),
                'nights' => $reservation->nights,
                'room' => ($reservation->activeAllocations->first() ?? $reservation->allocations->first())?->room?->number,
                'source' => $reservation->source->label(),
                'status' => $reservation->status->label(),
                'total' => Money::ofMinor($reservation->total_minor, $reservation->currency),
            ])
            ->values()
            ->all();
        $rows = array_values($rows);

        return [
            [
                $this->column('reference', 'Reference', 'text'),
                $this->column('guest', 'Guest', 'text'),
                $this->column('check_in', 'Arrival', 'date'),
                $this->column('check_out', 'Departure', 'date'),
                $this->column('nights', 'Nights', 'number'),
                $this->column('room', 'Room', 'text'),
                $this->column('source', 'Channel', 'text'),
                $this->column('status', 'Status', 'text'),
                $this->column('total', 'Total (booking currency)', 'money'),
            ],
            $rows,
            null,
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<array<string, mixed>>, 2: array<string, mixed>|null}
     */
    private function forecast(ReportPeriod $period, string $base): array
    {
        $rows = $this->analytics->forecast($period);

        return [
            [
                $this->column('date', 'Night', 'date'),
                $this->column('available', 'Rooms available', 'number'),
                $this->column('confirmed', 'Confirmed', 'number'),
                $this->column('pending', 'Pending requests', 'number'),
                $this->column('occupancy', 'Confirmed occupancy %', 'percent'),
                $this->column('revenue', "Confirmed room revenue ({$base})", 'money'),
            ],
            $rows,
            ['date' => 'Total', 'available' => array_sum(array_column($rows, 'available')), 'confirmed' => array_sum(array_column($rows, 'confirmed')), 'pending' => array_sum(array_column($rows, 'pending')), 'revenue' => $this->sum($rows, 'revenue', $base)],
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<array<string, mixed>>, 2: array<string, mixed>|null}
     */
    private function balances(string $base): array
    {
        $rows = array_map(fn (array $row): array => [
            ...$row,
            'status' => $row['status'] ? ReservationStatus::from($row['status'])->label() : null,
        ], $this->analytics->outstandingBalances());

        return [
            [
                $this->column('folio', 'Folio', 'text'),
                $this->column('reservation', 'Reservation', 'text'),
                $this->column('guest', 'Guest', 'text'),
                $this->column('status', 'Stay status', 'text'),
                $this->column('check_out', 'Departure', 'date'),
                $this->column('balance_local', 'Balance (folio currency)', 'money'),
                $this->column('balance', "Balance ({$base})", 'money'),
            ],
            $rows,
            ['folio' => 'Total', 'balance' => $this->sum($rows, 'balance', $base)],
        ];
    }

    /**
     * @return array{0: list<array{key: string, label: string, type: string}>, 1: list<array<string, mixed>>, 2: array<string, mixed>|null}
     */
    private function stock(string $base): array
    {
        $rows = InventoryItem::query()->with('category:id,name')->where('is_active', true)->orderBy('name')->get()
            ->map(fn (InventoryItem $item): array => [
                'sku' => $item->sku,
                'name' => $item->name,
                'category' => $item->category->name,
                'quantity' => (float) $item->quantity,
                'unit' => $item->unit,
                'average_cost' => Money::ofMinor($item->average_cost_minor, $item->currency),
                'value' => $this->inventory->valuation($item),
            ])
            ->all();
        $rows = array_values($rows);

        return [
            [
                $this->column('sku', 'SKU', 'text'),
                $this->column('name', 'Item', 'text'),
                $this->column('category', 'Category', 'text'),
                $this->column('quantity', 'On hand', 'number'),
                $this->column('unit', 'Unit', 'text'),
                $this->column('average_cost', "Average cost ({$base})", 'money'),
                $this->column('value', "Value ({$base})", 'money'),
            ],
            $rows,
            ['sku' => 'Total', 'value' => $this->sum($rows, 'value', $base)],
        ];
    }

    /**
     * @return array{key: string, label: string, type: string}
     */
    private function column(string $key, string $label, string $type): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sum(array $rows, string $key, string $currency): Money
    {
        return Money::ofMinor(array_sum(array_map(fn (array $row): int => $row[$key] instanceof Money ? $row[$key]->minor : 0, $rows)), $currency);
    }
}
