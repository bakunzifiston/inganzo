<?php

use App\Http\Controllers\Studio\ArtworkController;
use App\Http\Controllers\Studio\DashboardController;
use App\Http\Controllers\Studio\DesignStudioController;
use App\Http\Controllers\Studio\EarningsController;
use App\Http\Controllers\Studio\FinishedProductController;
use App\Http\Controllers\Studio\OrderController;
use App\Http\Controllers\Studio\ProductEditorController;
use App\Http\Controllers\Studio\ProductsController;
use App\Http\Controllers\Studio\SalesController;
use App\Http\Controllers\Studio\ShopController;
use Illuminate\Support\Facades\Route;

/*
| Artist workspace (auth + artist role), prefixed /studio and named studio.*.
*/
Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/artworks', [ArtworkController::class, 'index'])->name('artworks.index');
Route::get('/artworks/create', [ArtworkController::class, 'create'])->name('artworks.create');
Route::post('/artworks', [ArtworkController::class, 'store'])->middleware('throttle:uploads')->name('artworks.store');
Route::get('/artworks/{artwork:slug}', [ArtworkController::class, 'show'])->name('artworks.show');
Route::put('/artworks/{artwork:slug}', [ArtworkController::class, 'update'])->name('artworks.update');
Route::post('/artworks/{artwork:slug}/file', [ArtworkController::class, 'replaceFile'])->middleware('throttle:uploads')->name('artworks.file');
Route::post('/artworks/{artwork:slug}/submit', [ArtworkController::class, 'submit'])->name('artworks.submit');
Route::post('/artworks/{artwork:slug}/archive', [ArtworkController::class, 'archive'])->name('artworks.archive');
Route::post('/artworks/{artwork:slug}/restore', [ArtworkController::class, 'restore'])->name('artworks.restore');
Route::post('/artworks/{artwork:slug}/products/defaults', [ProductEditorController::class, 'enableDefaults'])->name('artworks.products.defaults');
Route::get('/artworks/{artwork:slug}/products/{product:slug}', [ProductEditorController::class, 'edit'])->withoutScopedBindings()->name('artworks.products.edit');
Route::put('/artworks/{artwork:slug}/products/{product:slug}', [ProductEditorController::class, 'update'])->withoutScopedBindings()->name('artworks.products.update');

// One catalogue and one order list for both selling methods.
Route::get('/products', ProductsController::class)->name('products.index');
Route::view('/sell', 'studio.sell')->name('sell');

// Ready-made (finished) products sold from the artist's own stock.
Route::prefix('/finished')->name('finished.')->group(function () {
    Route::get('/new', [FinishedProductController::class, 'create'])->name('create');
    Route::post('/', [FinishedProductController::class, 'store'])->middleware('throttle:uploads')->name('store');
    Route::get('/{finished_product:slug}', [FinishedProductController::class, 'edit'])->name('edit');
    Route::put('/{finished_product:slug}', [FinishedProductController::class, 'update'])->middleware('throttle:uploads')->name('update');
    Route::post('/{finished_product:slug}/publish', [FinishedProductController::class, 'publish'])->name('publish');
    Route::post('/{finished_product:slug}/archive', [FinishedProductController::class, 'archive'])->name('archive');
    Route::post('/{finished_product:slug}/restore', [FinishedProductController::class, 'restore'])->name('restore');
    Route::patch('/{finished_product:slug}/stock', [FinishedProductController::class, 'stock'])->name('stock');
});

Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order:number}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/orders/{order:number}/ship', [OrderController::class, 'ship'])->name('orders.ship');
Route::post('/orders/{order:number}/shipments/{shipment}/delivered', [OrderController::class, 'delivered'])->name('orders.delivered');
Route::post('/orders/{order:number}/items/{item}/cannot-fulfil', [OrderController::class, 'cannotFulfil'])->name('orders.cannot-fulfil');

// Design Studio: editable compositions, studio image library and print-file export.
Route::get('/design/new', [DesignStudioController::class, 'create'])->name('design.create');
Route::post('/design', [DesignStudioController::class, 'store'])->name('design.store');
Route::match(['get', 'post'], '/artworks/{artwork:slug}/design/start', [DesignStudioController::class, 'start'])->name('artworks.design.start');
Route::get('/artworks/{artwork:slug}/design', [DesignStudioController::class, 'edit'])->name('artworks.design');
Route::put('/artworks/{artwork:slug}/design', [DesignStudioController::class, 'save'])->middleware('throttle:design-saves')->name('artworks.design.save');
Route::post('/artworks/{artwork:slug}/design/export', [DesignStudioController::class, 'export'])->middleware('throttle:uploads')->name('artworks.design.export');
Route::get('/design-assets', [DesignStudioController::class, 'assets'])->name('design-assets.index');
Route::post('/design-assets', [DesignStudioController::class, 'storeAsset'])->middleware('throttle:uploads')->name('design-assets.store');
Route::get('/design-assets/{asset}', [DesignStudioController::class, 'showAsset'])->name('design-assets.show');
Route::get('/design-sources/{version}', [DesignStudioController::class, 'showSource'])->name('design-sources.show');

Route::get('/shop', [ShopController::class, 'edit'])->name('shop.edit');
Route::put('/shop', [ShopController::class, 'update'])->name('shop.update');

Route::get('/sales', SalesController::class)->name('sales');

Route::get('/earnings', [EarningsController::class, 'index'])->name('earnings');
Route::post('/earnings/payouts', [EarningsController::class, 'requestPayout'])->name('earnings.payouts.store');
Route::put('/earnings/payout-account', [EarningsController::class, 'updateAccount'])->name('earnings.account');
