<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HousekeepingStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\OperationalStatus;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\MaintenanceRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Reports\AnalyticsService;
use App\Services\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Today's operational picture for every staff member, plus 30-day
 * performance charts for those who may see financial reports.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, AnalyticsService $analytics): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::today();
        $canSeeFinance = $user->can(Permission::ReportsView->value);
        $last30 = ReportPeriod::lastDays(30);
        $next30 = new ReportPeriod($today, $today->addDays(29));

        $tonight = $analytics->summary(new ReportPeriod($today, $today))['current'];

        return Inertia::render('admin/dashboard', [
            'today' => [
                'date' => $today->toDateString(),
                'arrivals' => Reservation::query()->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])->whereDate('check_in_date', $today)->count(),
                'departures' => Reservation::query()->where('status', ReservationStatus::CheckedIn->value)->whereDate('check_out_date', $today)->count(),
                'in_house' => Reservation::query()->where('status', ReservationStatus::CheckedIn->value)->count(),
                'occupancy' => $tonight['occupancy'],
                'rooms_sold' => $tonight['sold'],
                'rooms_available' => $tonight['available'],
                'pending_requests' => Reservation::query()->where('status', ReservationStatus::Pending->value)->count(),
                'rooms_to_clean' => Room::query()->where('housekeeping_status', HousekeepingStatus::Dirty->value)->count(),
                'out_of_service' => Room::query()->where('operational_status', OperationalStatus::OutOfService->value)->count(),
                'open_maintenance' => MaintenanceRequest::query()->whereIn('status', MaintenanceStatus::activeValues())->count(),
                'low_stock' => $user->can(Permission::InventoryManage->value)
                    ? InventoryItem::query()->where('is_active', true)->whereColumn('quantity', '<=', 'reorder_level')->count()
                    : null,
            ],
            'finance' => $canSeeFinance ? Inertia::defer(fn () => [
                'currency' => $analytics->baseCurrency()->value,
                'summary' => $analytics->summary($last30),
                'daily' => $analytics->dailyPerformance($last30),
                'revenue' => $analytics->revenueByCategory($last30),
                'sources' => $analytics->bookingsBySource($last30),
                'payments' => $analytics->paymentsByMethod($last30),
                'forecast' => $analytics->forecast($next30),
            ]) : null,
        ]);
    }
}
