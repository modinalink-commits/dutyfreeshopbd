<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner 13 Nov 2026: Chanel perfume hero banner (animated MP4 twin), placed after existing slides. One image slide in Hero Media Slider, whole slide links to Perfume. Idempotent. */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('sliders')) return;
      $has = fn($c) => Schema::hasColumn('sliders', $c);
      $img = '/banners/hero-perfume.webp';
      if (DB::table('sliders')->where('image', $img)->exists()) return;
      $pos = (int)DB::table('sliders')->where(fn($q) => $q->whereNull('type')->orWhere('type', 'main'))->max('position');
      $row = ['image' => $img, 'link' => '/perfume/', 'active' => true, 'position' => $pos + 1, 'created_at' => now(), 'updated_at' => now()];
      if ($has('mobile_image')) $row['mobile_image'] = '/banners/hero-perfume-m.webp';
      foreach (['type' => 'main', 'media_type' => 'image', 'title' => 'Perfume'] as $c => $v) if ($has($c)) $row[$c] = $v;
      DB::table('sliders')->insert($row);
      foreach (['store.settings2', 'store.home11', 'store.home10', 'store.home8', 'store.home5', 'store.home6', 'store.home4'] as $c) { try { \Cache::forget($c); } catch (\Throwable $e) {} }
      try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void { try { DB::table('sliders')->where('image', '/banners/hero-perfume.webp')->delete(); } catch (\Throwable $e) {} }
};
