<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Average purchase price (weighted average cost) per product. Stock bought at different rates is blended into ONE average; the 15% protected profit is counted on this average.
 *  Backfill: with stock on hand, the average of the latest received purchase lots that make up that stock; with no stock (or no purchase history) the last purchase price. Idempotent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'cost')) return;
    if (!Schema::hasColumn('products', 'avg_cost')) Schema::table('products', fn(Blueprint $t) => $t->decimal('avg_cost', 14, 4)->default(0));
    if (!Schema::hasTable('avg_cost_logs')) Schema::create('avg_cost_logs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->string('source', 40); $t->string('reference', 120)->nullable(); $t->integer('qty')->default(0);
      $t->decimal('unit_cost', 14, 4)->default(0); $t->decimal('old_avg', 14, 4)->default(0); $t->decimal('new_avg', 14, 4)->default(0);
      $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->timestamps();
    });
    $lots = Schema::hasTable('purchase_items') && Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'stock_applied');
    DB::table('products')->where(fn($q) => $q->whereNull('avg_cost')->orWhere('avg_cost', '<=', 0))->orderBy('id')->chunkById(500, function ($rows) use ($lots) {
      foreach ($rows as $p) {
        $avg = (float)$p->cost; $stock = (int)$p->stock;
        if ($lots && $stock > 0) {
          try {
            $need = $stock; $sum = 0.0; $got = 0;
            foreach (DB::table('purchase_items as i')->join('purchases as u', 'u.id', '=', 'i.purchase_id')->where('i.product_id', $p->id)->where('u.stock_applied', true)->where('i.cost', '>', 0)->orderByDesc('u.id')->get(['i.qty', 'i.damage_qty', 'i.cost']) as $l) {
              $q = max(0, (int)$l->qty - (int)$l->damage_qty); if ($q <= 0) continue; $t = min($q, $need); $sum += $t * (float)$l->cost; $got += $t; $need -= $t; if ($need <= 0) break;
            }
            if ($got > 0) $avg = $sum / $got;
          } catch (\Throwable $e) { report($e); }
        }
        if ($avg > 0) DB::table('products')->where('id', $p->id)->update(['avg_cost' => round($avg, 4)]);
      }
    });
  }
  public function down(): void {}
};
