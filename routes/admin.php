<?php

use App\Http\Controllers\Admin\Billing\ExchangeRateController;
use App\Http\Controllers\Admin\Billing\FolioController;
use App\Http\Controllers\Admin\Billing\GatewayEventController;
use App\Http\Controllers\Admin\Billing\InvoiceController;
use App\Http\Controllers\Admin\Billing\PaymentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FrontDesk\FrontDeskController;
use App\Http\Controllers\Admin\FrontDesk\NightAuditController;
use App\Http\Controllers\Admin\FrontDesk\StayController;
use App\Http\Controllers\Admin\Guests\GuestCareController;
use App\Http\Controllers\Admin\Guests\GuestController;
use App\Http\Controllers\Admin\Identity\AuditLogController;
use App\Http\Controllers\Admin\Identity\BulkUserDeletionController;
use App\Http\Controllers\Admin\Identity\PermissionController;
use App\Http\Controllers\Admin\Identity\RoleController;
use App\Http\Controllers\Admin\Identity\UserController;
use App\Http\Controllers\Admin\Identity\UserStatusController;
use App\Http\Controllers\Admin\Inventory\InventoryController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\Operations\HousekeepingController;
use App\Http\Controllers\Admin\Operations\MaintenanceController;
use App\Http\Controllers\Admin\Outlets\CatalogController;
use App\Http\Controllers\Admin\Outlets\OutletController;
use App\Http\Controllers\Admin\Reports\ReportController;
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
use App\Http\Controllers\Admin\Staff\StaffController;
use App\Http\Controllers\Admin\Website\ContentController;
use App\Http\Controllers\Admin\Website\WebsiteManagementController;
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

// Notifications
Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

// Housekeeping
Route::get('housekeeping', [HousekeepingController::class, 'index'])->name('housekeeping.index');
Route::post('housekeeping/tasks', [HousekeepingController::class, 'store'])->name('housekeeping.tasks.store');
Route::put('housekeeping/tasks/{task}/assignment', [HousekeepingController::class, 'assign'])->name('housekeeping.tasks.assign');
Route::post('housekeeping/tasks/{task}/start', [HousekeepingController::class, 'start'])->name('housekeeping.tasks.start');
Route::post('housekeeping/tasks/{task}/complete', [HousekeepingController::class, 'complete'])->name('housekeeping.tasks.complete');
Route::post('housekeeping/tasks/{task}/inspection', [HousekeepingController::class, 'inspect'])->name('housekeeping.tasks.inspect');
Route::post('housekeeping/tasks/{task}/issue', [HousekeepingController::class, 'escalate'])->name('housekeeping.tasks.escalate');
Route::post('housekeeping/lost-found', [HousekeepingController::class, 'storeLostFound'])->name('housekeeping.lost-found.store');
Route::put('housekeeping/lost-found/{item}', [HousekeepingController::class, 'updateLostFound'])->name('housekeeping.lost-found.update');

