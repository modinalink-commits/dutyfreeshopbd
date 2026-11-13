<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/**
 * Speed (site audit 14 Nov 2026), measured with the admin ?_perf=1 probe:
 *  - Orders list: 770 ms in the order-lines query, because the image fallback looks a product up by NAME with no index.
 *  - Dashboard: 2,048 ms in "Top viewed products", whose per-product sub-queries scan the view events and order lines.
 *  - Win-back: several 170-260 ms scans grouping orders by the last 11 digits of the phone.
 * Indexes only: no data and no query is changed. Each one is created separately so one failure cannot stop the others.
 */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    if (DB::getDriverName() !== 'pgsql') return;
    $run = function (string $sql) { try { DB::statement($sql); } catch (\Throwable $e) { report($e); } };
    if (Schema::hasTable('products')) $run('create index if not exists products_name_idx on products (name)');
    if (Schema::hasTable('product_analytics_events')) $run("create index if not exists pae_view_product_time_idx on product_analytics_events (product_id, created_at) where event_type = 'product_view'");
    if (Schema::hasTable('order_items')) $run('create index if not exists order_items_product_id_idx on order_items (product_id)');
    if (Schema::hasTable('orders')) $run('create index if not exists orders_phone11_idx on orders ((right(phone, 11)))');
  }
  public function down(): void {
    if (DB::getDriverName() !== 'pgsql') return;
    foreach (['products_name_idx', 'pae_view_product_time_idx', 'order_items_product_id_idx', 'orders_phone11_idx'] as $i) { try { DB::statement('drop index if exists '.$i); } catch (\Throwable $e) {} }
  }
};
