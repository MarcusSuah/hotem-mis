<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The header search: guests, bookings and rooms, each only for staff who
 * may see them. Returns at most a handful of matches per group.
 */
class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:60']])['q']);
        $user = $request->user();
        $like = '%'.addcslashes($term, '%_\\').'%';
        $results = [];

        if ($user->can(Permission::BookingsView->value)) {
            $results['reservations'] = Reservation::query()
                ->where(fn ($query) => $query->where('reference', 'like', $like)->orWhere('contact_name', 'like', $like))
                ->orderByDesc('check_in_date')
                ->limit(5)
                ->get(['id', 'reference', 'contact_name', 'status', 'check_in_date', 'check_out_date'])
                ->map(fn (Reservation $reservation) => [
                    'title' => $reservation->contact_name,
                    'badge' => $reservation->reference,
                    'meta' => $reservation->status->label().' · '.$reservation->check_in_date->format('j M').' – '.$reservation->check_out_date->format('j M'),
                    'url' => route('admin.reservations.show', $reservation, false),
                ]);
        }

        if ($user->can(Permission::GuestsView->value)) {
            $results['guests'] = Guest::query()
                ->search($term)
                ->orderBy('last_name')
                ->limit(5)
                ->get(['id', 'first_name', 'last_name', 'email', 'is_vip'])
                ->map(fn (Guest $guest) => [
                    'title' => $guest->full_name,
                    'badge' => $guest->is_vip ? 'VIP' : null,
                    'meta' => $guest->email,
                    'url' => route('admin.guests.show', $guest, false),
                ]);
        }

        if ($user->can(Permission::RoomsView->value)) {
            $results['rooms'] = Room::query()
                ->with('roomType:id,name')
                ->where('number', 'like', addcslashes($term, '%_\\').'%')
                ->orderBy('number')
                ->limit(5)
                ->get()
                ->map(fn (Room $room) => [
                    'title' => "Room {$room->number}",
                    'badge' => null,
                    'meta' => $room->roomType->name.' · '.$room->housekeeping_status->label(),
                    'url' => route('admin.rooms.index', ['search' => $room->number], false),
                ]);
        }

        return response()->json($results);
    }
}
