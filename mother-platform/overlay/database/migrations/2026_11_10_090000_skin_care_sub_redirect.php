<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
/** The removed Daily Needs > Skin Care sub page now redirects (301) to the Skin & Hair Care category. */
return new class extends Migration {
  public function up(): void {
    try { if (Schema::hasTable('seo_redirects')) \App\Support\SeoMgr::addRedirect('/daily-needs/skin-care', '/skin-care', 301, 'cleanup'); } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {
    try { \Illuminate\Support\Facades\DB::table('seo_redirects')->where('from_path', '/daily-needs/skin-care')->where('source', 'cleanup')->delete(); } catch (\Throwable $e) {}
  }
};
