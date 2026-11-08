<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('ai_behavior_events')) Schema::create('ai_behavior_events', function (Blueprint $t) {
      $t->id(); $t->foreignId('thread_id')->nullable()->index(); $t->string('mkey', 40)->nullable()->index(); $t->string('actor', 10)->index(); $t->string('type', 30)->index(); $t->string('channel', 20)->nullable(); $t->json('meta')->nullable(); $t->timestamp('created_at')->useCurrent()->index();
    });
    if (!Schema::hasTable('ai_customer_memory')) Schema::create('ai_customer_memory', function (Blueprint $t) {
      $t->id(); $t->string('mkey', 40)->unique(); $t->string('phone', 20)->nullable()->index(); $t->string('name', 160)->nullable(); $t->string('channel', 20)->nullable();
      $t->unsignedInteger('msg_count')->default(0); $t->json('lang_n')->nullable(); $t->string('lang_pref', 12)->nullable(); $t->json('intents')->nullable(); $t->json('moods')->nullable(); $t->json('products')->nullable();
      $t->unsignedInteger('orders_count')->default(0); $t->unsignedInteger('delivered_count')->default(0); $t->string('last_order_status', 30)->nullable(); $t->unsignedInteger('avg_staff_wait_min')->nullable();
      $t->text('summary')->nullable(); $t->timestamp('last_seen_at')->nullable(); $t->timestamp('refreshed_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('ai_staff_examples')) Schema::create('ai_staff_examples', function (Blueprint $t) {
      $t->id(); $t->foreignId('thread_id')->nullable()->index(); $t->string('staff', 80)->nullable(); $t->string('intent', 30)->nullable()->index(); $t->string('channel', 20)->nullable(); $t->string('lang', 12)->nullable();
      $t->text('customer_text'); $t->text('reply_text'); $t->unsignedInteger('wait_sec')->nullable(); $t->timestamp('created_at')->useCurrent()->index();
    });
  }
  public function down(): void { Schema::dropIfExists('ai_staff_examples'); Schema::dropIfExists('ai_customer_memory'); Schema::dropIfExists('ai_behavior_events'); }
};
