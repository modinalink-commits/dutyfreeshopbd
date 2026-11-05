<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Report Center: which export formats a report page has built in (native_export), plus each person's favorite and recently viewed reports. */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    try {
      if (Schema::hasTable('report_registry') && !Schema::hasColumn('report_registry', 'native_export')) Schema::table('report_registry', function (Blueprint $t) { $t->string('native_export', 8)->default(''); });
      if (!Schema::hasTable('report_favorites')) Schema::create('report_favorites', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('user_id'); $t->string('report_code', 12); $t->timestamp('created_at')->nullable();
        $t->unique(['user_id', 'report_code']);
      });
      if (!Schema::hasTable('report_views')) Schema::create('report_views', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('user_id'); $t->string('report_code', 12); $t->timestamp('viewed_at')->nullable()->index();
        $t->unique(['user_id', 'report_code']);
      });
    } catch (\Throwable $e) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Report Center extras migration failed', 'subject' => mb_substr($e->getMessage(), 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e2) {}
    }
  }
  public function down(): void {}
};
