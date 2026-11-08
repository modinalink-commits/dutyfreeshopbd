<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner 9 Nov 2026: only the Watch slide stays in the hero banner; every other hero banner is switched off (not deleted - Home Banners page turns them back on). */
return new class extends Migration {
  public function up(): void {
    try {
      foreach (['home_globe', 'hero_offers_slide', 'hero_member_slide', 'hero_district_slide', 'hero_text_slide'] as $k) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => '0', 'updated_at' => now(), 'created_at' => now()]);
      DB::table('settings')->updateOrInsert(['key' => 'hero_watch_slide'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]);
      DB::table('sliders')->where(fn($q) => $q->whereNull('type')->orWhere('type', 'main'))->update(['active' => false, 'updated_at' => now()]);
      foreach (['store.settings2', 'store.home11', 'store.home10', 'store.home8', 'store.home5', 'store.home6', 'store.home4'] as $c) { try { \Cache::forget($c); } catch (\Throwable $e) {} }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
