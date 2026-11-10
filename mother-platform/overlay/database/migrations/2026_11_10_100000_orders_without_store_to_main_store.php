<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Orders that carry no (or an unknown) Store are filed under the main store (DutyFreeBD), the same default the order forms use. Only touches rows where store_id is empty/invalid; safe to run again. */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('orders') || !Schema::hasTable('stores')) return;
      $main = DB::table('stores')->whereNotIn('id', DB::table('watch_source')->whereNotNull('store_id')->select('store_id'))->orderBy('id')->value('id') ?: DB::table('stores')->orderBy('id')->value('id');
      if (!$main) return;
      $n = DB::table('orders')->where(fn($q) => $q->whereNull('store_id')->orWhereNotIn('store_id', DB::table('stores')->select('id')))->update(['store_id' => $main]);
      if ($n) \Illuminate\Support\Facades\Log::info('orders without store moved to store '.$main.': '.$n);
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
