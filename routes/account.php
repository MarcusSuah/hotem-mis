<?php

use App\Http\Controllers\Account\AccountDashboardController;
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
});
