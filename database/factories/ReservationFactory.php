<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Raw reservation rows. Use `inRoom()` to also create a coherent allocation and
 * night records, priced at the room type's standard rate with no tax.
 *
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = CarbonImmutable::today()->addDays(fake()->numberBetween(3, 30));
        $nights = fake()->numberBetween(1, 5);

        return [
            'reference' => 'TST-'.Str::upper(Str::random(8)),
            'property_id' => fn () => Property::query()->where('is_default', true)->value('id') ?? Property::factory()->default(),
            'guest_id' => Guest::factory(),
            'source' => BookingSource::Phone,
            'status' => ReservationStatus::Confirmed,
            'payment_status' => PaymentStatus::Unpaid,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkIn->addDays($nights),
            'nights' => $nights,
            'adults' => 2,
            'children' => 0,
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'currency' => Currency::USD,
            'base_currency' => Currency::USD,
            'room_total_minor' => 15000 * $nights,
            'total_minor' => 15000 * $nights,
            'base_total_minor' => 15000 * $nights,
            'cancellation_policy_snapshot' => ['penalty_type' => 'none', 'free_cancellation_hours' => 0, 'penalty_percent' => '0'],
        ];
    }

    public function between(string $checkIn, string $checkOut): static
    {
        return $this->state(function (array $attributes) use ($checkIn, $checkOut) {
            $nights = (int) CarbonImmutable::parse($checkIn)->diffInDays(CarbonImmutable::parse($checkOut));

            return [
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'nights' => $nights,
                'room_total_minor' => 15000 * $nights,
                'total_minor' => 15000 * $nights,
                'base_total_minor' => 15000 * $nights,
            ];
        });
    }

    /**
     * Allocate the stay to a room with one night record per night.
     */
    public function inRoom(Room $room): static
    {
        return $this->afterCreating(function (Reservation $reservation) use ($room): void {
            $rate = intdiv($reservation->room_total_minor, max(1, $reservation->nights));

            $allocation = ReservationRoom::create([
                'reservation_id' => $reservation->id,
                'room_id' => $room->id,
                'room_type_id' => $room->room_type_id,
                'start_date' => $reservation->check_in_date,
                'end_date' => $reservation->check_out_date,
                'room_total_minor' => $reservation->room_total_minor,
            ]);

            if (! $reservation->status->holdsInventory()) {
                $allocation->forceFill(['released_at' => now(), 'release_reason' => $reservation->status->label()])->save();

                return;
            }

            for ($night = $reservation->check_in_date; $night->lessThan($reservation->check_out_date); $night = $night->addDay()) {
                $allocation->nights()->create([
                    'reservation_id' => $reservation->id,
                    'room_id' => $room->id,
                    'room_type_id' => $room->room_type_id,
                    'night_date' => $night,
                    'rate_minor' => $rate,
                    'base_rate_minor' => $rate,
                ]);
            }
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Pending,
            'expires_at' => now()->addHour(),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Confirmed,
        ])->afterMaking(fn (Reservation $reservation) => $reservation->forceFill(['confirmed_at' => now()]));
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::CheckedIn,
            'check_in_date' => CarbonImmutable::today()->subDay(),
            'check_out_date' => CarbonImmutable::today()->addDays(2),
            'nights' => 3,
            'room_total_minor' => 45000,
            'total_minor' => 45000,
            'base_total_minor' => 45000,
        ])->afterMaking(fn (Reservation $reservation) => $reservation->forceFill([
            'confirmed_at' => now()->subDays(5),
            'checked_in_at' => now()->subDay(),
        ]));
    }

    public function checkedOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::CheckedOut,
            'payment_status' => PaymentStatus::Paid,
        ])->afterMaking(fn (Reservation $reservation) => $reservation->forceFill([
            'confirmed_at' => $reservation->check_in_date->subDays(7),
            'checked_in_at' => $reservation->check_in_date->setTime(14, 30),
            'checked_out_at' => $reservation->check_out_date->setTime(10, 45),
            'amount_paid_minor' => $reservation->total_minor,
        ]));
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::Cancelled,
            'cancellation_reason' => 'Change of plans',
        ])->afterMaking(fn (Reservation $reservation) => $reservation->forceFill(['cancelled_at' => now()]));
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReservationStatus::NoShow,
        ])->afterMaking(fn (Reservation $reservation) => $reservation->forceFill(['no_show_at' => $reservation->check_in_date->addDay()]));
    }

    public function complimentary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_complimentary' => true,
            'room_total_minor' => 0,
            'total_minor' => 0,
            'base_total_minor' => 0,
        ]);
    }
}
