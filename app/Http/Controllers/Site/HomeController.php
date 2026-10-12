<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AdditionalService;
use App\Models\HotelEvent;
use App\Models\Offer;
use App\Models\Post;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\Currency\DisplayCurrencyResolver;
use App\Services\Settings\SettingsRepository;
use App\Services\Website\ContentRenderer;
use App\Services\Website\SitePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, SitePresenter $present, SettingsRepository $settings, DisplayCurrencyResolver $currencies, ContentRenderer $content): Response
    {
        $currency = $currencies->resolve($request);
        $website = $settings->group('website');
        $approved = Review::query()->approved();

        return Inertia::render('site/home', [
            'hero' => [
                'eyebrow' => $website['hero_eyebrow'] ?? null,
                'title' => $website['hero_title'] ?? null,
                'subtitle' => $website['hero_subtitle'] ?? null,
                'image_url' => $present->settingImage('hero_image_path'),
            ],
            'about' => [
                'title' => $website['about_title'] ?? null,
                'intro' => $website['about_intro'] ?? null,
                'html' => $content->toHtml($website['about_body'] ?? null),
                'image_url' => $present->settingImage('about_image_path'),
            ],
            'facts' => [
                'rooms' => Room::query()->count(),
                'room_types' => RoomType::query()->active()->count(),
                'check_in' => $settings->get('booking.check_in_time'),
            ],
            'location' => [
                'map_embed_url' => $settings->get('hotel.map_embed_url'),
            ],
            'rooms' => RoomType::query()->active()->orderByDesc('is_featured')->ordered()->with('images')->limit(6)->get()
                ->map(fn (RoomType $type) => $present->roomTypeCard($type, $currency)),
            'offers' => Offer::query()->current()->with('roomType:id,slug,name')->orderBy('display_order')->limit(3)->get()
                ->map(fn (Offer $offer) => $present->offerCard($offer)),
            'events' => HotelEvent::query()->upcoming()->orderBy('starts_at')->limit(3)->get()
                ->map(fn (HotelEvent $event) => $present->eventCard($event)),
            'posts' => Post::query()->published()->with('author:id,name')->latest('published_at')->limit(3)->get()
                ->map(fn (Post $post) => $present->postCard($post)),
            'services' => AdditionalService::query()->where('is_active', true)->where('is_featured', true)->orderBy('display_order')->limit(4)->get()
                ->map(fn (AdditionalService $service) => [
                    ...$service->only(['slug', 'name', 'short_description', 'unit']),
                    'image_url' => $service->image_url,
                ]),
            'reviews' => [
                'average' => round((float) (clone $approved)->avg('rating'), 1),
                'count' => (clone $approved)->count(),
                'latest' => (clone $approved)->with(['guest:id,first_name,last_name', 'reservation:id,check_in_date'])->latest()->limit(3)->get()
                    ->map(fn (Review $review) => $present->review($review)),
            ],
            'gallery' => $present->hotelPhotos(7),
        ]);
    }
}
