<?php

namespace App\Http\Requests\Admin\Rooms;

use App\Enums\Permission;
use App\Models\RoomType;
use App\Services\Currency\CurrencyConverter;
use App\Services\Media\ImageStorage;
use App\Support\Money\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRoomTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::RoomTypesManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var RoomType|null $roomType */
        $roomType = $this->route('room_type');
        $money = ['regex:/^\d{1,9}(\.\d{1,2})?$/'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('room_types', 'slug')->ignore($roomType?->id)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'max_adults' => ['required', 'integer', 'min:1', 'max:20'],
            'max_children' => ['required', 'integer', 'min:0', 'max:20'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:30'],
            'included_adults' => ['required', 'integer', 'min:1', 'lte:max_adults'],
            'included_children' => ['required', 'integer', 'min:0', 'lte:max_children'],
            'bed_type' => ['required', 'string', 'max:64'],
            'bed_count' => ['required', 'integer', 'min:1', 'max:10'],
            'size_sqm' => ['nullable', 'numeric', 'min:1', 'max:9999'],
            'bathroom_details' => ['nullable', 'string', 'max:255'],
            'base_rate' => ['required', ...$money],
            'weekend_rate' => ['nullable', ...$money],
            'extra_adult_rate' => ['required', ...$money],
            'extra_child_rate' => ['required', ...$money],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'amenity_ids' => ['array'],
            'amenity_ids.*' => ['integer', 'distinct', Rule::exists('amenities', 'id')],
            'featured_image' => ['nullable', ...ImageStorage::rules()],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'gallery' => ['array', 'max:12'],
            'gallery.*' => ImageStorage::rules(),
            'remove_image_ids' => ['array'],
            'remove_image_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_rate.regex' => 'Enter an amount with up to two decimal places.',
            'weekend_rate.regex' => 'Enter an amount with up to two decimal places.',
            'extra_adult_rate.regex' => 'Enter an amount with up to two decimal places.',
            'extra_child_rate.regex' => 'Enter an amount with up to two decimal places.',
            'included_adults.lte' => 'Included adults cannot exceed the maximum adults.',
            'included_children.lte' => 'Included children cannot exceed the maximum children.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['max_adults', 'max_children', 'max_occupancy'])) {
                    return;
                }

                if ($this->integer('max_occupancy') > $this->integer('max_adults') + $this->integer('max_children')) {
                    $validator->errors()->add('max_occupancy', 'Maximum occupancy cannot exceed maximum adults plus maximum children.');
                }
            },
        ];
    }

    /**
     * Attributes for the room type, with rates converted to base-currency minor units.
     *
     * @return array<string, mixed>
     */
    public function roomTypeAttributes(CurrencyConverter $converter): array
    {
        $currency = $converter->baseCurrency();
        $minor = fn (string $field): ?int => $this->filled($field) ? Money::of($this->string($field)->toString(), $currency)->minor : null;

        return [
            ...$this->safe()->only([
                'name', 'short_description', 'description', 'max_adults', 'max_children', 'max_occupancy',
                'included_adults', 'included_children', 'bed_type', 'bed_count', 'size_sqm', 'bathroom_details',
                'display_order', 'meta_title', 'meta_description', 'featured_image_alt',
            ]),
            'slug' => $this->filled('slug') ? $this->string('slug')->toString() : null,
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'currency' => $currency,
            'base_rate_minor' => $minor('base_rate'),
            'weekend_rate_minor' => $minor('weekend_rate'),
            'extra_adult_minor' => $minor('extra_adult_rate'),
            'extra_child_minor' => $minor('extra_child_rate'),
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function galleryFiles(): array
    {
        return array_values(array_filter((array) $this->file('gallery', []), fn ($file) => $file instanceof UploadedFile));
    }
}
