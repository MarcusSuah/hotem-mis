<?php

namespace App\Http\Controllers\Admin\Rooms;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rooms\SaveAmenityRequest;
use App\Models\Amenity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AmenityController extends Controller
{
    public function store(SaveAmenityRequest $request): RedirectResponse
    {
        Amenity::create($request->amenityAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Amenity created.')]);

        return back();
    }

    public function update(SaveAmenityRequest $request, Amenity $amenity): RedirectResponse
    {
        $amenity->update($request->amenityAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Amenity updated.')]);

        return back();
    }

    public function destroy(Amenity $amenity): RedirectResponse
    {
        Gate::authorize(Permission::RoomTypesManage->value);

        $amenity->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Amenity deleted.')]);

        return back();
    }
}
