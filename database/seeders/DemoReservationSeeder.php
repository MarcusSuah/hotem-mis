<?php

namespace Database\Seeders;

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\Booking\BookingException;
use App\Services\Booking\NewReservation;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demonstration reservations created through the real booking engine, so
 * every stay respects availability, capacity and pricing rules. Data is
 * reproducible (fixed random seed) and only generated when no reservations
 * exist yet.
 *
 * Covers in-house guests, today's arrivals, future bookings, pending holds
 * and cancellations. Completed historical stays are added once check-out and
 * billing exist, so no checked-out stay is ever seeded without its payments.
 */
class DemoReservationSeeder extends Seeder
{
    public function run(ReservationService $reservations): void
    {
        if (Reservation::query()->exists()) {
            $this->command->warn('Reservations already exist; demo reservations skipped.');

            return;
        }

        mt_srand(2026);
        fake()->seed(2026);

        $guests = Guest::factory()->count(80)->create();
        $types = RoomType::query()->active()->get();
        $plans = RatePlan::query()->active()->get();
        $today = CarbonImmutable::today();
        $created = 0;

        for ($offset = -3; $offset <= 60; $offset++) {
            $attempts = $offset <= 7 ? 5 : 2;

            for ($i = 0; $i < $attempts; $i++) {
                $type = $types->random();
                $nights = mt_rand(1, 5);
                $checkIn = $today->addDays($offset);
                $checkOut = $checkIn->addDays($nights);

                if ($checkIn->lessThan($today) && $checkOut->lessThanOrEqualTo($today)) {
                    $checkOut = $today->addDays(mt_rand(1, 3));
                }

                $plan = $plans->random();

                if ($checkIn->diffInDays($checkOut) < $plan->min_nights) {
                    $plan = $plans->firstWhere('is_default', true);
                }

                try {
                    $reservation = $reservations->create(new NewReservation(
                        guest: $guests->random(),
                        roomType: $type,
                        stay: new StayPeriod($checkIn, $checkOut),
                        adults: mt_rand(1, $type->max_adults),
                        children: $type->max_children > 0 ? mt_rand(0, 1) : 0,
                        currency: mt_rand(1, 4) === 1 ? Currency::LRD : Currency::USD,
                        source: fake()->randomElement(BookingSource::cases()),
                        ratePlan: $plan,
                        specialRequests: mt_rand(1, 5) === 1 ? fake()->randomElement(['Late arrival around 22:00', 'High floor please', 'Celebrating an anniversary', 'Extra pillows']) : null,
                        confirmImmediately: $offset < 0 || mt_rand(1, 6) > 1,
                    ));
                } catch (BookingException) {
                    continue;
                }

                $created++;

                if ($checkIn->lessThan($today)) {
                    $reservations->transition($reservation, ReservationStatus::CheckedIn, null, 'Seeded in-house guest', function (Reservation $locked) use ($checkIn): void {
                        $locked->forceFill(['checked_in_at' => $checkIn->setTime(15, mt_rand(0, 59))]);
                    });
                } elseif ($offset > 5 && mt_rand(1, 8) === 1) {
                    $reservations->cancel($reservation, fake()->randomElement(['Change of travel plans', 'Flight cancelled', 'Booked elsewhere']), waiveFee: true);
                }
            }
        }

        $this->command->info("Created {$created} demo reservations.");
    }
}
