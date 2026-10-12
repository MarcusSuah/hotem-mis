<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * An inclusive range of calendar dates used by reports.
 */
final readonly class ReportPeriod
{
    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public function __construct(CarbonImmutable|string $from, CarbonImmutable|string $to)
    {
        $this->from = CarbonImmutable::parse($from)->startOfDay();
        $this->to = CarbonImmutable::parse($to)->startOfDay();

        if ($this->to->lt($this->from)) {
            throw new InvalidArgumentException('The report period ends before it starts.');
        }
    }

    public static function lastDays(int $days, ?CarbonImmutable $until = null): self
    {
        $end = ($until ?? CarbonImmutable::today())->startOfDay();

        return new self($end->subDays($days - 1), $end);
    }

    public static function monthOf(CarbonImmutable $date): self
    {
        return new self($date->startOfMonth(), $date->endOfMonth());
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /**
     * @return list<string>
     */
    public function dates(): array
    {
        $dates = [];

        for ($day = $this->from; $day->lte($this->to); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }

    /**
     * The same number of days immediately before this period.
     */
    public function previous(): self
    {
        return new self($this->from->subDays($this->days()), $this->from->subDay());
    }
}
