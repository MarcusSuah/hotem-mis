<?php

namespace App\Services\FrontDesk;

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingTaskStatus;
use App\Enums\Permission;
use App\Enums\ReservationStatus;
use App\Models\HousekeepingTask;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\Reports\AnalyticsService;
use App\Services\Reports\ReportPeriod;
use Carbon\CarbonImmutable;

/**
 * The small set of live figures shown around every back-office page: the
 * business date, tonight's occupancy and the navigation badges.
 */
class BackOfficeSummary
{
    public function __construct(
        private NightAuditService $audits,
        private AnalyticsService $analytics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $today = CarbonImmutable::today();
        $tonight = $this->analytics->summary(new ReportPeriod($today, $today))['current'];

        $tasksToday = HousekeepingTask::query()->whereDate('scheduled_for', $today);
        $totalTasks = (clone $tasksToday)->count();
        $doneTasks = (clone $tasksToday)->whereIn('status', [HousekeepingTaskStatus::Completed->value, HousekeepingTaskStatus::Inspected->value])->count();

        return [
            'business_date' => $this->audits->businessDate()->toDateString(),
            'rooms' => Room::query()->count(),
            'tonight' => [
                'sold' => $tonight['sold'],
                'available' => $tonight['available'],
                'occupancy' => $tonight['occupancy'],
            ],
            'badges' => [
                'reservations' => $user->can(Permission::BookingsView->value)
                    ? Reservation::query()->where('status', ReservationStatus::Pending->value)->count()
                    : null,
                'dirty_rooms' => Room::query()->where('housekeeping_status', HousekeepingStatus::Dirty->value)->count(),
                'housekeeping' => $totalTasks > 0 ? (int) round($doneTasks / $totalTasks * 100) : null,
                'arrivals' => Reservation::query()->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])->whereDate('check_in_date', $today)->count(),
            ],
            'role' => $user->getRoleNames()->first(),
        ];
    }
}
