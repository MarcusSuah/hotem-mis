<?php

namespace App\Http\Controllers\Admin\Reservations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reservations\ReassignRoomRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Booking\ReservationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReservationRoomController extends Controller
{
    public function update(ReassignRoomRequest $request, Reservation $reservation, ReservationService $reservations): RedirectResponse
    {
        $room = Room::query()->with('roomType')->findOrFail($request->integer('room_id'));

        $reservations->reassignRoom($reservation, $room, $request->user(), $request->filled('reason') ? $request->string('reason')->toString() : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Moved to room :number.', ['number' => $room->number])]);

        return back();
    }
}
