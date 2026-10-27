<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

/*
| Administration console (auth + admin.access), prefixed /admin and named admin.*.
| Each route additionally requires the specific permission for its area.
*/
Route::get('/', Admin\DashboardController::class)->name('dashboard');

Route::middleware('can:orders.view')->group(function () {
    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/export', [Admin\OrderController::class, 'export'])->name('orders.export');
    Route::get('/orders/{order:number}', [Admin\OrderController::class, 'show'])->name('orders.show');
});
Route::middleware('can:orders.manage')->group(function () {
    Route::post('/orders/{order:number}/items/{item}/assign', [Admin\OrderController::class, 'assign'])->name('orders.items.assign');
    Route::post('/orders/{order:number}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order:number}/shipments', [Admin\OrderController::class, 'ship'])->name('orders.ship');
    Route::post('/shipments/{shipment:reference}/events', [Admin\OrderController::class, 'shipmentEvent'])->name('shipments.events.store');
    Route::post('/jobs/{job:reference}/fail', [Admin\ProductionController::class, 'fail'])->name('jobs.fail');
    Route::post('/jobs/{job:reference}/reprint', [Admin\ProductionController::class, 'reprint'])->name('jobs.reprint');
    Route::get('/production', [Admin\ProductionController::class, 'index'])->name('production.index');
});
Route::middleware('can:payments.view')->group(function () {
    Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/refresh', [Admin\PaymentController::class, 'refresh'])->name('payments.refresh');
});
Route::middleware('can:refunds.manage')->group(function () {
    Route::get('/refunds', [Admin\RefundController::class, 'index'])->name('refunds.index');
    Route::post('/orders/{order:number}/refunds', [Admin\RefundController::class, 'store'])->name('refunds.store');
    Route::post('/refunds/{refund}/approve', [Admin\RefundController::class, 'approve'])->name('refunds.approve');
    Route::post('/refunds/{refund}/process', [Admin\RefundController::class, 'process'])->name('refunds.process');
    Route::post('/refunds/{refund}/reject', [Admin\RefundController::class, 'reject'])->name('refunds.reject');
});

Route::middleware('can:artworks.moderate')->group(function () {
    Route::get('/moderation', [Admin\ModerationController::class, 'index'])->name('moderation.index');
    Route::get('/moderation/{artwork:slug}', [Admin\ModerationController::class, 'show'])->name('moderation.show');
    Route::post('/moderation/{artwork:slug}/approve', [Admin\ModerationController::class, 'approve'])->name('moderation.approve');
    Route::post('/moderation/{artwork:slug}/reject', [Admin\ModerationController::class, 'reject'])->name('moderation.reject');
    Route::post('/moderation/{artwork:slug}/suspend', [Admin\ModerationController::class, 'suspend'])->name('moderation.suspend');
    Route::post('/moderation/{artwork:slug}/reinstate', [Admin\ModerationController::class, 'reinstate'])->name('moderation.reinstate');

    Route::get('/ready-made', [Admin\FinishedProductController::class, 'index'])->name('finished.index');
    Route::get('/ready-made/{finished_product:slug}', [Admin\FinishedProductController::class, 'show'])->name('finished.show');
    Route::post('/ready-made/{finished_product:slug}/{decision}', [Admin\FinishedProductController::class, 'decide'])->whereIn('decision', ['approve', 'reject', 'suspend', 'reinstate'])->name('finished.decide');
});

