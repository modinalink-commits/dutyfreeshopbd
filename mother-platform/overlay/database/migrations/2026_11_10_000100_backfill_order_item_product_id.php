<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Old order lines that lost their product link (order_items.product_id is null) get the real product id,
 * matched by exact, unique product name only (no guessing). The category snapshot is filled from that product.
 * The ids touched are kept in order_item_pid_backup so this can be rolled back.
 */
return new class extends Migration {
  public function up(): void {
    if (DB::getDriverName() !== 'pgsql' || !Schema::hasTable('order_items') || !Schema::hasTable('products')) return;
    if (!Schema::hasTable('order_item_pid_backup')) DB::unprepared("create table order_item_pid_backup (item_id bigint primary key, created_at timestamp default now())");
    $u = "(select min(id) as id, lower(trim(name)) as nm from products group by lower(trim(name)) having count(*) = 1)";
    DB::unprepared("insert into order_item_pid_backup (item_id) select i.id from order_items i join $u pn on pn.nm = lower(trim(i.name)) where i.product_id is null on conflict do nothing");
    DB::unprepared("update order_items i set product_id = pn.id from $u pn where i.product_id is null and pn.nm = lower(trim(i.name))");
    if (Schema::hasColumn('order_items', 'cat_id')) {
      DB::unprepared("update order_items i set cat_id = p.category_id, cat_sub = p.sub, cat_sub_sub = p.sub_sub from products p where p.id = i.product_id and i.cat_id is null");
    }
  }
  public function down(): void {
    if (DB::getDriverName() !== 'pgsql' || !Schema::hasTable('order_item_pid_backup')) return;
    DB::unprepared("update order_items set product_id = null where id in (select item_id from order_item_pid_backup)");
    DB::unprepared("drop table order_item_pid_backup");
  }
};
