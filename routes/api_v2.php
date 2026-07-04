<?php

use App\Http\Controllers\Api\V2\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v2 routes
|--------------------------------------------------------------------------
|
| Registered under the `api/v2` prefix with the `api` middleware group and the
| `api.v2.` route-name prefix (see bootstrap/app.php). Only the Products
| endpoints are versioned here; the unversioned routes in routes/api.php
| continue to serve the existing (v1) clients.
|
*/

// Public reads.
Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('products/{product}/statistics', [ProductController::class, 'statistics'])->name('products.statistics');

// Authenticated writes (Sanctum bearer token).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::post('products/{product}/sync', [ProductController::class, 'sync'])->name('products.sync');
});