// Maintenance
Route::get('maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
Route::post('maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
Route::post('maintenance-schedules', [MaintenanceController::class, 'storeSchedule'])->name('maintenance.schedules.store');
Route::put('maintenance-schedules/{schedule}', [MaintenanceController::class, 'updateSchedule'])->name('maintenance.schedules.update');
Route::get('maintenance/{maintenance}', [MaintenanceController::class, 'show'])->name('maintenance.show');
Route::put('maintenance/{maintenance}/assignment', [MaintenanceController::class, 'assign'])->name('maintenance.assign');
Route::post('maintenance/{maintenance}/status', [MaintenanceController::class, 'status'])->name('maintenance.status');
Route::post('maintenance/{maintenance}/comments', [MaintenanceController::class, 'comment'])->name('maintenance.comments');
Route::post('maintenance/{maintenance}/parts', [MaintenanceController::class, 'part'])->name('maintenance.parts');
Route::post('maintenance/{maintenance}/attachments', [MaintenanceController::class, 'attachments'])->name('maintenance.attachments');
Route::get('maintenance/{maintenance}/attachments/{attachment}', [MaintenanceController::class, 'attachment'])->name('maintenance.attachments.show');

// Inventory & purchasing
Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
Route::post('inventory/items', [InventoryController::class, 'storeItem'])->name('inventory.items.store');
Route::put('inventory/items/{item}', [InventoryController::class, 'updateItem'])->name('inventory.items.update');
Route::get('inventory/items/{item}', [InventoryController::class, 'history'])->name('inventory.items.show');
Route::post('inventory/items/{item}/movements', [InventoryController::class, 'movement'])->name('inventory.items.movements');
Route::post('suppliers', [InventoryController::class, 'storeSupplier'])->name('suppliers.store');
Route::put('suppliers/{supplier}', [InventoryController::class, 'updateSupplier'])->name('suppliers.update');
Route::post('purchase-orders', [InventoryController::class, 'storeOrder'])->name('purchase-orders.store');
Route::get('purchase-orders/{order}', [InventoryController::class, 'showOrder'])->name('purchase-orders.show');
Route::post('purchase-orders/{order}/ordered', [InventoryController::class, 'markOrdered'])->name('purchase-orders.ordered');
Route::post('purchase-orders/{order}/receipts', [InventoryController::class, 'receive'])->name('purchase-orders.receive');
Route::post('purchase-orders/{order}/cancellation', [InventoryController::class, 'cancelOrder'])->name('purchase-orders.cancel');

// Restaurant, room service & guest services
Route::get('outlets', [OutletController::class, 'index'])->name('outlets.index');
Route::post('outlets/orders', [OutletController::class, 'store'])->name('outlets.orders.store');
Route::post('outlets/orders/{order}/progress', [OutletController::class, 'advance'])->name('outlets.orders.advance');
Route::post('outlets/orders/{order}/settlement', [OutletController::class, 'settle'])->name('outlets.orders.settle');
Route::post('outlets/orders/{order}/cancellation', [OutletController::class, 'cancel'])->name('outlets.orders.cancel');
Route::post('outlets/services', [OutletController::class, 'storeService'])->name('outlets.services.store');
Route::post('outlets/services/{serviceOrder}/completion', [OutletController::class, 'completeService'])->name('outlets.services.complete');
Route::post('outlets/services/{serviceOrder}/cancellation', [OutletController::class, 'cancelService'])->name('outlets.services.cancel');
Route::get('catalog', [CatalogController::class, 'index'])->name('catalog.index');
Route::post('catalog/menu-categories', [CatalogController::class, 'storeCategory'])->name('catalog.menu-categories.store');
Route::put('catalog/menu-categories/{category}', [CatalogController::class, 'updateCategory'])->name('catalog.menu-categories.update');
Route::post('catalog/menu-items', [CatalogController::class, 'storeItem'])->name('catalog.menu-items.store');
Route::put('catalog/menu-items/{item}', [CatalogController::class, 'updateItem'])->name('catalog.menu-items.update');
Route::delete('catalog/menu-items/{item}', [CatalogController::class, 'destroyItem'])->name('catalog.menu-items.destroy');
Route::post('catalog/services', [CatalogController::class, 'storeService'])->name('catalog.services.store');
Route::put('catalog/services/{service}', [CatalogController::class, 'updateService'])->name('catalog.services.update');
Route::delete('catalog/services/{service}', [CatalogController::class, 'destroyService'])->name('catalog.services.destroy');

// Staff
Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
Route::put('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
Route::post('staff-departments', [StaffController::class, 'storeDepartment'])->name('staff.departments.store');
Route::post('staff-shifts', [StaffController::class, 'storeShift'])->name('staff.shifts.store');
Route::delete('staff-shifts/{shift}', [StaffController::class, 'destroyShift'])->name('staff.shifts.destroy');
Route::post('staff-attendance', [StaffController::class, 'storeAttendance'])->name('staff.attendance.store');
Route::post('staff-leave', [StaffController::class, 'storeLeave'])->name('staff.leave.store');
Route::post('staff-leave/{leave}/decision', [StaffController::class, 'decideLeave'])->name('staff.leave.decide');

// Guest care
Route::get('complaints', [GuestCareController::class, 'complaints'])->name('complaints.index');
Route::post('complaints', [GuestCareController::class, 'storeComplaint'])->name('complaints.store');
Route::put('complaints/{complaint}', [GuestCareController::class, 'updateComplaint'])->name('complaints.update');
Route::post('guests/{guest}/preferences', [GuestCareController::class, 'storePreference'])->name('guests.preferences.store');
Route::delete('guests/{guest}/preferences/{preference}', [GuestCareController::class, 'destroyPreference'])->name('guests.preferences.destroy');
Route::post('guests/{guest}/loyalty', [GuestCareController::class, 'adjustLoyalty'])->name('guests.loyalty.store');

// Website & CMS
Route::prefix('website')->name('website.')->group(function () {
    Route::get('content', [ContentController::class, 'index'])->name('content.index');
    Route::post('pages', [ContentController::class, 'storePage'])->name('pages.store');
    Route::put('pages/{page}', [ContentController::class, 'updatePage'])->name('pages.update');
    Route::delete('pages/{page}', [ContentController::class, 'destroyPage'])->name('pages.destroy');
    Route::post('posts', [ContentController::class, 'storePost'])->name('posts.store');
    Route::put('posts/{post}', [ContentController::class, 'updatePost'])->name('posts.update');
    Route::delete('posts/{post}', [ContentController::class, 'destroyPost'])->name('posts.destroy');
    Route::post('events', [ContentController::class, 'storeEvent'])->name('events.store');
    Route::put('events/{event}', [ContentController::class, 'updateEvent'])->name('events.update');
    Route::delete('events/{event}', [ContentController::class, 'destroyEvent'])->name('events.destroy');
    Route::post('offers', [ContentController::class, 'storeOffer'])->name('offers.store');
    Route::put('offers/{offer}', [ContentController::class, 'updateOffer'])->name('offers.update');
    Route::delete('offers/{offer}', [ContentController::class, 'destroyOffer'])->name('offers.destroy');

    Route::get('gallery', [WebsiteManagementController::class, 'gallery'])->name('gallery.index');
    Route::post('gallery', [WebsiteManagementController::class, 'storeGallery'])->name('gallery.store');
    Route::put('gallery/{image}', [WebsiteManagementController::class, 'updateGallery'])->name('gallery.update');
    Route::delete('gallery/{image}', [WebsiteManagementController::class, 'destroyGallery'])->name('gallery.destroy');
    Route::get('reviews', [WebsiteManagementController::class, 'reviews'])->name('reviews.index');
    Route::put('reviews/{review}', [WebsiteManagementController::class, 'moderateReview'])->name('reviews.update');
    Route::get('enquiries', [WebsiteManagementController::class, 'enquiries'])->name('enquiries.index');
    Route::put('enquiries/{enquiry}', [WebsiteManagementController::class, 'updateEnquiry'])->name('enquiries.update');
    Route::get('settings', [WebsiteManagementController::class, 'settings'])->name('settings.edit');
    Route::put('settings', [WebsiteManagementController::class, 'updateSettings'])->name('settings.update');
});

// Reports & analytics
Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
Route::get('reports/room-usage', [ReportController::class, 'heatmap'])->name('reports.heatmap');

// Online payment notifications
Route::get('payment-gateway-events', [GatewayEventController::class, 'index'])->name('gateway-events.index');
Route::post('payment-gateway-events/{event}/retry', [GatewayEventController::class, 'retry'])->name('gateway-events.retry');
