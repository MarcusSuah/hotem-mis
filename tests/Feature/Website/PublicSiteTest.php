<?php

use App\Enums\SystemRole;
use App\Models\Enquiry;
use App\Models\HotelEvent;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Post;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\User;
use App\Notifications\NewEnquiry;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00:00');
});

it('renders every public listing page', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['home', 'site.rooms.index', 'site.offers.index', 'site.events.index', 'site.blog.index', 'site.gallery', 'site.dining', 'site.experiences', 'site.reviews', 'site.contact', 'site.booking.start']);

it('hides content that is not published', function () {
    $hiddenType = RoomType::factory()->inactive()->create();
    $draftPage = Page::factory()->create(['is_published' => false]);
    $scheduledPost = Post::factory()->create(['published_at' => now()->addDay()]);
    $draftEvent = HotelEvent::factory()->create(['is_published' => false]);
    $draftOffer = Offer::factory()->create(['is_published' => false]);

    $this->get(route('site.rooms.show', $hiddenType))->assertNotFound();
    $this->get(route('site.pages.show', $draftPage))->assertNotFound();
    $this->get(route('site.blog.show', $scheduledPost))->assertNotFound();
    $this->get(route('site.events.show', $draftEvent))->assertNotFound();
    $this->get(route('site.offers.show', $draftOffer))->assertNotFound();
});

it('renders page content as markdown with raw html removed', function () {
    $page = Page::factory()->create(['body' => "## House rules\n\n<script>alert('x')</script>\n\n[Bad link](javascript:alert(1)) and **quiet hours**."]);

    $this->get(route('site.pages.show', $page))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('page.html', fn (string $html) => str_contains($html, '<h2>House rules</h2>')
                && str_contains($html, '<strong>quiet hours</strong>')
                && ! str_contains($html, '<script')
                && ! str_contains($html, 'javascript:')));
});

it('publishes only approved reviews and never the guest’s full name', function () {
    $approved = Review::factory()->approved()->create(['title' => 'Wonderful']);
    Review::factory()->create(['title' => 'Not yet moderated']);
    $guest = $approved->guest;

    $this->get(route('site.reviews'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reviews.data', 1)
            ->where('reviews.data.0.title', 'Wonderful')
            ->where('reviews.data.0.author', $guest->first_name.' '.mb_substr($guest->last_name, 0, 1).'.')
            ->missing('reviews.data.0.email')
            ->where('summary.count', 1));
});

it('lists published urls in the sitemap', function () {
    $page = Page::factory()->create();
    $draft = Page::factory()->create(['is_published' => false]);

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('site.pages.show', $page), false)
        ->assertDontSee(route('site.pages.show', $draft), false);
});

it('stores contact messages and alerts website staff', function () {
    Notification::fake();
    $marketing = User::factory()->withRole(SystemRole::MarketingManager)->create();
    $receptionist = User::factory()->withRole(SystemRole::Receptionist)->create();

    $this->post(route('site.contact.store'), ['name' => 'Musu', 'email' => 'musu@example.com', 'subject' => 'Wedding', 'message' => 'Do you host weddings for 120 guests?'])
        ->assertSessionHasNoErrors();

    expect(Enquiry::query()->sole()->subject)->toBe('Wedding');
    Notification::assertSentTo($marketing, NewEnquiry::class);
    Notification::assertNotSentTo($receptionist, NewEnquiry::class);
});

it('drops contact messages that fill the hidden bot field', function () {
    $this->post(route('site.contact.store'), ['name' => 'Bot', 'email' => 'bot@example.com', 'subject' => 'Offer', 'message' => 'Buy cheap links now please', 'website' => 'x'])
        ->assertSessionHasErrors('website');

    expect(Enquiry::query()->count())->toBe(0);
});
