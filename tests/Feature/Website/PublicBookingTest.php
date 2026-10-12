<?php

use App\Enums\BookingSource;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Offer;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Notifications\ReservationReceived;
use App\Services\Settings\SettingsRepository;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00:00');
    app(SettingsRepository::class)->setMany('booking', ['tax_rate' => '10', 'service_charge_rate' => '0', 'deposit_percent' => '50', 'website_hold_hours' => 24]);
    Notification::fake();
});

function bookableRoomType(int $rooms = 1, int $rateMinor = 10000): RoomType
{
    $type = RoomType::factory()->rate($rateMinor)->create(['slug' => 'garden-king']);
    Room::factory()->count($rooms)->for($type)->create();

    return $type;
}

/**
 * @return array<string, mixed>
 */
function bookingPayload(array $overrides = []): array
{
    return [
        'room_type' => 'garden-king',
        'check_in_date' => '2026-11-20',
        'check_out_date' => '2026-11-22',
        'adults' => 2,
        'children' => 0,
        'first_name' => 'Wokie',
        'last_name' => 'Kollie',
        'email' => 'Wokie@Example.com',
        'phone' => '+231 770 123 456',
        'accept_terms' => true,
        'idempotency_key' => (string) Str::uuid(),
        ...$overrides,
    ];
}

it('quotes only room types with a free room for the dates', function () {
    $available = bookableRoomType();
    $fullType = RoomType::factory()->rate(20000)->create();
    $fullRoom = Room::factory()->for($fullType)->create();
    Reservation::factory()->between('2026-11-19', '2026-11-23')->inRoom($fullRoom)->create();

    $this->get(route('site.booking.availability', ['check_in_date' => '2026-11-20', 'check_out_date' => '2026-11-22', 'adults' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('site/book/search')
            ->has('results', 1)
            ->where('results.0.room.slug', $available->slug)
            ->where('results.0.quote.total.formatted', 'USD 220.00'));
});

it('rejects a search that starts in the past', function () {
    $this->from(route('site.booking.start'))
        ->get(route('site.booking.availability', ['check_in_date' => '2026-11-01', 'check_out_date' => '2026-11-03', 'adults' => 2]))
        ->assertRedirect(route('site.booking.start'))
        ->assertSessionHasErrors('check_in_date');
});

it('holds a website booking as pending and emails the guest', function () {
    bookableRoomType();

    $response = $this->post(route('site.booking.store'), bookingPayload());

    $reservation = Reservation::query()->sole();
    $response->assertRedirectContains('/book/confirmation/'.$reservation->reference);

    expect($reservation)
        ->status->toBe(ReservationStatus::Pending)
        ->source->toBe(BookingSource::Website)
        ->total_minor->toBe(22000)
        ->contact_email->toBe('wokie@example.com')
        ->and($reservation->expires_at->toDateTimeString())->toBe('2026-11-11 10:00:00')
        ->and($reservation->guest->email)->toBe('wokie@example.com');

    Notification::assertSentTo(new AnonymousNotifiable, ReservationReceived::class, fn ($notification, $channels, $notifiable) => array_key_exists('wokie@example.com', $notifiable->routes['mail']));
});

it('creates one reservation when the same booking is submitted twice', function () {
    bookableRoomType(rooms: 2);
    $payload = bookingPayload();

    $this->post(route('site.booking.store'), $payload)->assertRedirect();
    $this->post(route('site.booking.store'), $payload)->assertRedirect();

    expect(Reservation::query()->count())->toBe(1);
    Notification::assertSentTimes(ReservationReceived::class, 1);
});

it('refuses submissions that fill the hidden bot field', function () {
    bookableRoomType();

    $this->post(route('site.booking.store'), bookingPayload(['website' => 'https://spam.example']))
        ->assertSessionHasErrors('website');

    expect(Reservation::query()->count())->toBe(0);
});

it('refuses a booking when the last room has just been taken', function () {
    $type = bookableRoomType();
    Reservation::factory()->between('2026-11-20', '2026-11-21')->inRoom($type->rooms()->sole())->create();

    $this->post(route('site.booking.store'), bookingPayload())
        ->assertSessionHasErrors('room_type_id');

    expect(Reservation::query()->where('source', BookingSource::Website->value)->count())->toBe(0);
});

it('links a signed-in guest booking to their own profile', function () {
    bookableRoomType();
    $user = User::factory()->create(['email' => 'member@example.com']);
    $guest = Guest::factory()->forUser($user)->create();

    $this->actingAs($user)->post(route('site.booking.store'), bookingPayload(['email' => 'someone-else@example.com']))->assertRedirect();

    expect(Reservation::query()->sole()->guest_id)->toBe($guest->id);
});

it('prices an offer with its linked rate plan and only for qualifying arrivals', function () {
    bookableRoomType();
    $plan = RatePlan::factory()->adjustment('-20')->create(['is_public' => true, 'is_active' => true]);
    $offer = Offer::factory()->create(['slug' => 'long-weekend', 'rate_plan_id' => $plan->id, 'valid_until' => '2026-11-30']);

    $this->post(route('site.booking.store'), bookingPayload(['offer' => $offer->slug]))->assertRedirect();
    expect(Reservation::query()->sole())->rate_plan_id->toBe($plan->id)->total_minor->toBe(17600);

    $this->post(route('site.booking.store'), bookingPayload(['offer' => $offer->slug, 'check_in_date' => '2026-12-05', 'check_out_date' => '2026-12-06']))
        ->assertSessionHasErrors('check_in');
});

it('shows the confirmation page only through its signed link', function () {
    $reservation = Reservation::factory()->pending()->create();

    $this->get(route('site.booking.confirmation', $reservation))->assertForbidden();

    $this->get(URL::temporarySignedRoute('site.booking.confirmation', now()->addDay(), ['reservation' => $reservation->reference]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('reservation.reference', $reservation->reference));
});
