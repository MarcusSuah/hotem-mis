<?php

namespace App\Console\Commands;

use App\Services\Operations\HousekeepingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hms:generate-housekeeping-tasks')]
#[Description('Create today\'s stayover service tasks for occupied rooms')]
class GenerateHousekeepingTasks extends Command
{
    public function handle(HousekeepingService $housekeeping): int
    {
        $created = $housekeeping->generateStayoverTasks(CarbonImmutable::today());

        $this->components->info("Created {$created} stayover task(s).");

        return self::SUCCESS;
    }
}
