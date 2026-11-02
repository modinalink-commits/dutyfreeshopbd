<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner decision: Win-Back stages 15/25/35/45/55 days at 3/4/5/6/7 %, delivered as fixed per-stage coupon codes (WB-15 ...). */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    $put = function ($k, $v) { DB::table('settings')->updateOrInsert(['key' => 'wb_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); };
    $put('stages', json_encode([[15, 3], [25, 4], [35, 5], [45, 6], [55, 7]]));
    $put('mode', 'fixed');
    $put('expiry', '30');
    $max = DB::table('settings')->where('key', 'wb_max_pct')->value('value');
    if ($max !== null && (float)$max > 0 && (float)$max < 7) $put('max_pct', '100');
  }
  public function down(): void {}
};
