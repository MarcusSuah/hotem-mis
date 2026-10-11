<?php

namespace App\Services\Booking;

use App\Enums\CancellationPenalty;
use App\Models\Reservation;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use BcMath\Number;
use Carbon\CarbonImmutable;

/**
 * Applies the cancellation policy snapshot stored on the reservation, so later
 * edits to the policy never change the terms the guest accepted.
 *
 * The free window is measured to the check-in time on the arrival date.
 * A "first night" penalty charges the first night plus its share of service
 * charge and tax.
 */
class CancellationCalculator
{
    public function __construct(private SettingsRepository $settings) {}

    public function fee(Reservation $reservation, CarbonImmutable $at): Money
    {
        $snapshot = $reservation->cancellation_policy_snapshot;
        $penalty = CancellationPenalty::tryFrom((string) ($snapshot['penalty_type'] ?? '')) ?? CancellationPenalty::None;
        $total = $reservation->total();

        if ($reservation->is_complimentary || $penalty === CancellationPenalty::None) {
            return Money::zero($reservation->currency);
        }

        if ($penalty === CancellationPenalty::FullStay) {
            return $total;
        }

        $freeHours = (int) ($snapshot['free_cancellation_hours'] ?? 0);

        if ($at->lessThanOrEqualTo($this->arrivalMoment($reservation)->subHours($freeHours))) {
            return Money::zero($reservation->currency);
        }

        return match ($penalty) {
            CancellationPenalty::FirstNight => $this->firstNightShare($reservation),
            default => $total->percentage((string) ($snapshot['penalty_percent'] ?? '0')),
        };
    }

    /**
     * Fee charged when a guest does not arrive: the first night, as is customary.
     */
    public function noShowFee(Reservation $reservation): Money
    {
        return $reservation->is_complimentary ? Money::zero($reservation->currency) : $this->firstNightShare($reservation);
    }

    public function arrivalMoment(Reservation $reservation): CarbonImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', (string) $this->settings->get('booking.check_in_time', '14:00')));

        return $reservation->check_in_date->setTime($hour, $minute);
    }

    private function firstNightShare(Reservation $reservation): Money
    {
        if ($reservation->room_total_minor <= 0) {
            return Money::zero($reservation->currency);
        }

        // Released reservations no longer hold night rows; fall back to the average night.
        $firstNight = (int) ($reservation->nightRecords()->value('rate_minor')
            ?? intdiv($reservation->room_total_minor, max(1, $reservation->nights)));

        $share = (new Number($firstNight))->div($reservation->room_total_minor, 12);

        return $reservation->total()->multiply($share->value);
    }
}
