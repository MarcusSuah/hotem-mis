<?php

namespace Database\Seeders;

use App\Enums\BookingSource;
use App\Enums\ChargeCategory;
use App\Enums\Currency;
use App\Enums\HousekeepingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Billing\FolioService;
use App\Services\Billing\PaymentService;
use App\Services\Booking\BookingException;
use App\Services\Booking\NewReservation;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\FrontDesk\CheckInDetails;
use App\Services\FrontDesk\FrontDeskService;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Demonstration reservations created through the real booking, front-desk
 * and billing services, so every record respects availability, pricing and
 * accounting rules. Historical stays are simulated by moving the clock, so
 * each completed stay has its payments, charges and invoice at realistic times.
 *
 * Reproducible (fixed random seed) and only generated when no reservations exist.
 */
class DemoReservationSeeder extends Seeder
{
    /** @var Collection<int, Guest> */
    private Collection $guests;

    /** @var Collection<int, RoomType> */
    private Collection $types;

    /** @var Collection<int, RatePlan> */
    private Collection $plans;

    public function __construct(
        private ReservationService $reservations,
        private FrontDeskService $frontDesk,
        private PaymentService $payments,
        private FolioService $folios,
    ) {}

    public function run(): void
    {
        if (Reservation::query()->exists()) {
            $this->command->warn('Reservations already exist; demo reservations skipped.');

            return;
        }

        mt_srand(2026);
        fake()->seed(2026);

        $this->guests = Guest::factory()->count(120)->create();
        $this->types = RoomType::query()->active()->get();
        $this->plans = RatePlan::query()->active()->with('cancellationPolicy')->get();
        $today = CarbonImmutable::today();

        try {
            $history = $this->seedHistory($today);
        } finally {
            Date::setTestNow();
        }

        $upcoming = $this->seedCurrentAndUpcoming($today);

        $this->command->info("Created {$history} completed or closed stays and {$upcoming} current or upcoming reservations.");
    }

    /**
     * Stays that started and ended in the past 120 days.
     */
    private function seedHistory(CarbonImmutable $today): int
    {
        $created = 0;

        for ($offset = -120; $offset <= -4; $offset++) {
            $arrival = $today->addDays($offset);
            $weekendBoost = in_array($arrival->dayOfWeekIso, [4, 5], true) ? 2 : 0;
            $attempts = mt_rand(2, 4) + $weekendBoost;

            for ($i = 0; $i < $attempts; $i++) {
                $nights = mt_rand(1, 4);

                if ($arrival->addDays($nights)->greaterThanOrEqualTo($today->subDay())) {
                    continue;
                }

                $reservation = $this->book($arrival, $nights, bookedDaysAhead: mt_rand(1, 30), confirm: true);

                if ($reservation === null) {
                    continue;
                }

                $created++;
                $roll = mt_rand(1, 100);

                if ($roll <= 6) {
                    $this->at($arrival->subDays(mt_rand(1, 5))->setTime(10, 0), fn () => $this->reservations->cancel($reservation, fake()->randomElement(['Change of travel plans', 'Flight cancelled', 'Illness']), waiveFee: true));
                } elseif ($roll <= 9) {
                    $this->at($arrival->addDay()->setTime(9, 0), fn () => $this->reservations->markNoShow($reservation));
                } else {
                    $this->completeStay($reservation);
                }
            }
        }

        return $created;
    }

    /**
     * In-house guests, today's arrivals, future bookings, pending holds and cancellations.
     */
    private function seedCurrentAndUpcoming(CarbonImmutable $today): int
    {
        $created = 0;

        for ($offset = -3; $offset <= 60; $offset++) {
            $attempts = $offset <= 7 ? 5 : 2;

            for ($i = 0; $i < $attempts; $i++) {
                $checkIn = $today->addDays($offset);
                $nights = mt_rand(1, 5);

                if ($checkIn->lessThan($today) && $checkIn->addDays($nights)->lessThanOrEqualTo($today)) {
                    $nights = (int) $checkIn->diffInDays($today) + mt_rand(1, 3);
                }

                $reservation = $offset < 0
                    ? $this->at($checkIn->subDays(mt_rand(2, 20))->setTime(11, 0), fn () => $this->book($checkIn, $nights, 0, true))
                    : $this->book($checkIn, $nights, 0, $offset < 2 || mt_rand(1, 6) > 1);

                if ($reservation === null) {
                    continue;
                }

                $created++;

                if ($offset < 0) {
                    $this->at($checkIn->setTime(15, 0), function () use ($reservation): void {
                        $this->payDeposit($reservation);
                        $this->frontDesk->checkIn($reservation, new CheckInDetails(waiveDeposit: false));
                    });
                } elseif ($offset > 5 && mt_rand(1, 8) === 1) {
                    $this->reservations->cancel($reservation, fake()->randomElement(['Change of travel plans', 'Booked elsewhere']), waiveFee: true);
                } elseif ($reservation->status === ReservationStatus::Confirmed && mt_rand(1, 3) === 1) {
                    $this->payDeposit($reservation);
                }
            }
        }

        Date::setTestNow();

        return $created;
    }

