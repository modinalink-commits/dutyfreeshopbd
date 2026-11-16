<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/** Owner 17 Nov 2026: official hotline is now 09644999000. Only the central "Hotline" setting is changed here (the pages, messages, invoices and schema read it). WhatsApp, mobile, PBX and sender numbers are not touched. down() puts the previous value back. */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    $cur = DB::table('settings')->where('key', 'call_number')->value('value');
    DB::table('settings')->updateOrInsert(['key' => 'hotline_previous'], ['value' => (string)$cur, 'updated_at' => now(), 'created_at' => now()]);
    DB::table('settings')->updateOrInsert(['key' => 'call_number'], ['value' => '09644999000', 'updated_at' => now(), 'created_at' => now()]);
    foreach (['store.settings2', 'ai.facts', 'store.home6', 'store.home4', 'store.home5'] as $c) { try { Cache::forget($c); } catch (\Throwable $e) {} }
  }
  public function down(): void {
    $prev = DB::table('settings')->where('key', 'hotline_previous')->value('value');
    if ($prev) DB::table('settings')->where('key', 'call_number')->update(['value' => $prev, 'updated_at' => now()]);
  }
};
