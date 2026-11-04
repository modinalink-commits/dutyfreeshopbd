<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Adds the animated Manchester range banner as the first Main Slider slide (image = poster; the .mp4 twin in /public/banners plays on top). Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sliders')) return;
    $img = '/banners/manchester-anim.webp';
    if (DB::table('sliders')->where('image', $img)->exists()) return;
    $min = DB::table('sliders')->where(fn($q) => $q->whereNull('type')->orWhere('type', 'main'))->min('position');
    $row = ['image' => $img, 'active' => true, 'position' => ($min === null ? 1 : (int)$min - 1), 'created_at' => now(), 'updated_at' => now()];
    foreach (['type' => 'main', 'media_type' => 'image', 'title' => null] as $c => $v) if (Schema::hasColumn('sliders', $c)) $row[$c] = $v;
    DB::table('sliders')->insert($row);
  }
  public function down(): void {}
};
