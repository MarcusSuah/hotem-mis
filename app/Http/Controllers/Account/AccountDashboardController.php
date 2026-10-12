<?php

namespace App\Http\Controllers\Account;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\Guests\LoyaltyService;
use App\Services\Settings\SettingsRepository;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountDashboardController extends Controller
{
    public function __invoke(Request $request, LoyaltyService $loyalty, SettingsRepository $settings): Response
    {
        $user = $request->user()->loadMissing('guest');
        $guest = $user->guest;

        $upcoming = $guest === null ? null : Reservation::query()
            ->whereBelongsTo($guest)
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value, ReservationStatus::CheckedIn->value])
            ->with('activeAllocations.roomType:id,name')
            ->orderBy('check_in_date')
            ->first();

        return Inertia::render('account/dashboard', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'member_since' => $user->created_at?->toDateString(),
            ],
            'upcoming' => $upcoming ? [
                'reference' => $upcoming->reference,
                'status' => $upcoming->status->value,
                'status_label' => $upcoming->status->label(),
                'check_in_date' => $upcoming->check_in_date->toDateString(),
                'check_out_date' => $upcoming->check_out_date->toDateString(),
                'nights' => $upcoming->nights,
                'room_type' => $upcoming->activeAllocations->first()?->roomType?->name,
                'total' => Money::ofMinor($upcoming->total_minor, $upcoming->currency),
            ] : null,
            'stats' => [
                'stays' => $guest ? Reservation::query()->whereBelongsTo($guest)->where('status', ReservationStatus::CheckedOut->value)->count() : 0,
                'loyalty_points' => $guest && $settings->get('loyalty.enabled', true) ? $loyalty->balance($guest) : null,
            ],
        ]);
    }
}
