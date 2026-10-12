<?php

namespace App\Console\Commands;

use App\Services\Operations\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hms:generate-preventive-maintenance')]
#[Description('Create maintenance requests for preventive schedules that are due')]
class GeneratePreventiveMaintenance extends Command
{
    public function handle(MaintenanceService $maintenance): int
    {
        $created = $maintenance->generateDueSchedules(CarbonImmutable::today());

        $this->components->info("Created {$created} preventive maintenance request(s).");

        return self::SUCCESS;
    }
}
