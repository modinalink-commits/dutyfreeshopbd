<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

/**
 * Owner (14 Nov): the "REGULAR CIGARETTE" sub-category of Cigarette & Vape is not wanted.
 * Its products stay in the main category (sub cleared); the node is removed. Old values are saved in category_cleanup_backup (kind sub_rm).
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('category_nodes') || !Schema::hasTable('products')) return;
    try {
      if (!Schema::hasTable('category_cleanup_backup')) DB::unprepared("create table category_cleanup_backup (kind varchar(10), ref_id bigint, old_value text, created_at timestamp default now())");
      foreach (DB::table('category_nodes')->where('level', 2)->where('slug', 'regular-cigarette')->get() as $n) {
        if (stripos((string)$n->name, 'REGULAR CIGARETTE') === false) continue;
        if (DB::table('category_nodes')->where('parent_id', $n->id)->exists()) continue;
        DB::transaction(function () use ($n) {
          $ids = DB::table('products')->where('category_id', $n->category_id)->where('sub', $n->name)->pluck('id')->all();
          DB::table('category_cleanup_backup')->insert(['kind' => 'sub_rm', 'ref_id' => $n->id, 'old_value' => json_encode(['name' => $n->name, 'category_id' => $n->category_id, 'position' => $n->position, 'products' => $ids], JSON_UNESCAPED_UNICODE)]);
          if ($ids) DB::table('products')->whereIn('id', $ids)->update(['sub' => null, 'sub_sub' => null, 'updated_at' => now()]);
          DB::table('category_nodes')->where('id', $n->id)->delete();
        });
      }
      foreach (['store.cats', 'store.subs', 'store.catslugs', 'store.home13', 'store.sitemap5'] as $k) Cache::forget($k);
      try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
