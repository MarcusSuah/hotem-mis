<?php

namespace App\Http\Requests\Admin\Outlets;

use App\Enums\Permission;
use App\Enums\ServiceCategory;
use App\Services\Media\ImageStorage;
use App\Support\Money\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Menu categories, menu items and additional services share one request;
 * the route decides which rules apply.
 */
class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ServicesManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $price = ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];
        $name = (string) $this->route()?->getName();

        return match (true) {
            str_contains($name, 'menu-categories') => [
                'name' => ['required', 'string', 'max:100'],
                'display_order' => ['required', 'integer', 'min:0', 'max:999'],
                'is_active' => ['boolean'],
            ],
            str_contains($name, 'menu-items') => [
                'menu_category_id' => ['required', 'integer', Rule::exists('menu_categories', 'id')],
                'name' => ['required', 'string', 'max:150'],
                'description' => ['nullable', 'string', 'max:1000'],
                'price' => $price,
                'is_available' => ['boolean'],
                'is_featured' => ['boolean'],
                'display_order' => ['required', 'integer', 'min:0', 'max:999'],
                'image' => ['nullable', ...ImageStorage::rules()],
            ],
            default => [
                'name' => ['required', 'string', 'max:150'],
                'category' => ['required', Rule::enum(ServiceCategory::class)],
                'short_description' => ['nullable', 'string', 'max:500'],
                'description' => ['nullable', 'string', 'max:5000'],
                'price' => $price,
                'unit' => ['required', 'string', 'max:32'],
                'is_active' => ['boolean'],
                'is_featured' => ['boolean'],
                'display_order' => ['required', 'integer', 'min:0', 'max:999'],
                'image' => ['nullable', ...ImageStorage::rules()],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['price.regex' => 'Enter a price with up to two decimal places.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogAttributes(string $currency): array
    {
        $attributes = $this->safe()->except(['price', 'image']);

        foreach (['is_active', 'is_available', 'is_featured'] as $flag) {
            if (array_key_exists($flag, $this->rules())) {
                $attributes[$flag] = $this->boolean($flag);
            }
        }

        if ($this->filled('price')) {
            $attributes['price_minor'] = Money::of($this->string('price')->toString(), $currency)->minor;
            $attributes['currency'] = $currency;
        }

        return $attributes;
    }
}
