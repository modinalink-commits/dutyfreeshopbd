<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\ProfitFloor;
// Owner (8 Nov 2026): products that were Below Minimum should only be lifted to Near Minimum (just above the protected floor), not all the way to Safe. Products that were Near stay at Safe.
return new class extends Migration {
    public function up(): void {
        try {
            if (!Schema::hasTable('product_price_history')) return;
            $n = 0;
            foreach (DB::table('product_price_history')->where('source', 'Bring to Safe')->orderBy('id')->get() as $h) {
                $cost = (float)$h->purchase_rate; if ($cost <= 0) continue;
                $floor = ProfitFloor::floor($cost); if ((float)$h->old_rate + 0.004 >= $floor) continue; // was Near, not Below
                $p = DB::table('products')->where('id', $h->product_id)->first(['id', 'price']); if (!$p || abs((float)$p->price - (float)$h->new_rate) > 0.004) continue; // someone changed it since
                $new = max((float)$h->old_rate, $floor); if ($new >= (float)$p->price - 0.004) continue;
                DB::table('products')->where('id', $p->id)->update(['price' => $new, 'updated_at' => now()]);
                DB::table('product_price_history')->insert(['product_id' => $p->id, 'old_rate' => $p->price, 'new_rate' => $new, 'purchase_rate' => $cost, 'changed_by' => 'System (owner: Below -> Near)', 'source' => 'Below to Near', 'note' => 'Owner request 8 Nov 2026: Below Minimum lifted only to Near Minimum', 'created_at' => now(), 'updated_at' => now()]);
                $n++;
            }
            \App\Support\BundlePricing::flush(); try { \App\Support\BundlePricing::sync(); } catch (\Throwable $e) { report($e); }
            try { ProfitFloor::audit('Below to Near', 'Prices set to Near Minimum', null, ['changed' => $n]); } catch (\Throwable $e) {}
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
