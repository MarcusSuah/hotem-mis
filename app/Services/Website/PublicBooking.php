<?php

namespace App\Services\Website;

use App\Enums\Currency;
use App\Models\Offer;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Booking\StayPeriod;

/**
 * A validated booking request from the public website.
 */
final readonly class PublicBooking
{
    public function __construct(
        public RoomType $roomType,
        public StayPeriod $stay,
        public int $adults,
        public int $children,
        public Currency $currency,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $phone,
        public ?string $countryCode,
        public ?string $specialRequests,
        public string $idempotencyKey,
        public ?Offer $offer = null,
        public ?User $user = null,
        public ?string $arrivalTime = null,
    ) {}
}
