<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner asked to switch Win-Back ON. First sends start tomorrow (2026-11-04, 2-5 PM Dhaka), max 250 per day. */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    $put = function ($k, $v) { DB::table('settings')->updateOrInsert(['key' => 'wb_' . $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); };
    $put('start', '2026-11-04'); $put('daily_cap', '250'); $put('on', '1');
  }
  public function down(): void {}
};
