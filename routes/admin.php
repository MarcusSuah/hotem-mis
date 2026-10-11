<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Identity\AuditLogController;
use App\Http\Controllers\Admin\Identity\BulkUserDeletionController;
use App\Http\Controllers\Admin\Identity\PermissionController;
use App\Http\Controllers\Admin\Identity\RoleController;
use App\Http\Controllers\Admin\Identity\UserController;
use App\Http\Controllers\Admin\Identity\UserStatusController;
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
