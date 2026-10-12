<?php

use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * Every back-office screen must check a permission. A staff account whose
 * role grants nothing may only reach the pages every staff member shares.
 */
it('refuses every permission-protected back-office page to a staff member without permissions', function () {
    $role = Role::create(['name' => 'Trainee', 'guard_name' => 'web']);
    $trainee = User::factory()->create();
    $trainee->assignRole($role);

    $openToAllStaff = ['admin.dashboard', 'admin.notifications.index'];

    $pages = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true)
            && str_starts_with((string) $route->getName(), 'admin.')
            && $route->parameterNames() === [])
        ->map(fn (RoutingRoute $route) => (string) $route->getName())
        ->reject(fn (string $name) => in_array($name, $openToAllStaff, true))
        ->values();

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $name) {
        $status = $this->actingAs($trainee)->get(route($name))->getStatusCode();

        expect($status)->toBe(403, "{$name} answered {$status} for a staff member without permissions");
    }

    foreach ($openToAllStaff as $name) {
        $this->actingAs($trainee)->get(route($name))->assertOk();
    }
});
