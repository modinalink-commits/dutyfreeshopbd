<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('staff_api_tokens')) Schema::create('staff_api_tokens', function (Blueprint $t) {
      $t->id(); $t->foreignId('user_id')->index(); $t->string('token_hash', 64)->unique(); $t->string('device_id', 80)->nullable()->index(); $t->string('device_name', 120)->nullable();
      $t->string('platform', 20)->nullable(); $t->string('app_version', 20)->nullable(); $t->text('push_token')->nullable(); $t->string('ip', 45)->nullable();
      $t->timestamp('last_used_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamp('revoked_at')->nullable(); $t->boolean('blocked')->default(false); $t->timestamps();
    });
    if (!Schema::hasTable('staff_tasks')) Schema::create('staff_tasks', function (Blueprint $t) {
      $t->id(); $t->string('title', 200); $t->text('description')->nullable(); $t->string('priority', 10)->default('normal'); $t->foreignId('assigned_to')->nullable()->index();
      $t->foreignId('created_by')->nullable(); $t->string('department', 40)->nullable(); $t->timestamp('due_at')->nullable(); $t->foreignId('order_id')->nullable()->index(); $t->foreignId('customer_id')->nullable();
      $t->foreignId('thread_id')->nullable(); $t->string('status', 12)->default('open')->index(); $t->timestamp('done_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('staff_tasks'); Schema::dropIfExists('staff_api_tokens'); }
};
