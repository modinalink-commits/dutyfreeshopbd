<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Dialer: when the customer asked to be called back, and when a "we tried to call you" message was sent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('call_logs')) return;
    if (!Schema::hasColumn('call_logs', 'callback_at')) Schema::table('call_logs', fn(Blueprint $t) => $t->timestamp('callback_at')->nullable()->index());
    if (!Schema::hasColumn('call_logs', 'msg_sent_at')) Schema::table('call_logs', fn(Blueprint $t) => $t->timestamp('msg_sent_at')->nullable());
  }
  public function down(): void {
    if (!Schema::hasTable('call_logs')) return;
    foreach (['callback_at', 'msg_sent_at'] as $c) if (Schema::hasColumn('call_logs', $c)) Schema::table('call_logs', fn(Blueprint $t) => $t->dropColumn($c));
  }
};