    private function book(CarbonImmutable $arrival, int $nights, int $bookedDaysAhead, bool $confirm): ?Reservation
    {
        $type = $this->types->random();
        $plan = $this->plans->random();

        if ($nights < $plan->min_nights) {
            $plan = $this->plans->firstWhere('is_default', true);
        }

        $data = new NewReservation(
            guest: $this->guests->random(),
            roomType: $type,
            stay: new StayPeriod($arrival, $arrival->addDays($nights)),
            adults: mt_rand(1, $type->max_adults),
            children: $type->max_children > 0 ? mt_rand(0, 1) : 0,
            currency: mt_rand(1, 4) === 1 ? Currency::LRD : Currency::USD,
            source: fake()->randomElement(BookingSource::cases()),
            ratePlan: $plan,
            specialRequests: mt_rand(1, 5) === 1 ? fake()->randomElement(['Late arrival around 22:00', 'High floor please', 'Celebrating an anniversary', 'Extra pillows']) : null,
            confirmImmediately: $confirm,
        );

        try {
            if ($bookedDaysAhead > 0) {
                return $this->at($arrival->subDays($bookedDaysAhead)->setTime(mt_rand(8, 20), mt_rand(0, 59)), fn () => $this->reservations->create($data));
            }

            return $this->reservations->create($data);
        } catch (BookingException) {
            return null;
        }
    }

    private function completeStay(Reservation $reservation): void
    {
        $arrival = $reservation->check_in_date;
        $departure = $reservation->check_out_date;

        $this->at($arrival->subDays(1)->setTime(12, 0), fn () => $this->payDeposit($reservation));

        $this->at($arrival->setTime(15, mt_rand(0, 59)), function () use ($reservation): void {
            $this->readyRoom($reservation);
            $this->frontDesk->checkIn($reservation, new CheckInDetails);
        });

        $folio = $this->folios->openFor($reservation);

        if (mt_rand(1, 3) === 1) {
            $this->at($arrival->setTime(20, 30), fn () => $this->folios->post(
                $folio,
                fake()->randomElement([ChargeCategory::FoodAndBeverage, ChargeCategory::Minibar, ChargeCategory::Laundry]),
                fake()->randomElement(['Dinner — Atlantic Grill', 'Minibar consumption', 'Laundry service', 'Room service breakfast']),
                $reservation->money(mt_rand(15, 90) * 100),
                serviceDate: $arrival,
            ));
        }

        $this->at($departure->setTime(10, mt_rand(0, 45)), function () use ($reservation, $folio): void {
            $fresh = $reservation->fresh() ?? $reservation;
            $this->folios->postStayNights($fresh, $fresh->check_out_date);
            $balance = $this->folios->summary($folio)->balance;

            if ($balance->isPositive()) {
                $this->payments->record($folio, $this->method(), $balance, externalReference: 'DEMO-'.mt_rand(100000, 999999));
            }

            $this->frontDesk->checkOut($fresh);
            $this->readyRoom($fresh);
        });
    }

    private function payDeposit(Reservation $reservation): void
    {
        $fresh = $reservation->fresh() ?? $reservation;
        $outstanding = $fresh->deposit_due_minor - $fresh->amount_paid_minor;

        if ($outstanding > 0) {
            $method = $this->method();
            $this->payments->record(
                $this->folios->openFor($fresh),
                $method,
                Money::ofMinor($outstanding, $fresh->currency),
                externalReference: $method === PaymentMethod::Cash ? null : 'DEMO-'.mt_rand(100000, 999999),
            );
        }
    }

    /**
     * Housekeeping is simulated as complete so the next guest can check in.
     */
    private function readyRoom(Reservation $reservation): void
    {
        $roomIds = $reservation->allocations()->pluck('room_id');

        Room::query()->whereIn('id', $roomIds)->update(['housekeeping_status' => HousekeepingStatus::Inspected->value]);
    }

    private function method(): PaymentMethod
    {
        return fake()->randomElement([PaymentMethod::Cash, PaymentMethod::Cash, PaymentMethod::Card, PaymentMethod::MobileMoney, PaymentMethod::BankTransfer]);
    }

    /**
     * Run a callback as if it happened at the given moment.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(CarbonImmutable $moment, callable $callback): mixed
    {
        Date::setTestNow($moment);

        try {
            return $callback();
        } finally {
            Date::setTestNow();
        }
    }
}
