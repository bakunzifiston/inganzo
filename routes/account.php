<?php

use App\Http\Controllers\Account\AddressController;
use App\Http\Controllers\Account\BecomeArtistController;
use App\Http\Controllers\Account\DashboardController;
use App\Http\Controllers\Account\FavoriteController;
use App\Http\Controllers\Account\OrderController;
use App\Http\Controllers\Account\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Customer account (auth + verified). Loaded from bootstrap/app.php.
*/
Route::prefix('account')->name('account.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order:number}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order:number}/refunds', [OrderController::class, 'requestRefund'])->name('orders.refunds.store');
    Route::post('/orders/{order:number}/items/{item}/review', [OrderController::class, 'review'])->name('orders.review');

    Route::resource('addresses', AddressController::class)->except(['show']);

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::get('/notifications', [ProfileController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read', [ProfileController::class, 'markNotificationsRead'])->name('notifications.read');

    Route::get('/sell', [BecomeArtistController::class, 'create'])->name('become-artist');
    Route::post('/sell', [BecomeArtistController::class, 'store'])->name('become-artist.store');
});

Route::post('/favorites/{artwork:slug}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
