<?php

use App\Http\Controllers\Account\AccountDashboardController;
use App\Http\Controllers\Account\AccountReservationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest Account Routes
|--------------------------------------------------------------------------
|
| Authenticated guests manage their own reservations, invoices and profile.
| Every query in these controllers is scoped to the signed-in user.
|
*/

Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', AccountDashboardController::class)->name('dashboard');
    Route::get('reservations', [AccountReservationController::class, 'index'])->name('reservations.index');
    Route::get('reservations/{reservation}', [AccountReservationController::class, 'show'])->name('reservations.show');
    Route::post('reservations/{reservation}/cancellation', [AccountReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::post('reservations/{reservation}/review', [AccountReservationController::class, 'review'])->name('reservations.review');
    Route::get('invoices/{invoice}', [AccountReservationController::class, 'invoice'])->name('invoices.show');
});
