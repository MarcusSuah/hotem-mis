<?php

use App\Enums\ReservationStatus;
use App\Enums\ReviewStatus;
use App\Enums\SystemRole;
use App\Events\GuestCheckedOut;
use App\Listeners\InviteGuestToReview;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;
use App\Notifications\ArrivalReminder;
use App\Notifications\ReviewInvitation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00:00');
});

function accountHolder(): User
{
    $user = User::factory()->create();
    Guest::factory()->forUser($user)->create();

    return $user->load('guest');
}

it('shows guests only their own reservations', function () {
    $user = accountHolder();
    $mine = Reservation::factory()->for($user->guest)->create();
    $theirs = Reservation::factory()->create();

    $this->actingAs($user)->get(route('account.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 1)->where('reservations.0.reference', $mine->reference));

    $this->actingAs($user)->get(route('account.reservations.show', $theirs))->assertNotFound();
    $this->actingAs($user)->post(route('account.reservations.cancel', $theirs))->assertNotFound();

    expect($theirs->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('lets a guest cancel an upcoming booking', function () {
    $user = accountHolder();
    $reservation = Reservation::factory()->for($user->guest)->between('2026-11-20', '2026-11-22')->create();

    $this->actingAs($user)->post(route('account.reservations.cancel', $reservation))->assertRedirect();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
});

it('accepts one review per completed stay and holds it for moderation', function () {
    $user = accountHolder();
    $stayed = Reservation::factory()->for($user->guest)->checkedOut()->create();
    $upcoming = Reservation::factory()->for($user->guest)->create();
    $review = ['rating' => 5, 'title' => 'Restful', 'body' => 'Quiet room, kind staff and a lovely breakfast.'];

    $this->actingAs($user)->post(route('account.reservations.review', $upcoming), $review)->assertSessionHasErrors('rating');
    $this->actingAs($user)->post(route('account.reservations.review', $stayed), $review)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('account.reservations.review', $stayed), $review)->assertSessionHasErrors('rating');

    expect(Review::query()->sole())->status->toBe(ReviewStatus::Pending)->reservation_id->toBe($stayed->id);
});

it('lets moderators publish reviews', function () {
    $review = Review::factory()->create();

    $this->actingAs(User::factory()->withRole(SystemRole::Receptionist)->create())
        ->put(route('admin.website.reviews.update', $review), ['status' => 'approved'])
        ->assertForbidden();

    $this->actingAs(User::factory()->withRole(SystemRole::MarketingManager)->create())
        ->put(route('admin.website.reviews.update', $review), ['status' => 'approved', 'management_response' => 'Thank you for staying with us.'])
        ->assertSessionHasNoErrors();

    expect($review->fresh())->status->toBe(ReviewStatus::Approved)->management_response->toBe('Thank you for staying with us.');
});

it('sends each arrival reminder once', function () {
    Notification::fake();
    $due = Reservation::factory()->between('2026-11-12', '2026-11-14')->create();
    Reservation::factory()->between('2026-11-15', '2026-11-16')->create();
    Reservation::factory()->pending()->between('2026-11-12', '2026-11-13')->create();

    Artisan::call('hms:send-arrival-reminders');
    Artisan::call('hms:send-arrival-reminders');

    Notification::assertSentTimes(ArrivalReminder::class, 1);
    expect($due->fresh()->arrival_reminder_sent_at)->not->toBeNull();
});

it('invites account holders to review after check-out', function () {
    Notification::fake();
    $user = accountHolder();
    $reservation = Reservation::factory()->for($user->guest)->checkedOut()->create();
    $walkIn = Reservation::factory()->checkedOut()->create();
    $listener = app(InviteGuestToReview::class);

    $listener->handle(new GuestCheckedOut($reservation, Room::factory()->create()));
    $listener->handle(new GuestCheckedOut($reservation, Room::factory()->create()));
    $listener->handle(new GuestCheckedOut($walkIn, Room::factory()->create()));

    Notification::assertSentToTimes($user, ReviewInvitation::class, 1);
});
