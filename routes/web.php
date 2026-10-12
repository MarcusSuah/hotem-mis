<?php

use App\Http\Controllers\CurrencyPreferenceController;
use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\Site\BookingController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\RoomController;
use App\Http\Controllers\Site\SiteContentController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Webhooks\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Website
|--------------------------------------------------------------------------
|
| Everything here is visible to anonymous visitors and shows published
| content only. Booking and contact submissions are rate limited.
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:120,1')
    ->where('gateway', '[a-z0-9-]+')
    ->name('webhooks.payments');
Route::post('currency', CurrencyPreferenceController::class)->middleware('throttle:30,1')->name('currency.update');

Route::name('site.')->group(function () {
    Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('rooms/{roomType}', [RoomController::class, 'show'])->name('rooms.show');

    Route::get('book', [BookingController::class, 'start'])->name('booking.start');
    Route::get('book/availability', [BookingController::class, 'availability'])->name('booking.availability');
    Route::get('book/rooms/{roomType}', [BookingController::class, 'details'])->name('booking.details');
    Route::post('book', [BookingController::class, 'store'])->middleware('throttle:public-booking')->name('booking.store');
    Route::get('book/confirmation/{reservation}', [BookingController::class, 'confirmation'])->middleware('signed')->name('booking.confirmation');

    Route::get('offers', [SiteContentController::class, 'offers'])->name('offers.index');
    Route::get('offers/{offer}', [SiteContentController::class, 'offer'])->name('offers.show');
    Route::get('events', [SiteContentController::class, 'events'])->name('events.index');
    Route::get('events/{event}', [SiteContentController::class, 'event'])->name('events.show');
    Route::get('journal', [SiteContentController::class, 'blog'])->name('blog.index');
    Route::get('journal/{post}', [SiteContentController::class, 'post'])->name('blog.show');
    Route::get('about', [SiteContentController::class, 'about'])->name('about');
    Route::get('gallery', [SiteContentController::class, 'gallery'])->name('gallery');
    Route::get('dining', [SiteContentController::class, 'dining'])->name('dining');
    Route::get('experiences', [SiteContentController::class, 'experiences'])->name('experiences');
    Route::get('reviews', [SiteContentController::class, 'reviews'])->name('reviews');
    Route::get('pages/{page}', [SiteContentController::class, 'page'])->name('pages.show');

    Route::get('contact', [ContactController::class, 'show'])->name('contact');
    Route::post('contact', [ContactController::class, 'store'])->middleware('throttle:public-contact')->name('contact.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', HomeRedirectController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
