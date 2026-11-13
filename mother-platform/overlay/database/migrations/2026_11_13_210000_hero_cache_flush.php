<?php
use Illuminate\Database\Migrations\Migration;
/** Owner 13 Nov 2026: one-time home cache flush so slides already paused in Hero Media Slider disappear at once. */
return new class extends Migration {
  public function up(): void {
    foreach (['store.settings2', 'store.home11', 'store.home10', 'store.home8', 'store.home5', 'store.home6', 'store.home4'] as $c) { try { \Cache::forget($c); } catch (\Throwable $e) {} }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
