<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Manchester + Our Brands hero slides: native 3:1 (desktop) and 16:9 (mobile) animations under new file names (so browsers/CDN never serve the old cached 2:1 videos). Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sliders')) return;
    $hasM = Schema::hasColumn('sliders', 'mobile_image');
    foreach (['manchester', 'brands'] as $n) {
      $row = ['image' => "/banners/$n-hero.webp", 'updated_at' => now()]; if ($hasM) $row['mobile_image'] = "/banners/$n-hero-m.webp";
      DB::table('sliders')->whereIn('image', ["/banners/$n-anim.webp", "/banners/$n-hero.webp"])->update($row);
    }
    \Illuminate\Support\Facades\Cache::forget('store.home8');
  }
  public function down(): void {}
};
