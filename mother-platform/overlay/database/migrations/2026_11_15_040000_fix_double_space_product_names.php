<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Data audit 15 Nov 2026: 4 product names had a double space (Marlboro White  Menthol, ELFBAR Moon Night Watermelon Ice  | ... and their Single Packets). Only the extra spaces are removed; old names are kept in name_fix_backup. Slug, SKU, price, stock are not touched. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('name_fix_backup')) {
      Schema::create('name_fix_backup', function ($t) { $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->text('old_name'); $t->timestamp('fixed_at')->useCurrent(); });
    }
    $rows = DB::select("select id, name from products where id in (1412,1788,36472,36627) and name <> regexp_replace(trim(name), '\\s+', ' ', 'g')");
    foreach ($rows as $r) {
      DB::table('name_fix_backup')->insert(['product_id' => $r->id, 'old_name' => $r->name]);
      DB::update("update products set name = regexp_replace(trim(name), '\\s+', ' ', 'g') where id = ?", [$r->id]);
    }
  }
  public function down(): void {
    if (!Schema::hasTable('name_fix_backup')) return;
    foreach (DB::table('name_fix_backup')->get() as $b) DB::table('products')->where('id', $b->product_id)->update(['name' => $b->old_name]);
  }
};
