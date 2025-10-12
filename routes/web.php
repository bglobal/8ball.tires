<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CalendarPreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Admin routes
    Route::post('/admin/shopify/sync', [App\Http\Controllers\Admin\ShopifySyncController::class, 'sync'])
        ->name('admin.shopify.sync');
    
    // Calendar Preview routes
    Route::get('/admin/calendar-preview', [CalendarPreviewController::class, 'index'])
        ->name('calendar-preview');
    Route::post('/admin/calendar-preview/sync', [CalendarPreviewController::class, 'syncShopify'])
        ->name('calendar-preview.sync');
});

require __DIR__.'/auth.php';
