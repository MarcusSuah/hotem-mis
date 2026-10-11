<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BookingsView->value);
    }

    /**
     * Staff with booking access, or the guest the reservation belongs to.
     */
    public function view(User $user, Reservation $reservation): bool
    {
        return $user->can(Permission::BookingsView->value) || $this->owns($user, $reservation);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BookingsCreate->value);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $user->can(Permission::BookingsUpdate->value);
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $user->can(Permission::BookingsCancel->value);
    }

    public function assignRoom(User $user, Reservation $reservation): bool
    {
        return $user->can(Permission::BookingsAssignRoom->value);
    }

    public function markNoShow(User $user, Reservation $reservation): bool
    {
        return $user->can(Permission::CheckinManage->value) || $user->can(Permission::BookingsUpdate->value);
    }

    private function owns(User $user, Reservation $reservation): bool
    {
        return $reservation->guest()->where('user_id', $user->id)->exists();
    }
}
