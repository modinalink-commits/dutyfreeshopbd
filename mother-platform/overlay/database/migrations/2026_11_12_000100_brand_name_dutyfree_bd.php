<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner decision: the brand name is "DutyFree BD" everywhere (was "DutyFree Shop BD"). Rewrites stored text only in content tables. */
return new class extends Migration {
  public function up(): void {
    foreach (['products','categories','category_nodes','brands','pages','posts','settings','chat_quick_replies','seo_items'] as $tb) {
      if (!Schema::hasTable($tb)) continue;
      $cols = DB::table('information_schema.columns')->where('table_schema', DB::raw('current_schema()'))->where('table_name', $tb)
        ->whereIn('data_type', ['character varying','text'])->pluck('column_name');
      foreach ($cols as $c) {
        try { DB::statement('UPDATE "'.$tb.'" SET "'.$c.'" = REPLACE("'.$c.'", ?, ?) WHERE "'.$c.'" LIKE ?', ['DutyFree Shop BD', 'DutyFree BD', '%DutyFree Shop BD%']); } catch (\Throwable $e) { report($e); }
      }
    }
    try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {}
  }
  public function down(): void {}
};
