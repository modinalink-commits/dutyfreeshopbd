<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner rule: the "Single Packet" section (inside Cigarette and Vape) never gets free delivery; the normal zone charge applies. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('delivery_overrides') || !Schema::hasTable('products') || !Schema::hasColumn('products', 'sub')) return;
    foreach (DB::table('products')->where('sub', 'ilike', 'single packet')->whereNotNull('category_id')->distinct()->pluck('category_id') as $cid) {
      if (DB::table('delivery_overrides')->where('kind', 'section')->where('ref_id', $cid)->where('ref_name', 'Single Packet')->where('mode', 'paid')->exists()) continue;
      DB::table('delivery_overrides')->insert(['kind' => 'section', 'ref_id' => $cid, 'ref_name' => 'Single Packet', 'mode' => 'paid', 'charge' => null, 'label' => 'Single Packet: delivery charge applies', 'starts_at' => null, 'ends_at' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    try { \App\Support\Delivery::flush(); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
