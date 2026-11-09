<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner 9 Nov 2026: the six site-made hero slides become ordinary image slides he can edit/replace in Hero Media Slider. The built-in versions are switched off (not deleted) so nothing shows twice; Hero Media Slider can turn them back on. Idempotent. */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('sliders')) return;
      $has = fn($c) => Schema::hasColumn('sliders', $c);
      $pos = (int)DB::table('sliders')->where(fn($q) => $q->whereNull('type')->orWhere('type', 'main'))->max('position');
      $set = [['flash', '/#flash', 'hero_text_slide'], ['globe', '/', 'home_globe'], ['district', '/delivery-policy/', 'hero_district_slide'], ['member', '/account', 'hero_member_slide'], ['offers', '/#flash', 'hero_offers_slide'], ['watch', '/watch/', 'hero_watch_slide']];
      foreach ($set as [$n, $link, $key]) {
        $img = "/banners/hero-$n.webp";
        if (!DB::table('sliders')->where('image', $img)->exists()) {
          $row = ['image' => $img, 'link' => $link, 'active' => true, 'position' => ++$pos, 'created_at' => now(), 'updated_at' => now()];
          if ($has('mobile_image')) $row['mobile_image'] = "/banners/hero-$n-m.webp";
          foreach (['type' => 'main', 'media_type' => 'image'] as $c => $v) if ($has($c)) $row[$c] = $v;
          DB::table('sliders')->insert($row);
        }
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => '0', 'updated_at' => now(), 'created_at' => now()]);
      }
      foreach (['store.settings2', 'store.home11', 'store.home10', 'store.home8', 'store.home5', 'store.home6', 'store.home4'] as $c) { try { \Cache::forget($c); } catch (\Throwable $e) {} }
      try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
