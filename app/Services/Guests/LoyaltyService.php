<?php

namespace App\Services\Guests;

use App\Models\Guest;
use App\Models\LoyaltyTransaction;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Booking\BookingException;
use App\Services\Settings\SettingsRepository;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Points ledger. Guests earn points per whole unit of base-currency room
 * revenue on check-out; complimentary stays earn nothing.
 */
class LoyaltyService
{
    public function __construct(
        private SettingsRepository $settings,
        private AuditLogger $audit,
    ) {}

    public function balance(Guest $guest): int
    {
        return (int) $guest->loyaltyTransactions()->sum('points');
    }

    public function earnForStay(Reservation $reservation): ?LoyaltyTransaction
    {
        if (! $this->settings->get('loyalty.enabled', true) || $reservation->is_complimentary) {
            return null;
        }

        $perUnit = (int) $this->settings->get('loyalty.points_per_unit', 1);
        $points = intdiv(max(0, $reservation->base_total_minor), 100) * $perUnit;

        if ($points <= 0) {
            return null;
        }

        try {
            return LoyaltyTransaction::create([
                'guest_id' => $reservation->guest_id,
                'reservation_id' => $reservation->id,
                'points' => $points,
                'reason' => "Stay {$reservation->reference}",
                'idempotency_key' => "stay:{$reservation->id}",
            ]);
        } catch (UniqueConstraintViolationException) {
            return LoyaltyTransaction::query()->where('idempotency_key', "stay:{$reservation->id}")->first();
        }
    }

    public function adjust(Guest $guest, int $points, string $reason, User $actor): LoyaltyTransaction
    {
        if ($points === 0) {
            throw new BookingException('Enter a non-zero number of points.', 'points');
        }

        if ($points < 0 && $this->balance($guest) + $points < 0) {
            throw new BookingException('The guest does not have enough points.', 'points');
        }

        $transaction = LoyaltyTransaction::create([
            'guest_id' => $guest->id,
            'points' => $points,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('loyalty.adjusted', $guest, newValues: ['points' => $points, 'reason' => $reason], actor: $actor);

        return $transaction;
    }
}
