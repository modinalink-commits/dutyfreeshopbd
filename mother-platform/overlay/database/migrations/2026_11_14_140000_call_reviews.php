<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Call Review: a manager's score and feedback for one call, and a log of who listened to which recording. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('call_reviews')) Schema::create('call_reviews', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('call_id')->unique(); $t->unsignedBigInteger('agent_id')->nullable()->index(); $t->unsignedBigInteger('reviewer_id')->nullable(); $t->string('reviewer_name', 120)->nullable();
      $t->unsignedTinyInteger('s_manners')->default(0); $t->unsignedTinyInteger('s_info')->default(0); $t->unsignedTinyInteger('s_save')->default(0); $t->unsignedTinyInteger('s_note')->default(0);
      $t->decimal('score', 4, 2)->default(0); $t->text('feedback')->nullable(); $t->boolean('follow_up')->default(false); $t->timestamps();
    });
    if (!Schema::hasTable('call_listens')) Schema::create('call_listens', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('call_id')->index(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->timestamp('created_at')->nullable()->index();
    });
  }
  public function down(): void { Schema::dropIfExists('call_listens'); Schema::dropIfExists('call_reviews'); }
};
