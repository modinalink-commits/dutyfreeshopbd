<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('staff_idle_notices')) Schema::create('staff_idle_notices', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('user_id')->index(); $t->string('user_name', 120)->nullable();
      $t->timestamp('idle_from')->nullable(); $t->unsignedInteger('idle_minutes')->default(0);
      $t->unsignedInteger('waiting_chats')->default(0); $t->unsignedInteger('waiting_orders')->default(0);
      $t->string('status', 12)->default('open')->index(); // open -> submitted -> reviewed
      $t->text('reason')->nullable(); $t->timestamp('submitted_at')->nullable();
      $t->string('verdict', 12)->nullable(); $t->string('admin_note', 400)->nullable(); $t->string('reviewed_by', 120)->nullable(); $t->timestamp('reviewed_at')->nullable();
      $t->timestamps();
      $t->index(['user_id', 'status']);
    });
  }
  public function down(): void { Schema::dropIfExists('staff_idle_notices'); }
};
