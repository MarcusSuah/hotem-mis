<?php

namespace App\Http\Controllers\Admin\Rooms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rooms\SaveRoomTypeRequest;
use App\Models\Amenity;
use App\Models\RoomType;
use App\Models\RoomTypeImage;
use App\Services\Currency\CurrencyConverter;
use App\Services\Rooms\RoomTypeService;
use App\Support\Money\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RoomTypeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Permission::RoomsView->value);

        $roomTypes = RoomType::query()
            ->with(['images', 'amenities:id,name'])
            ->withCount('rooms')
            ->ordered()
            ->get()
            ->map(fn (RoomType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
                'short_description' => $type->short_description,
                'description' => $type->description,
                'featured_image_url' => $type->featured_image_url,
                'featured_image_alt' => $type->featured_image_alt,
                'max_adults' => $type->max_adults,
                'max_children' => $type->max_children,
                'max_occupancy' => $type->max_occupancy,
                'included_adults' => $type->included_adults,
                'included_children' => $type->included_children,
                'bed_type' => $type->bed_type,
                'bed_count' => $type->bed_count,
                'size_sqm' => $type->size_sqm,
                'bathroom_details' => $type->bathroom_details,
                'base_rate' => $type->baseRate(),
                'weekend_rate' => $type->weekendRate(),
                'extra_adult_rate' => Money::ofMinor($type->extra_adult_minor, $type->currency),
                'extra_child_rate' => Money::ofMinor($type->extra_child_minor, $type->currency),
                'is_active' => $type->is_active,
                'is_featured' => $type->is_featured,
                'display_order' => $type->display_order,
                'meta_title' => $type->meta_title,
                'meta_description' => $type->meta_description,
                'rooms_count' => $type->rooms_count,
                'amenity_ids' => $type->amenities->modelKeys(),
                'amenities' => $type->amenities->pluck('name'),
                'images' => $type->images->map(fn (RoomTypeImage $image): array => [
                    'id' => $image->id,
                    'url' => $image->url,
                    'alt_text' => $image->alt_text,
                ]),
            ]);

        return Inertia::render('admin/room-types/index', [
            'roomTypes' => $roomTypes,
            'amenities' => Amenity::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get(['id', 'name']),
            'can' => ['manage' => $request->user()->can(Permission::RoomTypesManage->value)],
        ]);
    }

    public function store(SaveRoomTypeRequest $request, RoomTypeService $service, CurrencyConverter $converter): RedirectResponse
    {
        $service->save(
            null,
            $request->roomTypeAttributes($converter),
            array_values(array_map('intval', (array) $request->validated('amenity_ids', []))),
            $request->file('featured_image'),
            $request->galleryFiles(),
            [],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room type created.')]);

        return back();
    }

    public function update(SaveRoomTypeRequest $request, RoomType $roomType, RoomTypeService $service, CurrencyConverter $converter): RedirectResponse
    {
        $service->save(
            $roomType,
            $request->roomTypeAttributes($converter),
            array_values(array_map('intval', (array) $request->validated('amenity_ids', []))),
            $request->file('featured_image'),
            $request->galleryFiles(),
            array_values(array_map('intval', (array) $request->validated('remove_image_ids', []))),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room type updated.')]);

        return back();
    }

    public function destroy(RoomType $roomType, RoomTypeService $service): RedirectResponse
    {
        Gate::authorize(Permission::RoomTypesManage->value);

        $service->delete($roomType);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Room type deleted.')]);

        return back();
    }
}
