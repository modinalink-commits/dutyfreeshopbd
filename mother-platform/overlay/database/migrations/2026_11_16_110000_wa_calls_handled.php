<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Owner 16 Nov 2026: a missed WhatsApp call stays on the callback list until a representative marks it handled. Additive (two nullable columns); down() drops only them. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('wa_calls') && !Schema::hasColumn('wa_calls', 'handled_at')) Schema::table('wa_calls', function (Blueprint $t) { $t->timestamp('handled_at')->nullable(); $t->string('handled_by', 120)->nullable(); });
  }
  public function down(): void {
    if (Schema::hasTable('wa_calls') && Schema::hasColumn('wa_calls', 'handled_at')) Schema::table('wa_calls', function (Blueprint $t) { $t->dropColumn(['handled_at', 'handled_by']); });
  }
};
