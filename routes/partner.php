<?php

use App\Http\Controllers\Partner\JobController;
use App\Http\Controllers\Partner\ShipmentController;
use Illuminate\Support\Facades\Route;

/*
| Printing partner portal (auth + partner_staff role), prefixed /partner and named partner.*.
| Every action re-checks via ProductionJobPolicy/ShipmentPolicy that the record belongs to
| the signed-in user's facility.
*/
Route::get('/', [JobController::class, 'index'])->name('dashboard');
Route::get('/jobs/{job:reference}', [JobController::class, 'show'])->name('jobs.show');
Route::post('/jobs/{job:reference}/advance', [JobController::class, 'advance'])->name('jobs.advance');
Route::post('/jobs/{job:reference}/quality-check', [JobController::class, 'qualityCheck'])->name('jobs.qc');
Route::post('/jobs/{job:reference}/issue', [JobController::class, 'issue'])->name('jobs.issue');
Route::post('/jobs/{job:reference}/fail', [JobController::class, 'fail'])->name('jobs.fail');

Route::get('/shipments', [ShipmentController::class, 'index'])->name('shipments.index');
Route::post('/orders/{order:number}/shipments', [ShipmentController::class, 'store'])->name('shipments.store');
Route::post('/shipments/{shipment:reference}/events', [ShipmentController::class, 'event'])->name('shipments.events.store');
