<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Orders for our own products were tagged with an affiliate source shop (luxotix, amour, ryans) because the checkout took "the first store row". Put them back on the main store; orders that really contain an affiliate product keep their shop. */
return new class extends Migration {
  public function up(): void {
    try {
      $aff = DB::table('watch_source')->whereNotNull('store_id')->distinct()->pluck('store_id')->all(); if (!$aff) return;
      $main = DB::table('stores')->whereNotIn('id', $aff)->orderBy('id')->value('id'); if (!$main) return;
      $in = implode(',', array_map('intval', $aff));
      DB::statement("update orders set store_id = ? where store_id in ($in) and not exists (select 1 from order_items i join watch_source w on w.product_id = i.product_id where i.order_id = orders.id)", [$main]);
      foreach (['dash.srcstores.v1'] as $k) { try { \Cache::forget($k); } catch (\Throwable $e) {} }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
