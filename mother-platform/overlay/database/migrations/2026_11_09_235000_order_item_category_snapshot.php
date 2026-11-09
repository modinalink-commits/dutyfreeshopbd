<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Category Wise Profit: a returned or re-categorised product must keep the category it was sold in.
 * order_items gets cat_id / cat_sub / cat_sub_sub. A database trigger fills them for every NEW order line
 * (no order code is touched). Existing lines are filled once from the product's current category; from today on
 * a later category change no longer rewrites old results.
 */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_items') || !Schema::hasTable('products')) return;
    Schema::table('order_items', function (Blueprint $t) {
      if (!Schema::hasColumn('order_items', 'cat_id')) $t->unsignedBigInteger('cat_id')->nullable()->index();
      if (!Schema::hasColumn('order_items', 'cat_sub')) $t->string('cat_sub', 160)->nullable();
      if (!Schema::hasColumn('order_items', 'cat_sub_sub')) $t->string('cat_sub_sub', 160)->nullable();
    });
    if (DB::getDriverName() !== 'pgsql') return;
    DB::unprepared("update order_items i set cat_id = p.category_id, cat_sub = p.sub, cat_sub_sub = p.sub_sub from products p where p.id = i.product_id and i.cat_id is null");
    DB::unprepared("create or replace function df_order_item_cat_snapshot() returns trigger as \$\$ begin
      if new.cat_id is null and new.product_id is not null then
        select category_id, sub, sub_sub into new.cat_id, new.cat_sub, new.cat_sub_sub from products where id = new.product_id;
      end if; return new; end \$\$ language plpgsql");
    DB::unprepared("drop trigger if exists df_order_item_cat_snapshot on order_items");
    DB::unprepared("create trigger df_order_item_cat_snapshot before insert on order_items for each row execute function df_order_item_cat_snapshot()");
    // helps the report: orders are filtered by date, lines are joined by order
    DB::unprepared("create index if not exists orders_created_status_idx on orders (created_at, status)");
  }
  public function down(): void {
    if (DB::getDriverName() === 'pgsql') {
      DB::unprepared("drop trigger if exists df_order_item_cat_snapshot on order_items");
      DB::unprepared("drop function if exists df_order_item_cat_snapshot()");
      DB::unprepared("drop index if exists orders_created_status_idx");
    }
    if (Schema::hasTable('order_items')) Schema::table('order_items', function (Blueprint $t) {
      foreach (['cat_id', 'cat_sub', 'cat_sub_sub'] as $c) if (Schema::hasColumn('order_items', $c)) $t->dropColumn($c);
    });
  }
};
