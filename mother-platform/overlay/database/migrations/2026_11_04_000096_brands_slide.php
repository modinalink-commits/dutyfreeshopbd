<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Adds the animated "Our Premium Brands" logo slide right after the Manchester slide (image = poster; the .mp4 twin plays on top). Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sliders')) return;
    $img = '/banners/brands-anim.webp';
    if (DB::table('sliders')->where('image', $img)->exists()) return;
    $m = DB::table('sliders')->where('image', '/banners/manchester-anim.webp')->value('position');
    $row = ['image' => $img, 'active' => true, 'position' => ($m === null ? 1 : (int)$m + 1), 'created_at' => now(), 'updated_at' => now()];
    foreach (['type' => 'main', 'media_type' => 'image', 'title' => null] as $c => $v) if (Schema::hasColumn('sliders', $c)) $row[$c] = $v;
    DB::table('sliders')->insert($row);
  }
  public function down(): void {}
};
