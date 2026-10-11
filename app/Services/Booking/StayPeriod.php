<?php

namespace App\Services\Booking;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * A stay as the half-open interval [checkIn, checkOut): the guest occupies the
 * room on the night of every date from check-in up to, but not including,
 * the check-out date.
 */
final readonly class StayPeriod
{
    public CarbonImmutable $checkIn;

    public CarbonImmutable $checkOut;

    public function __construct(CarbonInterface|string $checkIn, CarbonInterface|string $checkOut)
    {
        $this->checkIn = CarbonImmutable::parse($checkIn)->startOfDay();
        $this->checkOut = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($this->checkOut->lessThanOrEqualTo($this->checkIn)) {
            throw new InvalidArgumentException('Check-out must be after check-in.');
        }
    }

    public function nights(): int
    {
        return (int) $this->checkIn->diffInDays($this->checkOut);
    }

    /**
     * @return list<CarbonImmutable>
     */
    public function eachNight(): array
    {
        $nights = [];

        for ($night = $this->checkIn; $night->lessThan($this->checkOut); $night = $night->addDay()) {
            $nights[] = $night;
        }

        return $nights;
    }

    public function startingFrom(CarbonImmutable $date): self
    {
        return new self($date->greaterThan($this->checkIn) ? $date : $this->checkIn, $this->checkOut);
    }

    public function overlaps(self $other): bool
    {
        return $this->checkIn->lessThan($other->checkOut) && $this->checkOut->greaterThan($other->checkIn);
    }
}
