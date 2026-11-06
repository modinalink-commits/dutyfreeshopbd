<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner decision (6 Nov 2026): finish every Cigarette & Vape image today. Daily cap raised to 600 so the remaining products can finish; one image per ~30s tick. */
return new class extends Migration {
  public function up(): void { DB::table('settings')->updateOrInsert(['key' => 'ai_auto_daily_cap'], ['value' => '600', 'updated_at' => now(), 'created_at' => now()]); }
  public function down(): void {}
};
