<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Commerce Agent: conversation state / intent / mood on threads, hand-off records, and an append-only AI action log. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('chat_threads')) Schema::table('chat_threads', function (Blueprint $t) {
      foreach ([['ai_state', fn() => $t->text('ai_state')->nullable()], ['intent', fn() => $t->string('intent', 30)->nullable()], ['mood', fn() => $t->string('mood', 20)->nullable()],
        ['priority', fn() => $t->string('priority', 10)->default('normal')], ['ai_confidence', fn() => $t->unsignedSmallInteger('ai_confidence')->nullable()],
        ['needs_human', fn() => $t->boolean('needs_human')->default(false)], ['handoff_at', fn() => $t->timestamp('handoff_at')->nullable()], ['handoff_reason', fn() => $t->string('handoff_reason', 200)->nullable()],
        ['ai_summary', fn() => $t->text('ai_summary')->nullable()]] as [$c, $f]) if (!Schema::hasColumn('chat_threads', $c)) $f();
    });
    if (!Schema::hasTable('chat_handoffs')) Schema::create('chat_handoffs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('thread_id')->index(); $t->unsignedBigInteger('assigned_to')->nullable()->index();
      $t->string('reason', 200)->nullable(); $t->text('summary')->nullable(); $t->string('priority', 10)->default('normal');
      $t->string('status', 12)->default('open')->index(); $t->timestamp('accepted_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('ai_activity_logs')) {
      Schema::create('ai_activity_logs', function (Blueprint $t) {
        $t->id(); $t->unsignedBigInteger('thread_id')->nullable()->index(); $t->unsignedBigInteger('customer_id')->nullable(); $t->string('tool', 40)->index();
        $t->text('input')->nullable(); $t->text('result')->nullable(); $t->boolean('ok')->default(true); $t->boolean('sim')->default(false); $t->timestamp('created_at')->useCurrent();
      });
      // immutable: rows can be added, never changed or removed
      if (DB::getDriverName() === 'pgsql') {
        DB::unprepared("CREATE OR REPLACE FUNCTION ai_activity_logs_immutable() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION 'ai_activity_logs is append-only'; END; \$\$ LANGUAGE plpgsql");
        DB::unprepared("DROP TRIGGER IF EXISTS ai_activity_logs_ro ON ai_activity_logs");
        DB::unprepared("CREATE TRIGGER ai_activity_logs_ro BEFORE UPDATE OR DELETE ON ai_activity_logs FOR EACH ROW EXECUTE FUNCTION ai_activity_logs_immutable()");
      }
    }
  }
  public function down(): void {}
};
