<?php

namespace App\Console\Commands;

use App\Services\Booking\ReservationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hms:expire-pending-reservations')]
#[Description('Cancel pending reservations whose confirmation window has expired and release their rooms')]
class ExpirePendingReservations extends Command
{
    public function handle(ReservationService $reservations): int
    {
        $count = $reservations->expireStalePending();

        $this->components->info("Expired {$count} pending reservation(s).");

        return self::SUCCESS;
    }
}
