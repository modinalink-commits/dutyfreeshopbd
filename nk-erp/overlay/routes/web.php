<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\WholesaleController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RoadmapController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public API used by the nklpgbd.com website order form.
Route::post('/api/website-order', [ApiController::class, 'websiteOrder'])->middleware('throttle:8,1');
Route::get('/api/ping', fn () => response()->json(['ok' => true]));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'pwd'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::post('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders/{order}', [OrderController::class, 'update'])->name('orders.update')
        ->middleware('role:super_admin,admin,manager');

    Route::get('/soon/{key}', [RoadmapController::class, 'soon'])->name('soon');
    Route::get('/build-progress', [RoadmapController::class, 'index'])->name('roadmap');

    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/history', [StockController::class, 'history'])->name('stock.history');
    Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');

    // Sales: counter staff (all roles except salesman) can sell; managers handle wholesale and voids.
    Route::middleware('role:super_admin,admin,manager,accounts')->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
        Route::get('/pos/customer', [PosController::class, 'lookup'])->name('pos.lookup');
        Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/print', [SaleController::class, 'print'])->name('sales.print');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'save'])->name('customers.save');
        Route::post('/customers/{customer}/pay', [CustomerController::class, 'pay'])->name('customers.pay');
        Route::post('/customers/{customer}/empties', [CustomerController::class, 'empties'])->name('customers.empties');
        Route::get('/wholesale', [WholesaleController::class, 'index'])->name('wholesale.index');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    });

    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::post('/wholesale', [WholesaleController::class, 'store'])->name('wholesale.store');
        Route::post('/wholesale/{sale}/load', [WholesaleController::class, 'load'])->name('wholesale.load');
        Route::post('/wholesale/{sale}/deliver', [WholesaleController::class, 'deliver'])->name('wholesale.deliver');
        Route::post('/sales/{sale}/void', [SaleController::class, 'void'])->name('sales.void');
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::post('/products', [ProductController::class, 'save'])->name('products.save');
        Route::post('/products/{product}/stock', [ProductController::class, 'receive'])->name('products.stock');
    });

    Route::middleware('role:super_admin,admin,manager')->group(function () {
        Route::get('/stock/new', [StockController::class, 'create'])->name('stock.create');
        Route::post('/stock', [StockController::class, 'store'])->name('stock.store');
        Route::post('/exchanges', [ExchangeController::class, 'store'])->name('exchanges.store');
        Route::post('/exchanges/{exchange}', [ExchangeController::class, 'decide'])->name('exchanges.decide');
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::post('/brands', [BrandController::class, 'save'])->name('brands.save');
        Route::post('/stock/{movement}/reverse', [StockController::class, 'reverse'])->name('stock.reverse');
    });

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update')->middleware('role:super_admin');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/activity', [UserController::class, 'activity'])->name('activity');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
        Route::post('/users/{user}/reset', [UserController::class, 'reset'])->name('users.reset');
    });
});
