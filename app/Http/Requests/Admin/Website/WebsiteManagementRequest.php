<?php

namespace App\Http\Requests\Admin\Website;

use App\Enums\EnquiryStatus;
use App\Enums\GalleryCategory;
use App\Enums\Permission;
use App\Enums\ReviewStatus;
use App\Services\Media\ImageStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Gallery photos, review moderation, enquiry handling and website settings.
 */
class WebsiteManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = str_contains((string) $this->route()?->getName(), '.reviews.')
            ? Permission::ReviewsModerate
            : Permission::WebsiteManage;

        return $this->user()->can($permission->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.website.gallery.store' => [
                'photos' => ['required', 'array', 'min:1', 'max:20'],
                'photos.*' => ImageStorage::rules(),
                'category' => ['required', Rule::enum(GalleryCategory::class)],
                'alt_text' => ['required', 'string', 'max:200'],
            ],
            'admin.website.gallery.update' => [
                'alt_text' => ['required', 'string', 'max:200'],
                'caption' => ['nullable', 'string', 'max:255'],
                'category' => ['required', Rule::enum(GalleryCategory::class)],
                'display_order' => ['required', 'integer', 'min:0', 'max:999'],
                'is_published' => ['boolean'],
                'is_featured' => ['boolean'],
            ],
            'admin.website.reviews.update' => [
                'status' => ['required', Rule::enum(ReviewStatus::class)],
                'management_response' => ['nullable', 'string', 'max:2000'],
            ],
            'admin.website.enquiries.update' => [
                'status' => ['required', Rule::enum(EnquiryStatus::class)],
                'notes' => ['nullable', 'string', 'max:2000'],
            ],
            default => [
                'hotel.name' => ['required', 'string', 'max:120'],
                'hotel.tagline' => ['nullable', 'string', 'max:200'],
                'hotel.email' => ['required', 'email', 'max:255'],
                'hotel.phone' => ['required', 'string', 'max:40'],
                'hotel.address' => ['required', 'string', 'max:255'],
                'website.hero_eyebrow' => ['nullable', 'string', 'max:80'],
                'website.hero_title' => ['required', 'string', 'max:120'],
                'website.hero_subtitle' => ['nullable', 'string', 'max:300'],
                'website.about_title' => ['nullable', 'string', 'max:120'],
                'website.about_body' => ['nullable', 'string', 'max:5000'],
                'website.booking_note' => ['nullable', 'string', 'max:300'],
                'social.facebook' => ['nullable', 'url:https', 'max:255'],
                'social.instagram' => ['nullable', 'url:https', 'max:255'],
                'social.x' => ['nullable', 'url:https', 'max:255'],
                'social.tripadvisor' => ['nullable', 'url:https', 'max:255'],
                'seo.meta_title' => ['nullable', 'string', 'max:70'],
                'seo.meta_description' => ['nullable', 'string', 'max:300'],
                'hero_image' => ['nullable', ...ImageStorage::rules()],
                'about_image' => ['nullable', ...ImageStorage::rules()],
                'website.about_intro' => ['nullable', 'string', 'max:300'],
            ],
        };
    }
}
