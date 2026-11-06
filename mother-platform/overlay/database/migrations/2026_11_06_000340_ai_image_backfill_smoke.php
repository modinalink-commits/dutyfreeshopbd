<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner decision: clean every Cigarette & Vape product image (main + gallery) first. Other categories wait. */
return new class extends Migration {
  public function up(): void { DB::table('settings')->updateOrInsert(['key' => 'ai_auto_backfill_all'], ['value' => '1', 'updated_at' => now(), 'created_at' => now()]); }
  public function down(): void {}
};
