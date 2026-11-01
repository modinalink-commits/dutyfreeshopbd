<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('claude_commands')) return;
    Schema::create('claude_commands', function (Blueprint $t) {
      $t->id(); $t->string('title', 200); $t->text('details')->nullable(); $t->string('priority', 8)->default('normal');
      $t->string('status', 10)->default('pending')->index(); // pending | working | done
      $t->text('result_note')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->string('created_by_name', 80)->nullable();
      $t->timestamp('started_at')->nullable(); $t->timestamp('done_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
