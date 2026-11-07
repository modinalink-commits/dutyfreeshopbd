<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
/** Owner: the official WhatsApp number is 01871600600 everywhere (replaces 01872404040). */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    $new = '01871600600';
    if (DB::table('settings')->where('key', 'whatsapp_number')->exists()) DB::table('settings')->where('key', 'whatsapp_number')->update(['value' => $new, 'updated_at' => now()]);
    else DB::table('settings')->insert(['key' => 'whatsapp_number', 'value' => $new, 'created_at' => now(), 'updated_at' => now()]);
    // any other saved setting/page text that still holds the old number
    $rep = ['8801872404040' => '8801871600600', '+8801872404040' => '+8801871600600', '01872-404040' => '01871-600600', '01872404040' => $new, '1872404040' => '1871600600'];
    foreach (DB::table('settings')->where('value', 'like', '%872404040%')->get(['key', 'value']) as $r) DB::table('settings')->where('key', $r->key)->update(['value' => strtr((string)$r->value, $rep), 'updated_at' => now()]);
    foreach (['pages' => ['body', 'meta_description'], 'products' => ['description'], 'posts' => ['body']] as $t => $cols) {
      if (!Schema::hasTable($t)) continue;
      foreach ($cols as $c) { if (!Schema::hasColumn($t, $c)) continue;
        foreach (DB::table($t)->where($c, 'like', '%872404040%')->get(['id', $c]) as $r) DB::table($t)->where('id', $r->id)->update([$c => strtr((string)$r->$c, $rep)]);
      }
    }
    foreach (['store.settings2', 'ai.facts', 'store.home6', 'store.home4', 'store.home5', 'store.catalogfeed9'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
  }
  public function down(): void {}
};
