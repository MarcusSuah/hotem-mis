<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invites a guest with an online account to review a completed stay.
 */
class ReviewInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Reservation $reservation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $hotel = (string) app(SettingsRepository::class)->get('hotel.name');

        return (new MailMessage)
            ->subject("How was your stay at {$hotel}?")
            ->greeting("Thank you for staying with us, {$this->reservation->contact_name}.")
            ->line('We would value a few words about your stay. Reviews are read by our management team and, once approved, shared on our website.')
            ->action('Review your stay', route('account.reservations.show', $this->reservation));
    }
}
