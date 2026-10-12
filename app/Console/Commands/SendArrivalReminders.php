<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Notifications\ArrivalReminder;
use App\Services\Settings\SettingsRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('hms:send-arrival-reminders')]
#[Description('Email confirmed guests a reminder ahead of their arrival date')]
class SendArrivalReminders extends Command
{
    public function handle(SettingsRepository $settings): int
    {
        $arrival = CarbonImmutable::today()->addDays((int) $settings->get('booking.arrival_reminder_days', 2));
        $sent = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Confirmed->value)
            ->whereDate('check_in_date', $arrival->toDateString())
            ->whereNull('arrival_reminder_sent_at')
            ->whereNotNull('contact_email')
            ->orderBy('id')
            ->each(function (Reservation $reservation) use (&$sent): void {
                // Mark first so a crash mid-run never sends the same reminder twice.
                $claimed = Reservation::query()
                    ->whereKey($reservation->id)
                    ->whereNull('arrival_reminder_sent_at')
                    ->update(['arrival_reminder_sent_at' => now()]);

                if ($claimed === 1) {
                    Notification::route('mail', [(string) $reservation->contact_email => $reservation->contact_name])
                        ->notify(new ArrivalReminder($reservation));
                    $sent++;
                }
            });

        $this->components->info("Sent {$sent} arrival reminder(s).");

        return self::SUCCESS;
    }
}
