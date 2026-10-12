<?php

use App\Enums\SystemRole;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Services\Settings\SettingsRepository;

function webEditor(): User
{
    return User::factory()->withRole(SystemRole::MarketingManager)->create();
}

it('opens website management only for website staff', function (string $route) {
    $this->actingAs(User::factory()->withRole(SystemRole::Receptionist)->create())->get(route($route))->assertForbidden();
    $this->actingAs(webEditor())->get(route($route))->assertOk();
})->with(['admin.website.content.index', 'admin.website.gallery.index', 'admin.website.enquiries.index', 'admin.website.settings.edit', 'admin.website.reviews.index']);

it('creates a page with a generated, stable address', function () {
    $editor = webEditor();

    $this->actingAs($editor)->post(route('admin.website.pages.store'), ['title' => 'House Policies', 'body' => 'Quiet hours start at 22:00.', 'is_published' => true, 'display_order' => 1])
        ->assertSessionHasNoErrors();

    $page = Page::query()->sole();
    expect($page)->slug->toBe('house-policies')->is_published->toBeTrue();

    $this->actingAs($editor)->put(route('admin.website.pages.update', $page), ['title' => 'Hotel Policies', 'body' => 'Updated.', 'display_order' => 1])
        ->assertSessionHasNoErrors();

    expect($page->fresh())->slug->toBe('house-policies')->is_published->toBeFalse();
});

it('saves a blog article without a publish date as a draft', function () {
    $this->actingAs(webEditor())->post(route('admin.website.posts.store'), ['title' => 'Monrovia in a weekend', 'body' => 'Start at Providence Island.'])
        ->assertSessionHasNoErrors();

    expect(Post::query()->sole())->published_at->toBeNull()->isPublished()->toBeFalse();
});

it('requires a public rate plan for offers', function () {
    $this->actingAs(webEditor())->post(route('admin.website.offers.store'), ['title' => 'Stay longer', 'rate_plan_id' => 999, 'display_order' => 0])
        ->assertSessionHasErrors('rate_plan_id');
});

it('updates the homepage and hotel details', function () {
    $this->actingAs(webEditor())->put(route('admin.website.settings.update'), [
        'hotel' => ['name' => 'Ocean Pearl', 'email' => 'hello@oceanpearl.example', 'phone' => '+231 77 111 2222', 'address' => 'Sinkor, Monrovia'],
        'website' => ['hero_title' => 'Wake up to the Atlantic'],
        'social' => ['instagram' => 'https://instagram.com/oceanpearl'],
        'seo' => [],
    ])->assertSessionHasNoErrors();

    $settings = app(SettingsRepository::class);
    expect($settings->get('hotel.name'))->toBe('Ocean Pearl')
        ->and($settings->get('website.hero_title'))->toBe('Wake up to the Atlantic')
        ->and($settings->get('social.instagram'))->toBe('https://instagram.com/oceanpearl');
});
