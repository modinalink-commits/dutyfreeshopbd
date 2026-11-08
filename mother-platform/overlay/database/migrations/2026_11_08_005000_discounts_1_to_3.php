<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\Procure;
// Owner (8 Nov 2026): every discount on the server stays within 1% - 3%. A rule value of 0 means "off" and stays 0; fixed-Taka amounts are not percentages and stay as they are.
return new class extends Migration {
    private function clamp(float $v): float { return $v <= 0 ? 0.0 : max(1.0, min(3.0, $v)); }
    public function up(): void {
        try {
            foreach (DB::table('discount_rules')->get() as $r) {
                $c = json_decode((string)$r->config, true); if (!is_array($c)) continue; $ch = false;
                foreach ($c as $k => $v) { if (!is_numeric($v) || !str_contains((string)$k, '%')) continue; $n = round($this->clamp((float)$v), 2); if (abs($n - (float)$v) > 0.0001) { $c[$k] = $n; $ch = true; } }
                if ($r->key === 'amount') { $t = ['Over 5000 TK %' => 1, 'Over 10000 TK %' => 1.5, 'Over 20000 TK %' => 2]; foreach ($t as $k => $v) if (isset($c[$k]) && (float)$c[$k] != $v) { $c[$k] = $v; $ch = true; } }
                if ($ch) DB::table('discount_rules')->where('id', $r->id)->update(['config' => json_encode($c, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            }
            foreach ([2 => 1.5, 3 => 2, 4 => 2.5, 5 => 3] as $n => $p) Procure::put("bundle_pct_$n", (string)$p);
            Procure::put('bundle_rule_version', (string)(max(1, (int)Procure::set('bundle_rule_version', '1')) + 1));
            \App\Support\BundlePricing::flush(); try { \App\Support\BundlePricing::sync(); } catch (\Throwable $e) { report($e); }
            try { \App\Support\Winback::put('max_pct', '3'); } catch (\Throwable $e) {}
            if (Schema::hasTable('voice_campaign_customers') && Schema::hasColumn('voice_campaign_customers', 'discount_value')) {
                DB::table('voice_campaign_customers')->where('discount_value', '>', 0)->where('discount_value', '<', 1)->update(['discount_value' => 1]);
                DB::table('voice_campaign_customers')->where('discount_value', '>', 3)->update(['discount_value' => 3]);
            }
            try { \App\Support\ProfitFloor::audit('Discounts 1-3%', 'All discount rules clamped to 1-3%', null, ['by' => 'owner request 8 Nov 2026']); } catch (\Throwable $e) {}
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
