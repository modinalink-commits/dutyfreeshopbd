<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\ProfitFloor;
use App\Support\Costing;
// Owner (8 Nov 2026): every product at Near Minimum / Below Minimum is raised by the smallest amount that makes it Safe (FIFO cost x (1 + min% + 5%), rounded up to whole Taka). Old price kept in the price history; bundle prices re-synced.
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasTable('product_price_history')) return;
            $pct = ProfitFloor::percent(); $mult = 1 + ($pct + 5) / 100; $by = 'System (owner: bring to Safe)'; $n = 0; $log = [];
            $rows = DB::table('products as p')->selectRaw('p.id, p.price, ' . Costing::fifoSql('p') . ' as fifo')->where('p.price', '>', 0)->get();
            foreach ($rows as $x) {
                $cost = (float)$x->fifo; $pr = (float)$x->price; if ($cost <= 0) continue;
                [$lab] = ProfitFloor::state($cost, $pr); if (!str_contains($lab, 'Near') && !str_contains($lab, 'Below')) continue;
                $new = ceil($cost * $mult - 0.000001); if ($new <= $pr + 0.004) continue;
                DB::table('products')->where('id', $x->id)->update(['price' => $new, 'updated_at' => now()]);
                DB::table('product_price_history')->insert(['product_id' => $x->id, 'old_rate' => $pr, 'new_rate' => $new, 'purchase_rate' => $cost, 'changed_by' => $by, 'source' => 'Bring to Safe', 'note' => 'Owner request 8 Nov 2026: Near/Below Minimum -> Safe', 'created_at' => now(), 'updated_at' => now()]);
                $n++; if (count($log) < 30) $log[] = [$x->id, $pr, $new];
            }
            \App\Support\BundlePricing::flush(); try { \App\Support\BundlePricing::sync(); } catch (\Throwable $e) { report($e); }
            try { ProfitFloor::audit('Bring to Safe', 'Prices raised to Safe', null, ['changed' => $n, 'sample' => $log]); } catch (\Throwable $e) {}
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
