<?php

namespace App\Services\Booking;

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\RoomType;

/**
 * Everything required to create a reservation, already validated.
 */
final readonly class NewReservation
{
    public function __construct(
        public Guest $guest,
        public RoomType $roomType,
        public StayPeriod $stay,
        public int $adults,
        public int $children,
        public Currency $currency,
        public BookingSource $source,
        public ?RatePlan $ratePlan = null,
        public ?int $roomId = null,
        public ?string $specialRequests = null,
        public bool $confirmImmediately = false,
        public bool $complimentary = false,
        public ?string $idempotencyKey = null,
        public ?string $contactName = null,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
        public ?int $holdMinutes = null,
    ) {}
}
