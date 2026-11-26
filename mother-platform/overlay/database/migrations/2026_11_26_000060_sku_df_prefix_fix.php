<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner's instruction (2026-11-26): some Cigarette and Vape product codes had no "DF-" prefix (plain number like 2924, or 1035-SP). Add "DF-" so every code matches the rest.
 *  A code is only changed when the new code is not already used by another product. Old codes are saved in settings (sku_df_prefix_backup) and in the audit log. Runs once. */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    try {
      if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'sku')) return;
      if (DB::table('settings')->where('key', 'sku_df_prefix_20261126')->exists()) return;
      $done = []; $skip = [];
      foreach (DB::table('products')->whereRaw("sku ~ '^[0-9]+(-SP)?$'")->orderBy('id')->get(['id', 'sku']) as $p) {
        try {
          $new = 'DF-'.$p->sku;
          if (DB::table('products')->where('sku', $new)->where('id', '!=', $p->id)->exists()) { $skip[] = $p->sku; continue; }
          DB::table('products')->where('id', $p->id)->update(['sku' => $new, 'updated_at' => now()]);
          $done[$p->id] = $p->sku;
        } catch (\Throwable $e) { $skip[] = $p->sku; }
      }
      DB::table('settings')->updateOrInsert(['key' => 'sku_df_prefix_backup'], ['value' => json_encode($done), 'updated_at' => now(), 'created_at' => now()]);
      DB::table('settings')->updateOrInsert(['key' => 'sku_df_prefix_20261126'], ['value' => (string)count($done), 'updated_at' => now(), 'created_at' => now()]);
      DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Product code changed', 'subject' => count($done).' product codes got the DF- prefix (owner instruction): '.implode(', ', $done).($skip ? ' | skipped (already used): '.implode(', ', $skip) : ''),
        'meta' => json_encode(['count' => count($done)], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
