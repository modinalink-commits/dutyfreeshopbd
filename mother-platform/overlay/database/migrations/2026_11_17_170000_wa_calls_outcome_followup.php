<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner 17 Nov 2026: a WhatsApp call gets a result and a follow-up time, like a PBX call. Additive only. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('wa_calls')) return;
    Schema::table('wa_calls', function (Blueprint $t) {
      if (!Schema::hasColumn('wa_calls', 'outcome')) $t->string('outcome', 30)->nullable();
      if (!Schema::hasColumn('wa_calls', 'followup_at')) $t->timestamp('followup_at')->nullable()->index();
      if (!Schema::hasColumn('wa_calls', 'followup_done_at')) $t->timestamp('followup_done_at')->nullable();
    });
  }
  public function down(): void {
    if (!Schema::hasTable('wa_calls')) return;
    Schema::table('wa_calls', function (Blueprint $t) { foreach (['followup_done_at', 'followup_at', 'outcome'] as $c) if (Schema::hasColumn('wa_calls', $c)) $t->dropColumn($c); });
  }
};
