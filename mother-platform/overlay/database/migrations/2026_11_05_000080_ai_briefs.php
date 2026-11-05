<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** AI Brief (phase 1, read-only): one row per generated daily / manual brief. */
return new class extends Migration {
  public $withinTransaction = false;
  public function up(): void {
    try {
      if (!Schema::hasTable('ai_briefs')) Schema::create('ai_briefs', function (Blueprint $t) {
        $t->id(); $t->string('kind', 12)->default('daily'); $t->date('for_date')->index();
        $t->longText('facts')->nullable(); $t->longText('body')->nullable(); $t->string('model', 60)->nullable(); $t->string('status', 12)->default('ok');
        $t->text('error')->nullable(); $t->unsignedInteger('tokens_in')->default(0); $t->unsignedInteger('tokens_out')->default(0); $t->string('made_by', 80)->nullable(); $t->timestamps();
      });
    } catch (\Throwable $e) {
      try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => 'AI Brief table failed', 'subject' => mb_substr($e->getMessage(), 0, 240), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $x) {}
    }
  }
  public function down(): void {}
};
