<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Report Center, phase 1: a permanent registry of every report that exists today. Nothing is removed or changed in the existing reports;
 * the table only lists them (code, group, route, export support). The rows are filled by App\Support\ReportCatalog::sync().
 */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    try {
      if (!Schema::hasTable('report_registry')) Schema::create('report_registry', function (Blueprint $t) {
        $t->id();
        $t->string('report_code', 12)->unique();
        $t->string('name', 160);
        $t->string('slug', 120)->unique();
        $t->text('description')->nullable();
        $t->string('group_code', 3)->index();
        $t->string('sub_group', 80)->nullable();
        $t->string('report_type', 20)->default('grid'); // grid | document | dashboard (internal only, never shown to staff)
        $t->string('data_source', 160)->nullable();
        $t->string('route_path', 200)->index();         // e.g. admin/analytics/sales-summary or admin/stock-movement-report?mode=damage
        $t->string('permission_key', 40)->nullable();
        $t->boolean('supports_pdf')->default(false); $t->boolean('supports_xlsx')->default(false); $t->boolean('supports_csv')->default(false);
        $t->boolean('supports_docx')->default(false); $t->boolean('supports_print')->default(false);
        $t->boolean('is_active')->default(true); $t->boolean('is_legacy')->default(false); $t->boolean('is_hidden_from_sidebar')->default(false);
        $t->unsignedBigInteger('replacement_report_id')->nullable();
        $t->string('duplicate_group', 60)->nullable()->index(); // reports that MAY do the same job; needs a six-field review before anything is marked Legacy
        $t->string('owner_module', 60)->nullable();
        $t->unsignedInteger('sort_order')->default(0);
        $t->timestamps();
      });
    } catch (\Throwable $e) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'Report registry table failed', 'subject' => mb_substr($e->getMessage(), 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e2) {}
    }
  }
  public function down(): void {}
};
