<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Owner 20 Nov 2026: the call-client relay is no longer used. Switch it off (no data is deleted; the client billing record stays). */
return new class extends Migration {
  public function up(): void { DB::table('settings')->updateOrInsert(['key' => 'vc_relay_off'], ['value' => '1']); }
  public function down(): void { DB::table('settings')->where('key', 'vc_relay_off')->delete(); }
};
