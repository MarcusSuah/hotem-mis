<?php

namespace App\Services\FrontDesk;

use App\Support\Money\Money;

final readonly class CheckInDetails
{
    /**
     * @param  list<array{name: string, age_category: string}>  $additionalGuests
     */
    public function __construct(
        public array $additionalGuests = [],
        public ?string $identityType = null,
        public ?string $identityNumber = null,
        public ?string $countryCode = null,
        public ?string $address = null,
        public bool $waiveDeposit = false,
        public ?Money $earlyCheckInFee = null,
    ) {}
}
