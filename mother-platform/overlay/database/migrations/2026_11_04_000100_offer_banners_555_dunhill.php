<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Replaces the two promo banners under the hero with the animated 555 Mandarin and Dunhill International banners (webp poster; the .mp4 twin plays on top). Old offer banners are only switched off, never deleted. Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sliders')) return;
    $new = [['/banners/offer-555.webp', '/search?q=555'], ['/banners/offer-dunhill.webp', '/search?q=dunhill']];
    $has = fn($c) => Schema::hasColumn('sliders', $c);
    $q = DB::table('sliders'); if ($has('type')) $q->where('type', 'offer');
    $min = (int)($q->min('position') ?? 1);
    $old = DB::table('sliders'); if ($has('type')) $old->where('type', 'offer');
    $old->whereNotIn('image', array_column($new, 0))->update(['active' => false, 'updated_at' => now()]);
    foreach ($new as $i => [$img, $link]) {
      $row = ['image' => $img, 'link' => $link, 'active' => true, 'position' => $min - 2 + $i, 'updated_at' => now()];
      foreach (['type' => 'offer', 'media_type' => 'image', 'title' => null] as $c => $v) if ($has($c)) $row[$c] = $v;
      if (DB::table('sliders')->where('image', $img)->exists()) { DB::table('sliders')->where('image', $img)->update($row); continue; }
      DB::table('sliders')->insert($row + ['created_at' => now()]);
    }
    \Illuminate\Support\Facades\Cache::forget('store.home8');
  }
  public function down(): void {}
};
