<?php

namespace App\Services\Website;

use App\Enums\Currency;
use App\Models\Amenity;
use App\Models\GalleryImage;
use App\Models\HotelEvent;
use App\Models\Offer;
use App\Models\Post;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\RoomTypeImage;
use App\Services\Currency\CurrencyConverter;
use App\Services\Currency\MissingExchangeRateException;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Illuminate\Support\Facades\Storage;

/**
 * Shapes public website data. Only fields meant for visitors leave here.
 */
class SitePresenter
{
    public function __construct(
        private CurrencyConverter $converter,
        private ContentRenderer $content,
        private SettingsRepository $settings,
    ) {}

    /**
     * A website photo chosen in website settings (hero_image_path, about_image_path).
     */
    public function settingImage(string $key): ?string
    {
        $path = $this->settings->get("website.{$key}");

        return is_string($path) && $path !== '' ? Storage::disk(config('hms.uploads.public_disk'))->url($path) : null;
    }

    /**
     * Published hotel photos, those picked for the homepage first.
     *
     * @return list<array{id: int, url: string, alt: string, caption: string|null, category: string}>
     */
    public function hotelPhotos(int $limit, bool $featuredOnly = false): array
    {
        return array_values(GalleryImage::query()
            ->where('is_published', true)
            ->when($featuredOnly, fn ($query) => $query->where('is_featured', true))
            ->orderByDesc('is_featured')
            ->orderBy('display_order')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (GalleryImage $image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'alt' => $image->alt_text,
                'caption' => $image->caption,
                'category' => $image->category->label(),
            ])
            ->all());
    }

    /**
     * Standard nightly rate in the visitor's currency where a rate exists.
     */
    public function fromPrice(RoomType $type, Currency $currency): Money
    {
        try {
            return $this->converter->convert($type->baseRate(), $currency)->converted;
        } catch (MissingExchangeRateException) {
            return $type->baseRate();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function roomTypeCard(RoomType $type, Currency $currency): array
    {
        return [
            'slug' => $type->slug,
            'name' => $type->name,
            'short_description' => $type->short_description,
            'image_url' => $type->featured_image_url ?? ($type->relationLoaded('images') ? $type->images->first()?->url : null),
            'image_alt' => $type->featured_image_alt ?? $type->name,
            'max_occupancy' => $type->max_occupancy,
            'bed' => trim($type->bed_count.' × '.$type->bed_type),
            'size_sqm' => $type->size_sqm,
            'from_price' => $this->fromPrice($type, $currency),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function roomTypeDetail(RoomType $type, Currency $currency): array
    {
        return [
            ...$this->roomTypeCard($type, $currency),
            'description_html' => $this->content->toHtml($type->description),
            'max_adults' => $type->max_adults,
            'max_children' => $type->max_children,
            'bathroom_details' => $type->bathroom_details,
            'images' => $type->images->map(fn (RoomTypeImage $image) => ['url' => $image->url, 'alt' => $image->alt_text ?? $type->name])->values(),
            'amenities' => $type->amenities->map(fn (Amenity $amenity) => ['name' => $amenity->name, 'icon' => $amenity->icon])->values(),
            'meta_title' => $type->meta_title,
            'meta_description' => $type->meta_description ?? $this->content->excerpt($type->short_description ?? $type->description),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function offerCard(Offer $offer): array
    {
        return [
            'slug' => $offer->slug,
            'title' => $offer->title,
            'summary' => $offer->summary,
            'image_url' => $offer->image_url,
            'image_alt' => $offer->image_alt ?? $offer->title,
            'valid_until' => $offer->valid_until?->toDateString(),
            'room_type' => $offer->roomType?->only(['slug', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventCard(HotelEvent $event): array
    {
        return [
            'slug' => $event->slug,
            'title' => $event->title,
            'summary' => $event->summary,
            'venue' => $event->venue,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'image_url' => $event->image_url,
            'image_alt' => $event->image_alt ?? $event->title,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function postCard(Post $post): array
    {
        return [
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt ?? $this->content->excerpt($post->body),
            'image_url' => $post->cover_image_url,
            'image_alt' => $post->cover_image_alt ?? $post->title,
            'published_at' => $post->published_at?->toIso8601String(),
            'author' => $post->author?->name,
        ];
    }

    /**
     * Public review: first name and last initial only, never contact details.
     *
     * @return array<string, mixed>
     */
    public function review(Review $review): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => $review->body,
            'author' => $review->displayName(),
            'stayed' => $review->reservation->check_in_date->format('F Y'),
            'response' => $review->management_response,
        ];
    }
}
