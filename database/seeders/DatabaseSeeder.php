<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Baseline seeders are idempotent and safe in every environment. Demo
     * fixtures are never loaded in production.
     */
    public function run(): void
    {
        $this->call([
            FoundationSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        if (app()->isProduction()) {
            $this->command->warn('Production environment: demo data skipped. Run `php artisan hms:provision-super-admin` to create the protected administrator.');

            return;
        }

        $this->call([
            DemoStaffSeeder::class,
            HotelInventorySeeder::class,
            DemoReservationSeeder::class,
        ]);
    }
}
