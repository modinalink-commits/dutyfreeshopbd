<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    DB::statement('create index if not exists order_items_product_order on order_items (product_id, order_id)');
    DB::statement('analyze order_items');
  }
  public function down(): void { DB::statement('drop index if exists order_items_product_order'); }
};
