<?php

namespace App\Events;

use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a stay is completed and its room has been released.
 */
class GuestCheckedOut
{
    use Dispatchable;

    public function __construct(
        public Reservation $reservation,
        public Room $room,
    ) {}
}
