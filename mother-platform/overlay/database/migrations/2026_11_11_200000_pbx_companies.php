<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extra PBX companies: each company has its own PBX details and its own list of extensions (pbx_extensions.company_id; 0 = the main DutyFreeBD PBX). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('pbx_companies')) {
      Schema::create('pbx_companies', function (Blueprint $t) {
        $t->id(); $t->string('name', 120); $t->string('sip_server', 255)->nullable(); $t->string('wss_url', 400)->nullable(); $t->string('domain', 255)->nullable(); $t->timestamps();
      });
    }
    if (Schema::hasTable('pbx_extensions') && !Schema::hasColumn('pbx_extensions', 'company_id')) {
      Schema::table('pbx_extensions', function (Blueprint $t) { $t->unsignedBigInteger('company_id')->default(0)->index(); });
      try { Schema::table('pbx_extensions', function (Blueprint $t) { $t->dropUnique(['extension']); }); } catch (\Throwable $e) {}
      Schema::table('pbx_extensions', function (Blueprint $t) { $t->unique(['company_id', 'extension']); });
    }
  }
  public function down(): void {
    if (Schema::hasTable('pbx_extensions') && Schema::hasColumn('pbx_extensions', 'company_id')) {
      \Illuminate\Support\Facades\DB::table('pbx_extensions')->where('company_id', '>', 0)->delete();
      try { Schema::table('pbx_extensions', function (Blueprint $t) { $t->dropUnique(['company_id', 'extension']); }); } catch (\Throwable $e) {}
      Schema::table('pbx_extensions', function (Blueprint $t) { $t->dropIndex(['company_id']); $t->dropColumn('company_id'); });
      try { Schema::table('pbx_extensions', function (Blueprint $t) { $t->unique('extension'); }); } catch (\Throwable $e) {}
    }
    Schema::dropIfExists('pbx_companies');
  }
};
