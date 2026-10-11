<?php

use App\Enums\SystemRole;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('staff are sent to the administration dashboard', function () {
    $user = User::factory()->withRole(SystemRole::Receptionist)->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('admin.dashboard'));
});

test('guests are sent to their account dashboard', function () {
    $user = User::factory()->withRole(SystemRole::Guest)->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('account.dashboard'));
});

test('staff can visit the administration dashboard', function () {
    $user = User::factory()->withRole(SystemRole::HotelManager)->create();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
});

test('guest accounts cannot enter the administration area', function () {
    $user = User::factory()->withRole(SystemRole::Guest)->create();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});
