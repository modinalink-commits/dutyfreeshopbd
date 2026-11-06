<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner request: old AI images that still carry the shop logo are redone with the new clean standard. prompt_cutoff = every image processed before now counts as "old". */
return new class extends Migration {
  public function up(): void {
    $put = fn($k, $v) => DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]);
    if (!DB::table('settings')->where('key', 'ai_auto_prompt_cutoff')->exists()) $put('ai_auto_prompt_cutoff', now()->toDateTimeString());
    foreach (['ai_auto_backfill' => '1', 'ai_auto_backfill_all' => '0'] as $k => $v) if (!DB::table('settings')->where('key', $k)->exists()) $put($k, $v);
  }
  public function down(): void {}
};
