<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public API Routes
Route::get('/locations', [App\Http\Controllers\Api\LocationsController::class, 'index']);
Route::get('/location/{id}', [App\Http\Controllers\Api\LocationsController::class, 'show']);
Route::get('/services', [App\Http\Controllers\Api\ServicesController::class, 'index']);
Route::get('/availability', [App\Http\Controllers\Api\AvailabilityController::class, 'index']);
Route::get('/blackout/{id}', [App\Http\Controllers\BlackoutController::class, 'show']);
Route::post('/bookings', [App\Http\Controllers\Api\BookingsController::class, 'store'])->middleware('throttle:5,1');
Route::get('/bookings/{id}', [App\Http\Controllers\Api\BookingsController::class, 'show']);

// API Documentation
Route::get('/docs', function () {
    return view('api.docs');
});

Route::get('/docs/api.yaml', function () {
    return response()->file(base_path('docs/api.yaml'), [
        'Content-Type' => 'application/x-yaml',
    ]);
});

// Shopify Webhook Routes
Route::middleware(['verify.shopify.webhook'])->group(function () {
    Route::post('/webhooks/shopify/inventory-updated', [App\Http\Controllers\ShopifyWebhookController::class, 'handleInventoryUpdate']);
    Route::post('/webhooks/shopify/product-updated', [App\Http\Controllers\ShopifyWebhookController::class, 'handleProductUpdate']);
    Route::post('/webhooks/shopify/order-created', [App\Http\Controllers\ShopifyOrderWebhookController::class, 'handleOrderCreate']);
});
