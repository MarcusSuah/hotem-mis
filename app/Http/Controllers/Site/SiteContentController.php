<?php

namespace App\Http\Controllers\Site;

use App\Enums\GalleryCategory;
use App\Http\Controllers\Controller;
use App\Models\AdditionalService;
use App\Models\Amenity;
use App\Models\GalleryImage;
use App\Models\HotelEvent;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Post;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Settings\SettingsRepository;
use App\Services\Website\ContentRenderer;
use App\Services\Website\SitePresenter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Published CMS content: pages, offers, events, blog, gallery, dining,
 * experiences and approved reviews. Drafts are never reachable here.
 */
class SiteContentController extends Controller
{
    public function __construct(
        private SitePresenter $present,
        private ContentRenderer $content,
    ) {}

    public function page(Page $page): Response
    {
        abort_unless($page->is_published, 404);

        return Inertia::render('site/page', [
            'page' => [
                'title' => $page->title,
                'summary' => $page->summary,
                'html' => $this->content->toHtml($page->body),
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description ?? $page->summary ?? $this->content->excerpt($page->body),
                'updated_at' => $page->updated_at?->toIso8601String(),
            ],
        ]);
    }

    public function about(SettingsRepository $settings): Response
    {
        $website = $settings->group('website');
        $approved = Review::query()->approved();

        return Inertia::render('site/about', [
            'about' => [
                'title' => $website['about_title'] ?? null,
                'intro' => $website['about_intro'] ?? null,
                'html' => $this->content->toHtml($website['about_body'] ?? null),
                'image_url' => $this->present->settingImage('about_image_path') ?? $this->present->settingImage('hero_image_path'),
            ],
            'facts' => [
                'rooms' => Room::query()->count(),
                'room_types' => RoomType::query()->active()->count(),
                'rating' => round((float) (clone $approved)->avg('rating'), 1),
                'reviews' => (clone $approved)->count(),
                'check_in' => $settings->get('booking.check_in_time'),
                'check_out' => $settings->get('booking.check_out_time'),
            ],
            'amenities' => Amenity::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->limit(12)->get()
                ->map(fn (Amenity $amenity) => ['name' => $amenity->name, 'icon' => $amenity->icon]),
            'services' => AdditionalService::query()->where('is_active', true)->orderByDesc('is_featured')->orderBy('display_order')->limit(4)->get()
                ->map(fn (AdditionalService $service) => [...$service->only(['name', 'short_description']), 'image_url' => $service->image_url]),
            'photos' => $this->present->hotelPhotos(9),
        ]);
    }

    public function offers(): Response
    {
        return Inertia::render('site/offers/index', [
            'offers' => Offer::query()->current()->with('roomType:id,slug,name')->orderBy('display_order')->get()
                ->map(fn (Offer $offer) => $this->present->offerCard($offer)),
        ]);
    }

    public function offer(Offer $offer): Response
    {
        abort_unless($offer->is_published, 404);

        $offer->loadMissing(['roomType:id,slug,name', 'ratePlan.cancellationPolicy']);
        $expired = $offer->valid_until !== null && $offer->valid_until->isPast() && ! $offer->valid_until->isToday();

        return Inertia::render('site/offers/show', [
            'offer' => [
                ...$this->present->offerCard($offer),
                'html' => $this->content->toHtml($offer->body),
                'valid_from' => $offer->valid_from?->toDateString(),
                'includes_breakfast' => (bool) $offer->ratePlan?->includes_breakfast,
                'policy' => $offer->ratePlan?->cancellationPolicy?->summary(),
                'expired' => $expired,
                'meta_description' => $offer->summary ?? $this->content->excerpt($offer->body),
            ],
        ]);
    }

    public function events(): Response
    {
        return Inertia::render('site/events/index', [
            'events' => HotelEvent::query()->upcoming()->orderBy('starts_at')->get()
                ->map(fn (HotelEvent $event) => $this->present->eventCard($event)),
        ]);
    }

    public function event(HotelEvent $event): Response
    {
        abort_unless($event->is_published, 404);

        return Inertia::render('site/events/show', [
            'event' => [
                ...$this->present->eventCard($event),
                'html' => $this->content->toHtml($event->body),
                'is_past' => ($event->ends_at ?? $event->starts_at)->isPast(),
                'meta_description' => $event->summary ?? $this->content->excerpt($event->body),
            ],
        ]);
    }

    public function blog(): Response
    {
        return Inertia::render('site/blog/index', [
            'posts' => Post::query()->published()->with('author:id,name')->latest('published_at')->paginate(9)
                ->through(fn (Post $post) => $this->present->postCard($post)),
        ]);
    }

    public function post(Post $post): Response
    {
        abort_unless($post->isPublished(), 404);

        $post->loadMissing('author:id,name');

        return Inertia::render('site/blog/show', [
            'post' => [
                ...$this->present->postCard($post),
                'html' => $this->content->toHtml($post->body),
                'meta_title' => $post->meta_title,
                'meta_description' => $post->meta_description ?? $post->excerpt ?? $this->content->excerpt($post->body),
            ],
            'related' => Post::query()->published()->whereKeyNot($post->id)->with('author:id,name')->latest('published_at')->limit(3)->get()
                ->map(fn (Post $related) => $this->present->postCard($related)),
        ]);
    }

    public function gallery(): Response
    {
        return Inertia::render('site/gallery', [
            'images' => GalleryImage::query()->where('is_published', true)->orderBy('display_order')->orderByDesc('id')->get()
                ->map(fn (GalleryImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'alt' => $image->alt_text,
                    'caption' => $image->caption,
                    'category' => $image->category->value,
                ]),
            'categories' => GalleryCategory::options(),
        ]);
    }

    public function dining(): Response
    {
        return Inertia::render('site/dining', [
            'menu' => MenuCategory::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')
                ->with(['items' => fn ($query) => $query->where('is_available', true)->orderBy('display_order')->orderBy('name')])
                ->get()
                ->filter(fn (MenuCategory $category) => $category->items->isNotEmpty())
                ->map(fn (MenuCategory $category) => [
                    'name' => $category->name,
                    'items' => $category->items->map(fn (MenuItem $item) => [
                        ...$item->only(['name', 'description', 'is_featured']),
                        'price' => $item->price(),
                        'image_url' => $item->image_url,
                    ]),
                ])->values(),
        ]);
    }

    public function experiences(): Response
    {
        return Inertia::render('site/experiences', [
            'services' => AdditionalService::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get()
                ->map(fn (AdditionalService $service) => [
                    ...$service->only(['slug', 'name', 'short_description', 'unit', 'is_featured']),
                    'html' => $this->content->toHtml($service->description),
                    'category' => $service->category->label(),
                    'price' => $service->price(),
                    'image_url' => $service->image_url,
                ]),
        ]);
    }

    public function reviews(): Response
    {
        $approved = Review::query()->approved();

        return Inertia::render('site/reviews', [
            'summary' => [
                'average' => round((float) (clone $approved)->avg('rating'), 1),
                'count' => (clone $approved)->count(),
                'distribution' => (clone $approved)->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating'),
            ],
            'reviews' => (clone $approved)->with(['guest:id,first_name,last_name', 'reservation:id,check_in_date'])->latest()->paginate(10)
                ->through(fn (Review $review) => $this->present->review($review)),
        ]);
    }
}
