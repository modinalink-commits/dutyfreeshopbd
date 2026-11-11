<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Call Clients: optional portal login (username + hashed password) so a client can see its own calls, minutes, amount, rate and billing cycle. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('voice_carrier_clients')) return;
    Schema::table('voice_carrier_clients', function (Blueprint $t) {
      if (!Schema::hasColumn('voice_carrier_clients', 'portal_user')) $t->string('portal_user', 60)->nullable()->unique();
      if (!Schema::hasColumn('voice_carrier_clients', 'portal_pass')) $t->string('portal_pass', 120)->nullable();
      if (!Schema::hasColumn('voice_carrier_clients', 'portal_on')) $t->boolean('portal_on')->default(false);
      if (!Schema::hasColumn('voice_carrier_clients', 'portal_last_login')) $t->timestamp('portal_last_login')->nullable();
    });
  }
  public function down(): void {
    if (Schema::hasTable('voice_carrier_clients')) Schema::table('voice_carrier_clients', function (Blueprint $t) { $t->dropColumn(['portal_user', 'portal_pass', 'portal_on', 'portal_last_login']); });
  }
};
