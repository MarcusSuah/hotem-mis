<?php

namespace App\Listeners;

use App\Events\GuestCheckedOut;
use App\Services\Guests\LoyaltyService;
use App\Services\Operations\HousekeepingService;

/**
 * After a stay ends: queue the departure clean and award loyalty points.
 */
class HandleGuestCheckedOut
{
    public function __construct(
        private HousekeepingService $housekeeping,
        private LoyaltyService $loyalty,
    ) {}

    public function handle(GuestCheckedOut $event): void
    {
        $this->housekeeping->createDepartureTask($event->room, $event->reservation);
        $this->loyalty->earnForStay($event->reservation);
    }
}
