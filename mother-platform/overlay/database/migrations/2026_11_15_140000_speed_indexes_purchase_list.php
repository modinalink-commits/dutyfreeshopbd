<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Speed audit 15 Nov 2026: Purchase Daily List took 2.9 s in the database (correlated max(id) per product on purchase_items, order_items by product). Additive indexes only (IF NOT EXISTS, columns checked, failures ignored). No data is changed. down() drops only these. */
return new class extends Migration {
  private function ix(): array { return [
    ['purchase_items', 'product_cost_id', ['product_id', 'id']],
    ['purchase_items', 'purchase_id', ['purchase_id']],
    ['order_items', 'product_order', ['product_id', 'order_id']],
    ['purchases', 'supplier_id', ['supplier_id']],
  ]; }
  public function up(): void {
    foreach ($this->ix() as [$t, $n, $cols]) {
      try {
        if (!Schema::hasTable($t)) continue;
        foreach ($cols as $c) if (!Schema::hasColumn($t, $c)) continue 2;
        DB::statement('create index if not exists ' . $t . '_' . $n . '_spd2 on ' . $t . ' (' . implode(',', array_map(fn($c) => '"' . $c . '"', $cols)) . ')');
      } catch (\Throwable $e) { report($e); }
    }
  }
  public function down(): void {
    foreach ($this->ix() as [$t, $n]) { try { DB::statement('drop index if exists ' . $t . '_' . $n . '_spd2'); } catch (\Throwable $e) {} }
  }
};
