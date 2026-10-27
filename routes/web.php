<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Shop\ArtistShopController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\CopyrightReportController;
use App\Http\Controllers\Shop\FinishedProductController;
use App\Http\Controllers\Shop\HomeController;
use App\Http\Controllers\Shop\LocaleController;
use App\Http\Controllers\Shop\MediaController;
use App\Http\Controllers\Shop\OrderTrackingController;
use App\Http\Controllers\Shop\PageController;
use App\Http\Controllers\Shop\PaymentSimulatorController;
use App\Http\Controllers\Shop\ProductController;
use App\Http\Controllers\Shop\SupportController;
use Illuminate\Support\Facades\Route;

/*
| Public storefront
*/
Route::get('/', HomeController::class)->name('home');
Route::get('/shop', [CatalogController::class, 'index'])->name('shop.index');
Route::get('/categories/{category:slug}', [CatalogController::class, 'category'])->name('shop.category');
Route::get('/collections/{collection:slug}', [CatalogController::class, 'collection'])->name('shop.collection');
Route::get('/designs/{artwork:slug}', [ProductController::class, 'artwork'])->name('designs.show');
Route::get('/designs/{artwork:slug}/{product:slug}', [ProductController::class, 'show'])->withoutScopedBindings()->name('products.show');
Route::get('/ready-made/{finished_product:slug}', [FinishedProductController::class, 'show'])->name('finished.show');
Route::get('/artists', [ArtistShopController::class, 'index'])->name('artists.index');
Route::get('/artists/{artistProfile:slug}', [ArtistShopController::class, 'show'])->name('artists.show');
Route::get('/pages/{page}', PageController::class)->name('pages.show');
Route::post('/locale', LocaleController::class)->name('locale.update');

Route::get('/report-copyright/{artwork:slug?}', [CopyrightReportController::class, 'create'])->name('copyright.create');
Route::post('/report-copyright', [CopyrightReportController::class, 'store'])->middleware('throttle:forms')->name('copyright.store');
Route::get('/support', [SupportController::class, 'create'])->name('support.create');
Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:forms')->name('support.store');

/*
| Artwork media. Unpublished artwork is only served to authorised users.
*/
Route::get('/media/artwork/{version}/{variant}', [MediaController::class, 'artwork'])
    ->whereIn('variant', ['preview', 'thumbnail'])
    ->name('media.artwork');
Route::get('/media/finished/{image}/{variant}', [FinishedProductController::class, 'image'])
    ->whereIn('variant', ['preview', 'thumbnail'])
    ->name('media.finished');
Route::get('/media/artwork/{version}/original/download', [MediaController::class, 'original'])
    ->middleware('auth')
    ->name('media.original');

/*
| Cart and checkout (guests allowed)
*/
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{item}', [CartController::class, 'destroy'])->name('cart.items.destroy');
Route::post('/cart/discount', [CartController::class, 'applyDiscount'])->middleware('throttle:forms')->name('cart.discount.apply');
Route::delete('/cart/discount', [CartController::class, 'removeDiscount'])->name('cart.discount.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout.store');

/*
| Order tracking by secret token (works for guests) and payment retry/polling.
*/
Route::get('/track', [OrderTrackingController::class, 'lookup'])->name('orders.lookup');
Route::post('/track', [OrderTrackingController::class, 'find'])->middleware('throttle:forms')->name('orders.find');
Route::get('/track/{token}', [OrderTrackingController::class, 'show'])->name('orders.track');
Route::get('/track/{token}/payment-status', [OrderTrackingController::class, 'paymentStatus'])->middleware('throttle:60,1')->name('orders.payment-status');
Route::post('/track/{token}/pay', [OrderTrackingController::class, 'pay'])->middleware('throttle:checkout')->name('orders.pay');

Route::get('/payments/simulator/{payment}', [PaymentSimulatorController::class, 'show'])->name('payments.simulator.show');
Route::post('/payments/simulator/{payment}', [PaymentSimulatorController::class, 'complete'])->name('payments.simulator.complete');

/*
| Authentication
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:forms');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:forms')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
});
