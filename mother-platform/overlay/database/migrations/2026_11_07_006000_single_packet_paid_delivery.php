<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner rule: the "Single Packet" category never gets free delivery (normal zone charge always applies). Adds an "Always charge" override for it. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('delivery_overrides') || !Schema::hasTable('categories')) return;
    foreach (DB::table('categories')->where('name', 'ilike', 'single packet%')->get(['id', 'name']) as $c) {
      if (DB::table('delivery_overrides')->where('kind', 'category')->where('ref_id', $c->id)->where('mode', 'paid')->exists()) continue;
      DB::table('delivery_overrides')->insert(['kind' => 'category', 'ref_id' => $c->id, 'ref_name' => $c->name, 'mode' => 'paid', 'charge' => null, 'label' => 'Single Packet: delivery charge applies', 'starts_at' => null, 'ends_at' => null, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    try { \App\Support\Delivery::flush(); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
