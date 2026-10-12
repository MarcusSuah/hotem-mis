<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\BookingSearchRequest;
use App\Http\Requests\Site\PublicBookingRequest;
use App\Models\Offer;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Services\Currency\DisplayCurrencyResolver;
use App\Services\Settings\SettingsRepository;
use App\Services\Website\PublicBookingService;
use App\Services\Website\SitePresenter;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public booking: search, choose a room type, enter details, confirmation.
 */
class BookingController extends Controller
{
    public function __construct(
        private PublicBookingService $booking,
        private SitePresenter $present,
        private DisplayCurrencyResolver $currencies,
        private SettingsRepository $settings,
    ) {}

    public function start(Request $request): Response
    {
        $offer = $request->filled('offer')
            ? Offer::query()->current()->where('slug', $request->string('offer')->toString())->first()
            : null;

        return $this->searchPage([
            'check_in_date' => '',
            'check_out_date' => '',
            'adults' => 2,
            'children' => 0,
            'offer' => $offer?->slug,
        ], $offer, null);
    }

    public function availability(BookingSearchRequest $request): Response
    {
        $offer = $request->offer();
        $currency = $this->currencies->resolve($request);

        $results = collect($this->booking->search($request->stay(), $request->adults(), $request->children(), $currency, $offer))
            ->map(fn (array $row) => [
                'room' => $this->present->roomTypeCard($row['room_type']->loadMissing('images'), $currency),
                'available' => $row['available'],
                'quote' => $row['quote']->toArray(),
            ]);

        return $this->searchPage($this->criteria($request, $offer), $offer, $results);
    }

    public function details(BookingSearchRequest $request, RoomType $roomType): Response
    {
        abort_unless($roomType->is_active, 404);

        $offer = $request->offer();
        $currency = $this->currencies->resolve($request);
        $match = collect($this->booking->search($request->stay(), $request->adults(), $request->children(), $currency, $offer))
            ->first(fn (array $row) => $row['room_type']->is($roomType));

        $guest = $request->user()?->loadMissing('guest')->guest;

        return Inertia::render('site/book/details', [
            'criteria' => $this->criteria($request, $offer),
            'room' => $this->present->roomTypeCard($roomType->loadMissing('images'), $currency),
            'quote' => $match ? $match['quote']->toArray() : null,
            'offer' => $offer ? ['slug' => $offer->slug, 'title' => $offer->title] : null,
            'policy' => $this->policySummary($offer),
            'prefill' => [
                'first_name' => $guest->first_name ?? '',
                'last_name' => $guest->last_name ?? '',
                'email' => $guest->email ?? $request->user()->email ?? '',
                'phone' => $guest->phone ?? $request->user()->phone ?? '',
                'country_code' => $guest->country_code ?? '',
            ],
            'note' => $this->settings->get('website.booking_note'),
            'holdHours' => (int) $this->settings->get('booking.website_hold_hours', 24),
        ]);
    }

    public function store(PublicBookingRequest $request): RedirectResponse
    {
        $reservation = $this->booking->book($request->booking($this->currencies->resolve($request)));

        return redirect(URL::temporarySignedRoute('site.booking.confirmation', now()->addDays(60), ['reservation' => $reservation->reference]));
    }

    public function confirmation(Reservation $reservation): Response
    {
        $reservation->loadMissing(['activeAllocations.roomType:id,name,slug', 'allocations.roomType:id,name,slug']);
        $roomType = ($reservation->activeAllocations->first() ?? $reservation->allocations->first())?->roomType;

        return Inertia::render('site/book/confirmation', [
            'reservation' => [
                'reference' => $reservation->reference,
                'status' => $reservation->status->value,
                'status_label' => $reservation->status->label(),
                'contact_name' => $reservation->contact_name,
                'contact_email' => $reservation->contact_email,
                'check_in_date' => $reservation->check_in_date->toDateString(),
                'check_out_date' => $reservation->check_out_date->toDateString(),
                'nights' => $reservation->nights,
                'adults' => $reservation->adults,
                'children' => $reservation->children,
                'room_type' => $roomType?->name,
                'total' => Money::ofMinor($reservation->total_minor, $reservation->currency),
                'deposit_due' => Money::ofMinor($reservation->deposit_due_minor, $reservation->currency),
                'expires_at' => $reservation->expires_at?->toIso8601String(),
            ],
            'checkInTime' => $this->settings->get('booking.check_in_time'),
            'checkOutTime' => $this->settings->get('booking.check_out_time'),
            'note' => $this->settings->get('website.booking_note'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  Collection<int, array{room: array<string, mixed>, available: int, quote: array<string, mixed>}>|null  $results
     */
    private function searchPage(array $criteria, ?Offer $offer, ?Collection $results): Response
    {
        return Inertia::render('site/book/search', [
            'criteria' => $criteria,
            'offer' => $offer ? ['slug' => $offer->slug, 'title' => $offer->title, 'summary' => $offer->summary] : null,
            'results' => $results,
            'limits' => [
                'max_nights' => (int) $this->settings->get('booking.max_stay_nights', 30),
                'max_advance_days' => (int) $this->settings->get('booking.max_advance_days', 365),
            ],
        ]);
    }

    /**
     * @return array{check_in_date: string, check_out_date: string, adults: int, children: int, offer: string|null}
     */
    private function criteria(BookingSearchRequest $request, ?Offer $offer): array
    {
        return [
            'check_in_date' => $request->stay()->checkIn->toDateString(),
            'check_out_date' => $request->stay()->checkOut->toDateString(),
            'adults' => $request->adults(),
            'children' => $request->children(),
            'offer' => $offer?->slug,
        ];
    }

    private function policySummary(?Offer $offer): ?string
    {
        $plan = $offer?->ratePlan?->loadMissing('cancellationPolicy')
            ?? RatePlan::query()->active()->where('is_default', true)->with('cancellationPolicy')->first();

        return $plan?->cancellationPolicy?->summary();
    }
}
