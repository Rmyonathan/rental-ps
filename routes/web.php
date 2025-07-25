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

// Redirect root to rentals
Route::get('/', function () {
    return redirect()->route('rentals.index');
});

// Rental Management Routes
Route::controller(RentalController::class)->group(function () {
    // Main rental CRUD routes
    Route::get('/rentals', 'index')->name('rentals.index');
    Route::get('/rentals/create', 'create')->name('rentals.create');
    Route::post('/rentals', 'store')->name('rentals.store');
    
    // Rental management actions
    Route::patch('/rentals/{rental}/extend', 'extend')->name('rentals.extend');
    Route::post('/rentals/{id}/extend', 'extend')->name('rentals.extend.post'); // Added for modal form
    Route::patch('/rentals/{rental}/complete', 'complete')->name('rentals.complete');
    Route::post('/rentals/{id}/complete', 'complete')->name('rentals.complete.post'); // Added for AJAX
    Route::post('/rentals/{rental}/timeout', 'forceTimeout')->name('rentals.timeout');
    Route::post('/rentals/{id}/force-timeout', 'forceTimeout')->name('rentals.force-timeout'); // Added for modal
    Route::post('/rentals/{rental}/switch-playstation', 'switchToPlayStation')->name('rentals.switch-playstation');
    
    // AJAX endpoints
    Route::post('/rentals/auto-check', 'autoCheck')->name('rentals.auto-check');
    Route::post('/rentals/test-connection', 'testConnection')->name('rentals.test-connection');
    Route::post('/rentals/debug-connection', 'debugConnection')->name('rentals.debug-connection');

    // Expired rentals management
    Route::get('/rentals/expired', 'expired')->name('rentals.expired');
    Route::delete('/rentals/{rental}/delete-expired', 'deleteExpired')->name('rentals.delete-expired');
    Route::delete('/rentals/clear-all-expired', 'clearAllExpired')->name('rentals.clear-all-expired');

    // API endpoints for real-time updates
    Route::get('/api/active-rentals', 'getActiveRentals')->name('api.active-rentals');
    Route::patch('/api/rentals/{rental}/status', 'updateStatus')->name('api.rentals.update-status');

    // Partial view for AJAX refresh
    Route::get('/rentals/active-list-partial', 'getActiveRentalsPartial')->name('rentals.active-list-partial');

    // Station management routes
    Route::get('/rentals/stations', 'stations')->name('rentals.stations');
    Route::post('/rentals/stations/refresh', 'refreshStations')->name('rentals.stations.refresh');
    
    // Testing and debugging routes
    Route::get('/test-adb-path', 'testAdbPath')->name('test.adb-path');
    Route::post('/rentals/test-tv/{tvIp}', 'testTvConnection')->name('rentals.test-tv');
    
    // Cafe/Food ordering routes (for future implementation)
    Route::get('/rentals/{station}/cafe', 'showCafeMenu')->name('rentals.cafe');
    Route::post('/rentals/{station}/cafe/order', 'createFoodOrder')->name('rentals.cafe.order');
    Route::get('/cafe/orders', 'cafeOrders')->name('cafe.orders');
    
    // Bulk operations
    Route::post('/rentals/bulk-complete', 'bulkComplete')->name('rentals.bulk-complete');
    Route::post('/rentals/bulk-extend', 'bulkExtend')->name('rentals.bulk-extend');

    // Transaction routes
    Route::get('/transactions', [App\Http\Controllers\TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/data', [App\Http\Controllers\TransactionController::class, 'getData'])->name('transactions.data');
    Route::post('/transactions', [App\Http\Controllers\TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/summary', [App\Http\Controllers\TransactionController::class, 'summary'])->name('transactions.summary');

    
    // Reports and analytics
    Route::get('/rentals/reports', 'reports')->name('rentals.reports');
    Route::get('/rentals/analytics', 'analytics')->name('rentals.analytics');
    Route::post('/rentals/update-end-time', [RentalController::class, 'updateEndTime']);
    Route::post('/tv-control', [RentalController::class, 'controlTv']);
    Route::post('/rentals/test-connection', [RentalController::class, 'testConnection']);
    Route::get('/rentals/{rental}', [RentalController::class, 'show'])->name('rentals.show');

    // Real-time monitoring
    Route::get('/rentals/monitor', 'monitor')->name('rentals.monitor');
    Route::post('/rentals/monitor/alert', 'sendAlert')->name('rentals.monitor.alert');

    // Cafe routes
Route::prefix('cafe')->name('cafe.')->group(function () {
    Route::get('/menu', [CafeController::class, 'menu'])->name('menu');
    Route::post('/order', [CafeController::class, 'placeOrder'])->name('order.place');
    Route::get('/order/{cafeOrder}/confirmation', [CafeController::class, 'orderConfirmation'])->name('order.confirmation');
    Route::get('/orders', [CafeController::class, 'orders'])->name('orders');
    Route::patch('/order/{cafeOrder}/status', [CafeController::class, 'updateOrderStatus'])->name('order.status');
    
    // Stock management
    Route::prefix('stock')->name('stock.')->group(function () {
        Route::get('/', [CafeStockController::class, 'index'])->name('index');
        Route::get('/create', [CafeStockController::class, 'create'])->name('create');
        Route::post('/', [CafeStockController::class, 'store'])->name('store');
        Route::get('/{cafeStock}/edit', [CafeStockController::class, 'edit'])->name('edit');
        Route::patch('/{cafeStock}', [CafeStockController::class, 'update'])->name('update');
        Route::post('/{cafeStock}/add-stock', [CafeStockController::class, 'addStock'])->name('add');
    });
    // Add these routes inside your cafe group
Route::prefix('items')->name('items.')->group(function () {
    Route::get('/', [CafeItemController::class, 'index'])->name('index');
    Route::get('/create', [CafeItemController::class, 'create'])->name('create');
    Route::post('/', [CafeItemController::class, 'store'])->name('store');
    Route::get('/{cafeItem}/edit', [CafeItemController::class, 'edit'])->name('edit');
    Route::patch('/{cafeItem}', [CafeItemController::class, 'update'])->name('update');
    Route::delete('/{cafeItem}', [CafeItemController::class, 'destroy'])->name('destroy');
});
});


});

// Additional utility routes
Route::get('/system/status', [RentalController::class, 'systemStatus'])->name('system.status');
Route::post('/system/restart-services', [RentalController::class, 'restartServices'])->name('system.restart-services');

// Emergency routes
Route::post('/emergency/stop-all', [RentalController::class, 'emergencyStopAll'])->name('emergency.stop-all');
Route::post('/emergency/disconnect-all-tvs', [RentalController::class, 'disconnectAllTvs'])->name('emergency.disconnect-all-tvs');

Route::get('/rentals/active-data', [RentalController::class, 'getActiveData']);
Route::get('/rental-history/{tv_ip}', [RentalController::class, 'historyByIp'])->name('rental.history');
Route::get('/rental-ip-list', [RentalController::class, 'ipList'])->name('rental.iplist');
Route::get('/expired-rentals', [RentalController::class, 'expiredRentals']);


