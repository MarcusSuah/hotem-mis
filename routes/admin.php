<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Identity\AuditLogController;
use App\Http\Controllers\Admin\Identity\BulkUserDeletionController;
use App\Http\Controllers\Admin\Identity\PermissionController;
use App\Http\Controllers\Admin\Identity\RoleController;
use App\Http\Controllers\Admin\Identity\UserController;
use App\Http\Controllers\Admin\Identity\UserStatusController;
use App\Http\Controllers\Admin\Reservations\AvailabilityCalendarController;
use App\Http\Controllers\Admin\Reservations\ReservationController;
use App\Http\Controllers\Admin\Reservations\ReservationRoomController;
use App\Http\Controllers\Admin\Reservations\ReservationStatusController;
use App\Http\Controllers\Admin\Rooms\AmenityController;
use App\Http\Controllers\Admin\Rooms\CancellationPolicyController;
use App\Http\Controllers\Admin\Rooms\RatePlanController;
use App\Http\Controllers\Admin\Rooms\RateSetupController;
use App\Http\Controllers\Admin\Rooms\RoomBlockController;
use App\Http\Controllers\Admin\Rooms\RoomController;
use App\Http\Controllers\Admin\Rooms\RoomTypeController;
use App\Http\Controllers\Admin\Rooms\SeasonalRateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration Routes
|--------------------------------------------------------------------------
|
| Loaded with the "web", "auth", "verified" and "staff" middleware, the
| "/admin" prefix and the "admin." route name prefix. Fine-grained access
| is enforced by policies and permission middleware.
|
*/

Route::get('/', DashboardController::class)->name('dashboard');

Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
Route::post('users/bulk-delete', BulkUserDeletionController::class)->name('users.bulk-delete');
Route::post('users/{user}/activation', [UserStatusController::class, 'store'])->name('users.activate');
Route::delete('users/{user}/activation', [UserStatusController::class, 'destroy'])->name('users.deactivate');

Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);
Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');

Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

// Rooms & rates
Route::resource('room-types', RoomTypeController::class)->only(['index', 'store', 'update', 'destroy']);
Route::resource('rooms', RoomController::class)->only(['index', 'store', 'update', 'destroy']);
Route::post('rooms/{room}/blocks', [RoomBlockController::class, 'store'])->name('rooms.blocks.store');
Route::delete('room-blocks/{room_block}', [RoomBlockController::class, 'destroy'])->name('room-blocks.destroy');
Route::get('rates', RateSetupController::class)->name('rates.index');
Route::resource('rate-plans', RatePlanController::class)->only(['store', 'update', 'destroy']);
Route::resource('seasonal-rates', SeasonalRateController::class)->only(['store', 'update', 'destroy']);
Route::resource('cancellation-policies', CancellationPolicyController::class)->only(['store', 'update', 'destroy']);
Route::resource('amenities', AmenityController::class)->only(['store', 'update', 'destroy']);

// Reservations
Route::get('availability', AvailabilityCalendarController::class)->name('availability.index');
Route::resource('reservations', ReservationController::class)->only(['index', 'create', 'store', 'show', 'update']);
Route::post('reservations/{reservation}/confirmation', [ReservationStatusController::class, 'confirm'])->name('reservations.confirm');
Route::post('reservations/{reservation}/cancellation', [ReservationStatusController::class, 'cancel'])->name('reservations.cancel');
Route::post('reservations/{reservation}/no-show', [ReservationStatusController::class, 'noShow'])->name('reservations.no-show');
Route::put('reservations/{reservation}/room', [ReservationRoomController::class, 'update'])->name('reservations.room.update');
