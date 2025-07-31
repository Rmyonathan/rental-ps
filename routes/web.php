<?php

use App\Http\Controllers\RentalController;
use App\Http\Controllers\CafeStockController;
use App\Http\Controllers\CafeController;
use App\Http\Controllers\CafeItemController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root to the main rental dashboard
Route::get('/', function () {
    return redirect()->route('rentals.index');
});

// --- Rental Management ---
Route::controller(RentalController::class)->group(function () {
    // Main rental dashboard and CRUD
    Route::get('/rentals', 'index')->name('rentals.index');
    Route::get('/rentals/create', 'create')->name('rentals.create');
    Route::post('/rentals', 'store')->name('rentals.store');
    Route::get('/rentals/{rental}', 'show')->name('rentals.show');

    // Actions on active rentals
    Route::post('/rentals/{rentalId}/extend', 'extend')->name('rentals.extend');
    Route::post('/rentals/{rentalId}/complete', 'complete')->name('rentals.complete');
    Route::post('/rentals/{rentalId}/force-timeout', 'forceTimeout')->name('rentals.force-timeout');
    
    // History, Expired Rentals, and IP Lists
    Route::get('/rentals/expired', 'expired')->name('rentals.expired');
    Route::delete('/rentals/{rental}/delete-expired', 'deleteExpired')->name('rentals.delete-expired');
    Route::delete('/rentals/clear-all-expired', 'clearAllExpired')->name('rentals.clear-all-expired');
    Route::get('/rental-history/{tv_ip}', 'historyByIp')->name('rentals.history');
    Route::get('/rental-ip-list', 'ipList')->name('rentals.ip-list');

    // --- TV Control & Debugging API Routes (called from frontend JS) ---
    Route::post('/tv-control', 'controlTv')->name('tv.control');
    Route::post('/test-connection', 'testConnection')->name('tv.test-connection');
    Route::get('/test-adb-path', 'testAdbPath')->name('tv.test-adb-path'); // For debugging
    Route::post('/rentals/update-end-time', 'updateEndTime')->name('rentals.update-end-time');
    Route::post('/rentals/refresh-all-statuses', 'refreshAllStatuses')->name('rentals.refresh-all');

});

// --- Cafe & Stock Management ---
Route::prefix('cafe')->name('cafe.')->group(function () {
    Route::get('/menu', [CafeController::class, 'menu'])->name('menu');
    Route::post('/order', [CafeController::class, 'placeOrder'])->name('order.place');
    Route::get('/order/{cafeOrder}/confirmation', [CafeController::class, 'orderConfirmation'])->name('order.confirmation');
    Route::get('/orders', [CafeController::class, 'orders'])->name('orders');
    Route::patch('/order/{cafeOrder}/status', [CafeController::class, 'updateOrderStatus'])->name('order.status');
    
    // Menu Item Management (Resource Route)
    Route::resource('items', CafeItemController::class)->except(['show']);

    // Stock Management
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [CafeStockController::class, 'index'])->name('index');
        Route::get('/create', [CafeStockController::class, 'create'])->name('create');
        Route::post('/', [CafeStockController::class, 'store'])->name('store');
        Route::get('/{cafeStock}/edit', [CafeStockController::class, 'edit'])->name('edit');
        Route::patch('/{cafeStock}', [CafeStockController::class, 'update'])->name('update');
        Route::post('/{cafeStock}/add-stock', [CafeStockController::class, 'addStock'])->name('add');
    });
});

// --- Transaction Management ---
Route::prefix('transactions')->name('transactions.')->group(function () {
    Route::get('/', [TransactionController::class, 'index'])->name('index');
    Route::post('/', [TransactionController::class, 'store'])->name('store');
    Route::get('/summary', [TransactionController::class, 'summary'])->name('summary');
});

