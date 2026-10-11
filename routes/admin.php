<?php

use App\Http\Controllers\Admin\Billing\ExchangeRateController;
use App\Http\Controllers\Admin\Billing\FolioController;
use App\Http\Controllers\Admin\Billing\InvoiceController;
use App\Http\Controllers\Admin\Billing\PaymentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FrontDesk\FrontDeskController;
use App\Http\Controllers\Admin\FrontDesk\NightAuditController;
use App\Http\Controllers\Admin\FrontDesk\StayController;
use App\Http\Controllers\Admin\Guests\GuestController;
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

// Front desk
Route::get('front-desk', FrontDeskController::class)->name('front-desk.index');
Route::get('front-desk/{reservation}/check-in', [StayController::class, 'showCheckIn'])->name('front-desk.check-in');
Route::post('front-desk/{reservation}/check-in', [StayController::class, 'checkIn'])->name('front-desk.check-in.store');
Route::get('front-desk/{reservation}/check-out', [StayController::class, 'showCheckOut'])->name('front-desk.check-out');
Route::post('front-desk/{reservation}/check-out', [StayController::class, 'checkOut'])->name('front-desk.check-out.store');
Route::post('front-desk/{reservation}/extension', [StayController::class, 'extend'])->name('front-desk.extend');
Route::get('front-desk/{reservation}/registration-card', [StayController::class, 'registrationCard'])->name('front-desk.registration-card');
Route::get('night-audit', [NightAuditController::class, 'index'])->name('night-audit.index');
Route::post('night-audit', [NightAuditController::class, 'store'])->name('night-audit.store');

// Guests
Route::resource('guests', GuestController::class)->only(['index', 'show', 'store', 'update']);

// Billing
Route::get('folios/{folio}', [FolioController::class, 'show'])->name('folios.show');
Route::post('folios/{folio}/charges', [FolioController::class, 'storeCharge'])->name('folios.charges.store');
Route::post('folio-charges/{charge}/reversal', [FolioController::class, 'reverseCharge'])->name('folio-charges.reverse');
Route::post('folios/{folio}/payments', [PaymentController::class, 'store'])->name('folios.payments.store');
Route::post('folios/{folio}/invoices', [InvoiceController::class, 'store'])->name('folios.invoices.store');
Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
Route::post('payments/{payment}/refunds', [PaymentController::class, 'refund'])->name('payments.refunds.store');
Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
Route::get('exchange-rates', [ExchangeRateController::class, 'index'])->name('exchange-rates.index');
Route::post('exchange-rates', [ExchangeRateController::class, 'store'])->name('exchange-rates.store');
