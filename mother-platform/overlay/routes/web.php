<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController as A;
Route::get('/', fn() => redirect('/admin'));
Route::get('/login', [A::class,'loginForm'])->name('login');
Route::post('/login', [A::class,'login']);
Route::post('/logout', [A::class,'logout']);
Route::middleware('auth')->prefix('admin')->group(function () {
  Route::get('/', [A::class,'dashboard']);
  Route::get('/orders', [A::class,'orders']);
  Route::get('/orders/new', [A::class,'orderNew']);
  Route::post('/orders', [A::class,'orderStore']);
  Route::get('/orders/{id}', [A::class,'orderShow']);
  Route::post('/orders/{id}/status', [A::class,'orderStatus']);
  Route::get('/orders-export.csv', [A::class,'ordersCsv']);
  Route::get('/staff-log', [A::class,'staffLog']);
});
