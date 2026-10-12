<?php

namespace App\Notifications;

use App\Models\Reservation;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the guest when a website booking request is received.
 */
class ReservationReceived extends Notification implements ShouldQueue
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
        $reservation = $this->reservation;
        $hotel = (string) app(SettingsRepository::class)->get('hotel.name');

        return (new MailMessage)
            ->subject("{$hotel}: booking request {$reservation->reference}")
            ->greeting("Thank you, {$reservation->contact_name}")
            ->line("We have received your booking request {$reservation->reference} for {$reservation->check_in_date->toFormattedDayDateString()} to {$reservation->check_out_date->toFormattedDayDateString()} ({$reservation->nights} nights).")
            ->line('Total for the stay: '.Money::ofMinor($reservation->total_minor, $reservation->currency)->format().'. Deposit to confirm: '.Money::ofMinor($reservation->deposit_due_minor, $reservation->currency)->format().'.')
            ->line('Your room is held while our reservations team confirms the booking and arranges your deposit. We will be in touch shortly.')
            ->action('View your booking', URL::temporarySignedRoute('site.booking.confirmation', now()->addDays(60), ['reservation' => $reservation->reference]))
            ->line('Please keep this email; the link above shows your booking details.');
    }
}