Route::middleware('can:artists.view')->group(function () {
    Route::get('/artists', [Admin\ArtistController::class, 'index'])->name('artists.index');
    Route::get('/artists/{artistProfile:slug}', [Admin\ArtistController::class, 'show'])->name('artists.show');
});
Route::middleware('can:artists.manage')->group(function () {
    Route::post('/artists/{artistProfile:slug}/status', [Admin\ArtistController::class, 'status'])->name('artists.status');
    Route::post('/artists/{artistProfile:slug}/feature', [Admin\ArtistController::class, 'feature'])->name('artists.feature');
});
Route::middleware('can:payouts.manage')->group(function () {
    Route::post('/payout-accounts/{payoutAccount}/verify', [Admin\PayoutController::class, 'verifyAccount'])->name('payout-accounts.verify');
    Route::get('/payouts', [Admin\PayoutController::class, 'index'])->name('payouts.index');
    Route::post('/payouts/{payout:reference}/approve', [Admin\PayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('/payouts/{payout:reference}/paid', [Admin\PayoutController::class, 'paid'])->name('payouts.paid');
    Route::post('/payouts/{payout:reference}/reject', [Admin\PayoutController::class, 'reject'])->name('payouts.reject');
    Route::post('/payouts/{payout:reference}/failed', [Admin\PayoutController::class, 'failed'])->name('payouts.failed');
});
Route::middleware('can:finance.view')->group(function () {
    Route::get('/finance', [Admin\FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/ledger/export', [Admin\FinanceController::class, 'export'])->name('finance.export');
});
Route::middleware('can:finance.manage')->group(function () {
    Route::resource('pricing-rules', Admin\PricingRuleController::class)->except(['show', 'destroy']);
    Route::post('/finance/adjustments', [Admin\FinanceController::class, 'adjust'])->name('finance.adjust');
});

Route::middleware('can:catalog.manage')->group(function () {
    Route::resource('products', Admin\ProductController::class)->except(['show', 'destroy']);
    Route::post('/products/{product}/colors', [Admin\ProductController::class, 'storeColor'])->name('products.colors.store');
    Route::post('/products/{product}/sizes', [Admin\ProductController::class, 'storeSize'])->name('products.sizes.store');
    Route::put('/products/{product}/variants', [Admin\ProductController::class, 'updateVariants'])->name('products.variants.update');
    Route::put('/products/{product}/print-areas/{printArea}', [Admin\ProductController::class, 'updatePrintArea'])->name('products.print-areas.update');
    Route::post('/products/{product}/mockups', [Admin\ProductController::class, 'storeMockup'])->name('products.mockups.store');
    Route::resource('product-types', Admin\ProductTypeController::class)->except(['show', 'destroy']);
});

Route::middleware('can:partners.manage')->group(function () {
    Route::resource('partners', Admin\PartnerController::class)->except(['destroy']);
    Route::put('/partners/{partner}/services', [Admin\PartnerController::class, 'updateServices'])->name('partners.services.update');
    Route::post('/partners/{partner}/areas', [Admin\PartnerController::class, 'storeArea'])->name('partners.areas.store');
    Route::delete('/partners/{partner}/areas/{area}', [Admin\PartnerController::class, 'destroyArea'])->name('partners.areas.destroy');
    Route::post('/partners/{partner}/members', [Admin\PartnerController::class, 'storeMember'])->name('partners.members.store');
    Route::delete('/partners/{partner}/members/{member}', [Admin\PartnerController::class, 'destroyMember'])->name('partners.members.destroy');
});

Route::middleware('can:discounts.manage')->group(function () {
    Route::resource('discounts', Admin\DiscountController::class)->except(['show', 'destroy']);
});

Route::middleware('can:content.manage')->group(function () {
    Route::resource('categories', Admin\CategoryController::class)->except(['show', 'destroy']);
    Route::resource('collections', Admin\CollectionController::class)->except(['show', 'destroy']);
    Route::resource('promotions', Admin\PromotionController::class)->except(['show']);
});

Route::middleware('can:localization.manage')->group(function () {
    Route::get('/localization', [Admin\LocalizationController::class, 'index'])->name('localization.index');
    Route::put('/countries/{country}', [Admin\LocalizationController::class, 'updateCountry'])->name('countries.update');
    Route::post('/delivery-zones', [Admin\LocalizationController::class, 'storeZone'])->name('delivery-zones.store');
    Route::put('/delivery-zones/{zone}', [Admin\LocalizationController::class, 'updateZone'])->name('delivery-zones.update');
    Route::post('/exchange-rates', [Admin\LocalizationController::class, 'storeRate'])->name('exchange-rates.store');
});

Route::middleware('can:reports.manage')->group(function () {
    Route::get('/copyright-reports', [Admin\CopyrightReportController::class, 'index'])->name('copyright.index');
    Route::get('/copyright-reports/{report}', [Admin\CopyrightReportController::class, 'show'])->name('copyright.show');
    Route::post('/copyright-reports/{report}/resolve', [Admin\CopyrightReportController::class, 'resolve'])->name('copyright.resolve');
});

Route::middleware('can:support.manage')->group(function () {
    Route::get('/support', [Admin\SupportController::class, 'index'])->name('support.index');
    Route::get('/support/{ticket:reference}', [Admin\SupportController::class, 'show'])->name('support.show');
    Route::put('/support/{ticket:reference}', [Admin\SupportController::class, 'update'])->name('support.update');
});

Route::middleware('can:users.view')->group(function () {
    Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
});
Route::middleware('can:users.manage')->group(function () {
    Route::post('/users/{user}/suspend', [Admin\UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/reactivate', [Admin\UserController::class, 'reactivate'])->name('users.reactivate');
});
Route::middleware('can:staff.manage')->group(function () {
    Route::put('/users/{user}/roles', [Admin\UserController::class, 'roles'])->name('users.roles');
    Route::get('/roles', [Admin\RoleController::class, 'index'])->name('roles.index');
    Route::put('/roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
});

Route::middleware('can:settings.manage')->group(function () {
    Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
});

Route::middleware('can:audit.view')->group(function () {
    Route::get('/audit', [Admin\SystemController::class, 'audit'])->name('audit.index');
    Route::get('/system', [Admin\SystemController::class, 'health'])->name('system.health');
});
