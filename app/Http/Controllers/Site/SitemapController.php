<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\HotelEvent;
use App\Models\Offer;
use App\Models\Page;
use App\Models\Post;
use App\Models\RoomType;
use Illuminate\Http\Response;

/**
 * XML sitemap of every public, published URL.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => null],
            ...collect(['site.about', 'site.rooms.index', 'site.offers.index', 'site.events.index', 'site.blog.index', 'site.gallery', 'site.dining', 'site.experiences', 'site.reviews', 'site.contact', 'site.booking.start'])
                ->map(fn (string $name) => ['loc' => route($name), 'lastmod' => null]),
        ])
            ->merge(RoomType::query()->active()->get(['slug', 'updated_at'])->map(fn (RoomType $type) => ['loc' => route('site.rooms.show', $type), 'lastmod' => $type->updated_at]))
            ->merge(Page::query()->published()->get(['slug', 'updated_at'])->map(fn (Page $page) => ['loc' => route('site.pages.show', $page), 'lastmod' => $page->updated_at]))
            ->merge(Post::query()->published()->get(['slug', 'updated_at'])->map(fn (Post $post) => ['loc' => route('site.blog.show', $post), 'lastmod' => $post->updated_at]))
            ->merge(Offer::query()->current()->get(['slug', 'updated_at'])->map(fn (Offer $offer) => ['loc' => route('site.offers.show', $offer), 'lastmod' => $offer->updated_at]))
            ->merge(HotelEvent::query()->upcoming()->get(['slug', 'updated_at'])->map(fn (HotelEvent $event) => ['loc' => route('site.events.show', $event), 'lastmod' => $event->updated_at]));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url><loc>'.e($url['loc']).'</loc>'.($url['lastmod'] ? '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>' : '').'</url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
