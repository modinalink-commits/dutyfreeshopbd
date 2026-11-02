<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner: Win-Back discounts spread evenly from 3% to 5% (6-7% risks a loss). */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    DB::table('settings')->updateOrInsert(['key' => 'wb_stages'], ['value' => json_encode([[15, 3], [25, 3.5], [35, 4], [45, 4.5], [55, 5]]), 'updated_at' => now(), 'created_at' => now()]);
  }
  public function down(): void {}
};
