<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\RoomType;
use App\Services\Currency\DisplayCurrencyResolver;
use App\Services\Website\SitePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function __construct(
        private SitePresenter $present,
        private DisplayCurrencyResolver $currencies,
    ) {}

    public function index(Request $request): Response
    {
        $currency = $this->currencies->resolve($request);

        return Inertia::render('site/rooms/index', [
            'rooms' => RoomType::query()->active()->ordered()->with('images')->get()
                ->map(fn (RoomType $type) => $this->present->roomTypeCard($type, $currency)),
        ]);
    }

    public function show(Request $request, RoomType $roomType): Response
    {
        abort_unless($roomType->is_active, 404);

        $roomType->load(['images', 'amenities' => fn ($query) => $query->where('is_active', true)->orderBy('display_order')]);
        $currency = $this->currencies->resolve($request);

        return Inertia::render('site/rooms/show', [
            'room' => $this->present->roomTypeDetail($roomType, $currency),
            'others' => RoomType::query()->active()->ordered()->whereKeyNot($roomType->id)->with('images')->limit(3)->get()
                ->map(fn (RoomType $type) => $this->present->roomTypeCard($type, $currency)),
            'reviews' => Review::query()->approved()
                ->whereHas('reservation.allocations', fn ($query) => $query->where('room_type_id', $roomType->id))
                ->with(['guest:id,first_name,last_name', 'reservation:id,check_in_date'])
                ->latest()->limit(3)->get()
                ->map(fn (Review $review) => $this->present->review($review)),
        ]);
    }
}
