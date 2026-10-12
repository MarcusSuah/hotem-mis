<?php

namespace App\Http\Requests\Admin\Website;

use App\Enums\Permission;
use App\Services\Media\ImageStorage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Pages, blog posts, events and offers. The route name decides which
 * fields apply; bodies are Markdown and rendered with raw HTML stripped.
 */
class WebsiteContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::WebsiteManage->value);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => str((string) $this->input('slug'))->slug()->toString()]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $common = [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique($this->table(), 'slug')->ignore($this->routeModelId())],
            'body' => [$this->kind() === 'page' || $this->kind() === 'post' ? 'required' : 'nullable', 'string', 'max:60000'],
        ];
        $image = ['nullable', ...ImageStorage::rules()];
        $seo = [
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:300'],
        ];

        return match ($this->kind()) {
            'page' => [
                ...$common,
                ...$seo,
                'summary' => ['nullable', 'string', 'max:500'],
                'is_published' => ['boolean'],
                'show_in_footer' => ['boolean'],
                'display_order' => ['required', 'integer', 'min:0', 'max:999'],
            ],
            'post' => [
                ...$common,
                ...$seo,
                'excerpt' => ['nullable', 'string', 'max:500'],
                'published_at' => ['nullable', 'date'],
                'image' => $image,
                'cover_image_alt' => ['nullable', 'required_with:image', 'string', 'max:200'],
            ],
            'event' => [
                ...$common,
                'summary' => ['nullable', 'string', 'max:500'],
                'venue' => ['nullable', 'string', 'max:150'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after:starts_at'],
                'is_published' => ['boolean'],
                'image' => $image,
                'image_alt' => ['nullable', 'required_with:image', 'string', 'max:200'],
            ],
            default => [
                ...$common,
                'summary' => ['nullable', 'string', 'max:500'],
                'rate_plan_id' => ['nullable', 'integer', Rule::exists('rate_plans', 'id')->where('is_public', true)],
                'room_type_id' => ['nullable', 'integer', Rule::exists('room_types', 'id')->whereNull('deleted_at')],
                'valid_from' => ['nullable', 'date'],
                'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
                'is_published' => ['boolean'],
                'display_order' => ['required', 'integer', 'min:0', 'max:999'],
                'image' => $image,
                'image_alt' => ['nullable', 'required_with:image', 'string', 'max:200'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate_plan_id.exists' => 'Choose a public rate plan so the website can quote it.',
            'cover_image_alt.required_with' => 'Describe the image for visitors using screen readers.',
            'image_alt.required_with' => 'Describe the image for visitors using screen readers.',
        ];
    }

    /**
     * Validated attributes ready to fill the model (images handled separately).
     *
     * @return array<string, mixed>
     */
    public function contentAttributes(): array
    {
        $data = $this->safe()->except(['image']);

        foreach (['is_published', 'show_in_footer'] as $flag) {
            if (array_key_exists($flag, $this->rules())) {
                $data[$flag] = $this->boolean($flag);
            }
        }

        if (blank($data['slug'] ?? null)) {
            unset($data['slug']);
        }

        return $data;
    }

    public function kind(): string
    {
        $name = (string) $this->route()?->getName();

        return match (true) {
            str_contains($name, '.pages.') => 'page',
            str_contains($name, '.posts.') => 'post',
            str_contains($name, '.events.') => 'event',
            default => 'offer',
        };
    }

    private function table(): string
    {
        return match ($this->kind()) {
            'page' => 'pages',
            'post' => 'posts',
            'event' => 'hotel_events',
            default => 'offers',
        };
    }

    private function routeModelId(): ?int
    {
        $model = $this->route('page') ?? $this->route('post') ?? $this->route('event') ?? $this->route('offer');

        return is_object($model) && method_exists($model, 'getKey') ? (int) $model->getKey() : null;
    }
}
