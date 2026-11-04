<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Manchester + Our Brands hero slides: native 3:1 (desktop) and 16:9 (mobile) animations. Sets the mobile poster; the -m.mp4 twin is picked up automatically. Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sliders') || !Schema::hasColumn('sliders', 'mobile_image')) return;
    foreach (['manchester-anim', 'brands-anim'] as $n) {
      DB::table('sliders')->where('image', "/banners/$n.webp")->update(['mobile_image' => "/banners/$n-m.webp", 'updated_at' => now()]);
    }
    \Illuminate\Support\Facades\Cache::forget('store.home8');
  }
  public function down(): void {}
};
