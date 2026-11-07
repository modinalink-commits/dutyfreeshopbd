<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// The old 555 / Dunhill offer banners were replaced by the showcase strip: keep them OFF (not deleted) so the home page looks the same as now.
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('settings')) return;
    $now = now();
    DB::table('settings')->updateOrInsert(['key' => 'home_offer_banners'], ['value' => '0', 'updated_at' => $now, 'created_at' => $now]);
    try { \Illuminate\Support\Facades\Cache::forget('store.settings2'); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
