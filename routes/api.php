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

// EightBall Proxy Routes for React App (No Database Required)
Route::prefix('eightball')->middleware('cors')->group(function () {
    // Locations
    Route::get('/latepoint/locations', [App\Http\Controllers\Api\EightballProxyController::class, 'getLocations']);

    // Service Categories
    Route::get('/latepoint/services/categories/location/{id}', [App\Http\Controllers\Api\EightballProxyController::class, 'getServiceCategories']);

    // Services
    Route::get('/latepoint/services/location/{id}', [App\Http\Controllers\Api\EightballProxyController::class, 'getServicesByLocation']);

    // Agents
    Route::get('/latepoint/agents/location/{locationId}/service/{serviceId}', [App\Http\Controllers\Api\EightballProxyController::class, 'getAgentsByLocationAndService']);

    // Calendar and Availability
    Route::get('/latepoint/calendar', [App\Http\Controllers\Api\EightballProxyController::class, 'getCalendarAvailability']);
    Route::get('/latepoint/time-slots-auto', [App\Http\Controllers\Api\EightballProxyController::class, 'getTimeSlots']);
    Route::get('/latepoint/availability', [App\Http\Controllers\Api\EightballProxyController::class, 'checkAvailability']);

    // Bookings
    Route::post('/latepoint/bookings', [App\Http\Controllers\Api\EightballProxyController::class, 'createBooking'])->middleware('throttle:5,1');
    Route::get('/latepoint/bookings/{id}', [App\Http\Controllers\Api\EightballProxyController::class, 'getBooking']);

    // Location Mappings
    Route::get('/location-mappings', [App\Http\Controllers\Api\EightballProxyController::class, 'getLocationMappings']);

    // Shopify Services by Product Type
    Route::get('/shopify-services', [App\Http\Controllers\Api\EightballProxyController::class, 'getShopifyServicesByProductType']);
});

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
