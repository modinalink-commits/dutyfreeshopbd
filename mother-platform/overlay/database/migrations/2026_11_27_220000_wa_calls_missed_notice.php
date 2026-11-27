<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Owner 27 Nov 2026 (22:07): one automatic WhatsApp line after a missed incoming WhatsApp call. Three nullable columns record that it was tried once and how it ended; down() drops only them. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('wa_calls') && !Schema::hasColumn('wa_calls', 'missed_msg_at')) Schema::table('wa_calls', function (Blueprint $t) { $t->timestamp('missed_msg_at')->nullable(); $t->string('missed_msg_status', 20)->nullable(); $t->string('missed_msg_error', 300)->nullable(); });
  }
  public function down(): void {
    if (Schema::hasTable('wa_calls') && Schema::hasColumn('wa_calls', 'missed_msg_at')) Schema::table('wa_calls', function (Blueprint $t) { $t->dropColumn(['missed_msg_at', 'missed_msg_status', 'missed_msg_error']); });
  }
};
