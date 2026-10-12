<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Services\Settings\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Pre-arrival reminder with check-in time and hotel contact details.
 */
class ArrivalReminder extends Notification implements ShouldQueue
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
        $settings = app(SettingsRepository::class);
        $reservation = $this->reservation;
        $hotel = (string) $settings->get('hotel.name');

        return (new MailMessage)
            ->subject("See you soon at {$hotel}")
            ->greeting("Hello {$reservation->contact_name},")
            ->line("We look forward to welcoming you on {$reservation->check_in_date->toFormattedDayDateString()} (booking {$reservation->reference}).")
            ->line('Check-in is from '.$settings->get('booking.check_in_time').' and check-out is by '.$settings->get('booking.check_out_time').' on '.$reservation->check_out_date->toFormattedDayDateString().'.')
            ->line('Please bring the identity document you will register with.')
            ->line('Questions or a change of plans? Reply to this email or call '.$settings->get('hotel.phone').'.');
    }
}
