<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Call Clients CDR: every finished call is reported (answered or not); store the hangup cause. billsec = 0 means not connected. */
return new class extends Migration {
  public function up(): void {
    Schema::table('voice_call_cdrs', function (Blueprint $t) { $t->string('cause', 40)->nullable()->after('billsec'); $t->index('ended_at'); });
  }
  public function down(): void {
    Schema::table('voice_call_cdrs', function (Blueprint $t) { $t->dropIndex(['ended_at']); $t->dropColumn('cause'); });
  }
};
