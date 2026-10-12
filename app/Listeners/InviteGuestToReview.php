<?php

namespace App\Listeners;

use App\Events\GuestCheckedOut;
use App\Models\Reservation;
use App\Notifications\ReviewInvitation;
use App\Services\Settings\SettingsRepository;

/**
 * After check-out, invite guests who have an online account to review the stay.
 * Reviews require an account so every published review is tied to a real stay.
 */
class InviteGuestToReview
{
    public function __construct(private SettingsRepository $settings) {}

    public function handle(GuestCheckedOut $event): void
    {
        $reservation = $event->reservation->loadMissing('guest.user');
        $user = $reservation->guest->user;

        if ($user === null || ! $this->settings->get('notifications.review_invitation', true)) {
            return;
        }

        $claimed = Reservation::query()
            ->whereKey($reservation->id)
            ->whereNull('review_invited_at')
            ->update(['review_invited_at' => now()]);

        if ($claimed === 1) {
            $user->notify(new ReviewInvitation($reservation));
        }
    }
}
