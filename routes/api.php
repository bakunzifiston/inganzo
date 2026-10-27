<?php

use App\Http\Controllers\Api\V1\AccountOrderController;
use App\Http\Controllers\Api\V1\ArtistController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\PaymentCallbackController;
use Illuminate\Support\Facades\Route;

/*
| Versioned REST API for mobile apps and integrations. Public catalogue endpoints are
| read-only; account endpoints use Sanctum personal access tokens.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::get('products', [CatalogController::class, 'index'])->name('products.index');
        Route::get('products/{artistProduct}', [CatalogController::class, 'show'])->name('products.show');
        Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
        Route::get('product-types', [CatalogController::class, 'productTypes'])->name('product-types.index');
        Route::get('artists', [ArtistController::class, 'index'])->name('artists.index');
        Route::get('artists/{artistProfile:slug}', [ArtistController::class, 'show'])->name('artists.show');
    });

    // Provider callbacks (MTN sends PUT or POST). Verification happens in PaymentService.
    Route::match(['post', 'put'], 'payments/callback/{provider}', PaymentCallbackController::class)
        ->middleware('throttle:webhooks')
        ->name('payments.callback');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::get('me', [AccountOrderController::class, 'me'])->name('me');
        Route::get('me/orders', [AccountOrderController::class, 'index'])->name('me.orders.index');
        Route::get('me/orders/{order:number}', [AccountOrderController::class, 'show'])->name('me.orders.show');
    });
});
