<?php

namespace App\Http\Controllers\Admin\Website;

use App\Enums\Permission;
use App\Http\Controllers\Admin\Website\Concerns\ManagesWebsiteContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Website\WebsiteContentRequest;
use App\Models\HotelEvent;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Post;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Website pages, blog posts, events and offers.
 */
class ContentController extends Controller
{
    use ManagesWebsiteContent;

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        Gate::authorize(Permission::WebsiteManage->value);

        return Inertia::render('admin/website/content', [
            'pages' => Page::query()->orderBy('display_order')->orderBy('title')->get()
                ->map(fn (Page $page) => [
                    ...$page->only(['id', 'title', 'slug', 'summary', 'body', 'meta_title', 'meta_description', 'is_published', 'show_in_footer', 'display_order']),
                    'url' => route('site.pages.show', $page),
                    'updated_at' => $page->updated_at?->toIso8601String(),
                ]),
            'posts' => Post::query()->with('author:id,name')->orderByRaw('published_at is null desc')->orderByDesc('published_at')->limit(100)->get()
                ->map(fn (Post $post) => [
                    ...$post->only(['id', 'title', 'slug', 'excerpt', 'body', 'cover_image_alt', 'meta_title', 'meta_description']),
                    'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                    'is_live' => $post->isPublished(),
                    'author' => $post->author?->name,
                    'image_url' => $post->cover_image_url,
                    'url' => route('site.blog.show', $post),
                ]),
            'events' => HotelEvent::query()->orderByDesc('starts_at')->limit(100)->get()
                ->map(fn (HotelEvent $event) => [
                    ...$event->only(['id', 'title', 'slug', 'summary', 'body', 'venue', 'image_alt', 'is_published']),
                    'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                    'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
                    'image_url' => $event->image_url,
                    'url' => route('site.events.show', $event),
                ]),
            'offers' => Offer::query()->with(['ratePlan:id,name', 'roomType:id,name'])->orderBy('display_order')->orderBy('title')->get()
                ->map(fn (Offer $offer) => [
                    ...$offer->only(['id', 'title', 'slug', 'summary', 'body', 'image_alt', 'rate_plan_id', 'room_type_id', 'is_published', 'display_order']),
                    'valid_from' => $offer->valid_from?->toDateString(),
                    'valid_until' => $offer->valid_until?->toDateString(),
                    'rate_plan' => $offer->ratePlan?->name,
                    'room_type' => $offer->roomType?->name,
                    'image_url' => $offer->image_url,
                    'url' => route('site.offers.show', $offer),
                ]),
            'ratePlans' => RatePlan::query()->where('is_public', true)->where('is_active', true)->orderBy('display_order')->get(['id', 'name']),
            'roomTypes' => RoomType::query()->active()->ordered()->get(['id', 'name']),
        ]);
    }

    public function storePage(WebsiteContentRequest $request): RedirectResponse
    {
        $this->persistContent(new Page, $request);

        return $this->saved(__('Page created.'));
    }

    public function updatePage(WebsiteContentRequest $request, Page $page): RedirectResponse
    {
        $this->persistContent($page, $request);

        return $this->saved(__('Page updated.'));
    }

    public function destroyPage(Page $page): RedirectResponse
    {
        return $this->remove($page, 'page', __('Page removed.'));
    }

    public function storePost(WebsiteContentRequest $request): RedirectResponse
    {
        $post = new Post;
        $post->author_id = $request->user()->id;
        $this->persistContent($post, $request, 'cover_image_path', 'blog');

        return $this->saved(__('Article saved.'));
    }

    public function updatePost(WebsiteContentRequest $request, Post $post): RedirectResponse
    {
        $this->persistContent($post, $request, 'cover_image_path', 'blog');

        return $this->saved(__('Article updated.'));
    }

    public function destroyPost(Post $post): RedirectResponse
    {
        return $this->remove($post, 'post', __('Article removed.'));
    }

    public function storeEvent(WebsiteContentRequest $request): RedirectResponse
    {
        $this->persistContent(new HotelEvent, $request, 'image_path', 'events');

        return $this->saved(__('Event saved.'));
    }

    public function updateEvent(WebsiteContentRequest $request, HotelEvent $event): RedirectResponse
    {
        $this->persistContent($event, $request, 'image_path', 'events');

        return $this->saved(__('Event updated.'));
    }

    public function destroyEvent(HotelEvent $event): RedirectResponse
    {
        return $this->remove($event, 'event', __('Event removed.'));
    }

    public function storeOffer(WebsiteContentRequest $request): RedirectResponse
    {
        $this->persistContent(new Offer, $request, 'image_path', 'offers');

        return $this->saved(__('Offer saved.'));
    }

    public function updateOffer(WebsiteContentRequest $request, Offer $offer): RedirectResponse
    {
        $this->persistContent($offer, $request, 'image_path', 'offers');

        return $this->saved(__('Offer updated.'));
    }

    public function destroyOffer(Offer $offer): RedirectResponse
    {
        return $this->remove($offer, 'offer', __('Offer removed.'));
    }

    private function remove(Page|Post|HotelEvent|Offer $model, string $kind, string $message): RedirectResponse
    {
        Gate::authorize(Permission::WebsiteManage->value);

        $model->delete();
        $this->audit->record("website.{$kind}.deleted", $model, oldValues: ['title' => $model->title]);

        return $this->saved($message);
    }
}
