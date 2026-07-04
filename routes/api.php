<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderImportController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;
use Tsitsishvili\ElasticAudit\Http\Middleware\IncomingHttpLogMiddleware;

// Authentication (Sanctum personal access tokens).
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
});

// Newsletter — inline ($request->validate) validation, no FormRequest/DTO.
Route::post('newsletter/subscribe', [NewsletterController::class, 'subscribe']);

// Public reads.
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('products/{product}/statistics', [ProductController::class, 'statistics']);

// Authenticated writes (Sanctum bearer token).
Route::middleware('auth:sanctum')->group(function () {
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::post('products/{product}/sync', [ProductController::class, 'sync']);

    // Orders — spatie/laravel-data DTOs through the service/repository pipeline.
    Route::post('orders/import', [OrderImportController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::put('orders/{order}', [OrderController::class, 'update']);
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::delete('orders/{order}', [OrderController::class, 'destroy']);
    Route::post('orders/{order}/ship', [OrderController::class, 'ship']);
    Route::get('orders/{order}/shipment', [OrderController::class, 'shipment']);
    Route::post('orders/{order}/reconcile', [OrderController::class, 'reconcile']);
});

// Incoming third-party callbacks — recorded by elastic-audit's incoming middleware.
Route::middleware(IncomingHttpLogMiddleware::class)->group(function () {
    Route::post('webhooks/stripe', [WebhookController::class, 'stripe']);
});
