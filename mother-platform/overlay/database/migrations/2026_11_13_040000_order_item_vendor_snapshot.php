<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Owner (13 Nov 2026): sourcing products are bought from other shops. Every NEW order line remembers which source shop
 * (vendor), which product link, the vendor price and SKU it was sold from - hidden from customers, shown to staff only.
 * A database trigger fills it from watch_source (no order code touched); old lines are back-filled once.
 */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('order_items') || !Schema::hasTable('watch_source')) return;
      Schema::table('order_items', function (Blueprint $t) {
        if (!Schema::hasColumn('order_items', 'src_store_id')) $t->unsignedBigInteger('src_store_id')->nullable()->index();
        if (!Schema::hasColumn('order_items', 'src_store')) $t->string('src_store', 160)->nullable();
        if (!Schema::hasColumn('order_items', 'src_url')) $t->string('src_url', 600)->nullable();
        if (!Schema::hasColumn('order_items', 'src_sku')) $t->string('src_sku', 120)->nullable();
        if (!Schema::hasColumn('order_items', 'src_price')) $t->decimal('src_price', 12, 2)->nullable();
        if (!Schema::hasColumn('order_items', 'vendor_status')) $t->string('vendor_status', 20)->nullable()->index(); // null = to order, ordered, shipped, received
        if (!Schema::hasColumn('order_items', 'vendor_ref')) $t->string('vendor_ref', 160)->nullable();
      });
      if (DB::getDriverName() !== 'pgsql') return;
      DB::unprepared("update order_items i set src_store_id = w.store_id, src_store = s.name, src_url = w.source_url, src_sku = w.source_sku, src_price = w.source_price from watch_source w left join stores s on s.id = w.store_id where w.product_id = i.product_id and i.src_store_id is null");
      DB::unprepared("create or replace function df_order_item_vendor() returns trigger as \$\$ begin
        if new.src_store_id is null and new.product_id is not null then
          select w.store_id, s.name, w.source_url, w.source_sku, w.source_price into new.src_store_id, new.src_store, new.src_url, new.src_sku, new.src_price
          from watch_source w left join stores s on s.id = w.store_id where w.product_id = new.product_id order by w.id limit 1;
        end if; return new; end \$\$ language plpgsql");
      DB::unprepared("drop trigger if exists df_order_item_vendor on order_items");
      DB::unprepared("create trigger df_order_item_vendor before insert on order_items for each row execute function df_order_item_vendor()");
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {
    if (DB::getDriverName() === 'pgsql') { DB::unprepared("drop trigger if exists df_order_item_vendor on order_items"); DB::unprepared("drop function if exists df_order_item_vendor()"); }
  }
};
