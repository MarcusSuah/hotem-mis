<?php

namespace App\Http\Controllers\Admin\Website;

use App\Enums\EnquiryStatus;
use App\Enums\GalleryCategory;
use App\Enums\Permission;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Website\WebsiteManagementRequest;
use App\Models\Enquiry;
use App\Models\GalleryImage;
use App\Models\Review;
use App\Services\Audit\AuditLogger;
use App\Services\Media\ImageStorage;
use App\Services\Settings\SettingsRepository;
use App\Services\Website\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gallery, review moderation, contact enquiries and website settings.
 */
class WebsiteManagementController extends Controller
{
    public function __construct(
        private AuditLogger $audit,
        private ImageStorage $images,
        private SettingsRepository $settings,
    ) {}

    public function gallery(): Response
    {
        Gate::authorize(Permission::WebsiteManage->value);

        return Inertia::render('admin/website/gallery', [
            'images' => GalleryImage::query()->orderBy('category')->orderBy('display_order')->orderByDesc('id')->get()
                ->map(fn (GalleryImage $image) => [
                    ...$image->only(['id', 'alt_text', 'caption', 'display_order', 'is_published', 'is_featured']),
                    'category' => $image->category->value,
                    'category_label' => $image->category->label(),
                    'url' => $image->url,
                ]),
            'categories' => GalleryCategory::options(),
        ]);
    }

    public function storeGallery(WebsiteManagementRequest $request): RedirectResponse
    {
        /** @var list<UploadedFile> $photos */
        $photos = $request->file('photos', []);
        $category = GalleryCategory::from($request->string('category')->toString());

        foreach ($photos as $photo) {
            $image = new GalleryImage(['alt_text' => $request->string('alt_text')->toString(), 'category' => $category, 'display_order' => 0, 'is_published' => true]);
            $image->path = $this->images->store($photo, 'gallery');
            $image->save();
        }

        return $this->saved(trans_choice(':count photo added.|:count photos added.', count($photos)));
    }

    public function updateGallery(WebsiteManagementRequest $request, GalleryImage $image): RedirectResponse
    {
        $image->update([...$request->validated(), 'is_published' => $request->boolean('is_published'), 'is_featured' => $request->boolean('is_featured')]);

        return $this->saved(__('Photo updated.'));
    }

    public function destroyGallery(GalleryImage $image): RedirectResponse
    {
        Gate::authorize(Permission::WebsiteManage->value);

        $image->delete();
        $this->images->delete($image->path);

        return $this->saved(__('Photo removed.'));
    }

    public function reviews(Request $request): Response
    {
        Gate::authorize(Permission::ReviewsModerate->value);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(ReviewStatus::class)]]);
        $status = $filters['status'] ?? ReviewStatus::Pending->value;

        return Inertia::render('admin/website/reviews', [
            'reviews' => Review::query()
                ->with(['guest:id,first_name,last_name', 'reservation:id,reference,check_in_date,check_out_date', 'moderator:id,name'])
                ->where('status', $status)
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Review $review) => [
                    ...$review->only(['id', 'rating', 'title', 'body', 'management_response']),
                    'status' => $review->status->value,
                    'guest' => $review->guest->full_name,
                    'byline' => $review->displayName(),
                    'reservation' => $review->reservation->reference,
                    'stay' => $review->reservation->check_in_date->toDateString().' – '.$review->reservation->check_out_date->toDateString(),
                    'moderator' => $review->moderator?->name,
                    'created_at' => $review->created_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $status],
            'statuses' => ReviewStatus::options(),
            'counts' => Review::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function moderateReview(WebsiteManagementRequest $request, Review $review, ReviewService $reviews): RedirectResponse
    {
        $reviews->moderate($review, ReviewStatus::from($request->string('status')->toString()), $request->user(), $request->input('management_response'));

        return $this->saved(__('Review updated.'));
    }

    public function enquiries(Request $request): Response
    {
        Gate::authorize(Permission::WebsiteManage->value);

        $filters = $request->validate(['status' => ['nullable', Rule::enum(EnquiryStatus::class)]]);

        return Inertia::render('admin/website/enquiries', [
            'enquiries' => Enquiry::query()
                ->with('handler:id,name')
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Enquiry $enquiry) => [
                    ...$enquiry->only(['id', 'name', 'email', 'phone', 'subject', 'message', 'notes']),
                    'status' => $enquiry->status->value,
                    'status_label' => $enquiry->status->label(),
                    'handler' => $enquiry->handler?->name,
                    'created_at' => $enquiry->created_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $filters['status'] ?? ''],
            'statuses' => EnquiryStatus::options(),
        ]);
    }

    public function updateEnquiry(WebsiteManagementRequest $request, Enquiry $enquiry): RedirectResponse
    {
        $enquiry->forceFill([
            'status' => EnquiryStatus::from($request->string('status')->toString()),
            'notes' => $request->input('notes'),
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        return $this->saved(__('Enquiry updated.'));
    }

    public function settings(): Response
    {
        Gate::authorize(Permission::WebsiteManage->value);

        return Inertia::render('admin/website/settings', [
            'settings' => [
                'hotel' => collect($this->settings->group('hotel'))->only(['name', 'tagline', 'email', 'phone', 'address']),
                'website' => collect($this->settings->group('website'))->except(['hero_image_path', 'about_image_path']),
                'social' => $this->settings->group('social'),
                'seo' => $this->settings->group('seo'),
            ],
            'heroImageUrl' => $this->publicUrl($this->settings->get('website.hero_image_path')),
            'aboutImageUrl' => $this->publicUrl($this->settings->get('website.about_image_path')),
            'featuredPhotos' => GalleryImage::query()->where('is_published', true)->where('is_featured', true)->count(),
        ]);
    }

    public function updateSettings(WebsiteManagementRequest $request): RedirectResponse
    {
        $validated = $request->safe()->except(['hero_image', 'about_image']);
        $uploads = [];

        foreach (['hero_image' => 'hero_image_path', 'about_image' => 'about_image_path'] as $field => $setting) {
            if ($request->hasFile($field)) {
                $uploads[$setting] = ['new' => $this->images->store($request->file($field), 'website'), 'old' => $this->settings->get("website.{$setting}")];
            }
        }

        DB::transaction(function () use ($validated, $uploads): void {
            foreach (['hotel', 'website', 'social', 'seo'] as $group) {
                $values = $validated[$group] ?? [];

                if ($group === 'website') {
                    foreach ($uploads as $setting => $paths) {
                        $values[$setting] = $paths['new'];
                    }
                }

                $this->settings->setMany($group, $values);
            }
        });

        foreach ($uploads as $paths) {
            $this->images->delete(is_string($paths['old']) ? $paths['old'] : null);
        }

        $this->audit->record('website.settings_updated', newValues: ['groups' => ['hotel', 'website', 'social', 'seo']]);

        return $this->saved(__('Website settings saved.'));
    }

    private function publicUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk(config('hms.uploads.public_disk'))->url($path) : null;
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
