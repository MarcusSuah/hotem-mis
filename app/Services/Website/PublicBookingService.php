<?php

namespace App\Services\Website;

use App\Enums\BookingSource;
use App\Enums\Currency;
use App\Models\Offer;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Notifications\ReservationReceived;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingException;
use App\Services\Booking\NewReservation;
use App\Services\Booking\PricingService;
use App\Services\Booking\ReservationService;
use App\Services\Booking\StayPeriod;
use App\Services\Booking\StayQuote;
use App\Services\Currency\MissingExchangeRateException;
use App\Services\Guests\GuestService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Notification;

/**
 * Availability search and self-service booking for website visitors.
 *
 * Prices always come from the pricing engine and bookings go through the
 * same transactional reservation service the front desk uses. Website
 * bookings are held as pending until the hotel takes a deposit.
 */
class PublicBookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private ReservationService $reservations,
        private GuestService $guests,
        private SettingsRepository $settings,
    ) {}

    /**
     * Room types with free rooms for the stay, each with a full quote.
     *
     * @return list<array{room_type: RoomType, available: int, quote: StayQuote}>
     */
    public function search(StayPeriod $stay, int $adults, int $children, Currency $currency, ?Offer $offer = null): array
    {
        $ratePlan = $this->ratePlanFor($offer, $stay);
        $results = [];

        foreach ($this->availability->roomTypesFor($stay, $adults, $children) as $row) {
            if ($row['available'] === 0 || ($offer?->room_type_id !== null && $offer->room_type_id !== $row['room_type']->id)) {
                continue;
            }

            $results[] = [
                'room_type' => $row['room_type'],
                'available' => $row['available'],
                'quote' => $this->quote($row['room_type'], $stay, $adults, $children, $currency, $ratePlan),
            ];
        }

        return $results;
    }

    /**
     * Quote in the visitor's currency, falling back to the base currency
     * when no exchange rate has been published yet.
     */
    public function quote(RoomType $roomType, StayPeriod $stay, int $adults, int $children, Currency $currency, ?RatePlan $ratePlan = null): StayQuote
    {
        try {
            return $this->pricing->quote($roomType, $ratePlan, $stay, $adults, $children, $currency);
        } catch (MissingExchangeRateException) {
            return $this->pricing->quote($roomType, $ratePlan, $stay, $adults, $children, Currency::from((string) $this->settings->get('currency.base', 'USD')));
        }
    }

    public function book(PublicBooking $booking): Reservation
    {
        $ratePlan = $this->ratePlanFor($booking->offer, $booking->stay);

        if ($booking->offer?->room_type_id !== null && $booking->offer->room_type_id !== $booking->roomType->id) {
            throw new BookingException('This offer is only available for another room type.', 'room_type');
        }

        $guest = $booking->user !== null
            ? $this->guests->forUser($booking->user)
            : $this->guests->findOrCreate([
                'first_name' => $booking->firstName,
                'last_name' => $booking->lastName,
                'email' => $booking->email,
                'phone' => $booking->phone,
                'country_code' => $booking->countryCode,
            ]);

        $currency = $this->quote($booking->roomType, $booking->stay, $booking->adults, $booking->children, $booking->currency, $ratePlan)->currency;

        $reservation = $this->reservations->create(new NewReservation(
            guest: $guest,
            roomType: $booking->roomType,
            stay: $booking->stay,
            adults: $booking->adults,
            children: $booking->children,
            currency: $currency,
            source: BookingSource::Website,
            ratePlan: $ratePlan,
            specialRequests: $booking->specialRequests,
            idempotencyKey: $booking->idempotencyKey,
            contactName: trim("{$booking->firstName} {$booking->lastName}"),
            contactEmail: $booking->email,
            contactPhone: $booking->phone,
            holdMinutes: (int) $this->settings->get('booking.website_hold_hours', 24) * 60,
            arrivalTime: $booking->arrivalTime,
        ));

        if ($reservation->wasRecentlyCreated && $this->settings->get('notifications.booking_confirmation', true)) {
            Notification::route('mail', [$booking->email => $reservation->contact_name])->notify(new ReservationReceived($reservation));
        }

        return $reservation;
    }

    /**
     * The rate plan an offer applies, after checking the stay qualifies.
     */
    private function ratePlanFor(?Offer $offer, StayPeriod $stay): ?RatePlan
    {
        if ($offer === null) {
            return null;
        }

        if (($offer->valid_from !== null && $stay->checkIn->lt($offer->valid_from))
            || ($offer->valid_until !== null && $stay->checkIn->gt($offer->valid_until))) {
            throw new BookingException('This offer is not available for the selected arrival date.', 'check_in');
        }

        $plan = $offer->ratePlan;

        return $plan !== null && $plan->is_active && $plan->is_public ? $plan : null;
    }
}
