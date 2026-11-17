<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner 17 Nov 2026: stock watch and restock follow-up on WhatsApp for general products (never cigarettes / vape). One open task per product. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('restock_tasks')) Schema::create('restock_tasks', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->index(); $t->string('status', 12)->default('new')->index();
      $t->integer('usable')->default(0); $t->integer('on_order')->default(0); $t->decimal('per_day', 10, 2)->default(0); $t->decimal('days_left', 10, 1)->nullable();
      $t->integer('lead_days')->default(0); $t->integer('suggest_qty')->default(0); $t->integer('need_qty')->default(0); $t->string('reason', 300)->nullable();
      $t->string('assignee_name', 80)->nullable(); $t->string('assignee_wa', 20)->nullable();
      $t->integer('po_qty')->nullable(); $t->timestamp('ordered_at')->nullable(); $t->timestamp('eta_at')->nullable(); $t->timestamp('snooze_until')->nullable();
      $t->timestamp('notified_at')->nullable(); $t->timestamp('seen_at')->nullable(); $t->timestamp('last_msg_at')->nullable(); $t->integer('reminders')->default(0);
      $t->integer('received_qty')->default(0); $t->text('note')->nullable(); $t->string('closed_why', 40)->nullable(); $t->timestamp('closed_at')->nullable(); $t->timestamps();
    });
    // one open task per product, enforced by the database
    try { DB::statement('create unique index if not exists restock_tasks_one_open on restock_tasks (product_id) where closed_at is null'); } catch (\Throwable $e) {}
    if (!Schema::hasTable('restock_events')) Schema::create('restock_events', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('task_id')->nullable()->index(); $t->string('kind', 16)->index(); $t->text('body')->nullable(); $t->string('wamid', 120)->nullable()->index(); $t->string('by_name', 80)->nullable(); $t->timestamps();
    });
    Schema::table('products', function (Blueprint $t) {
      if (!Schema::hasColumn('products', 'restock_lead_days')) $t->smallInteger('restock_lead_days')->nullable();
      if (!Schema::hasColumn('products', 'restock_off')) $t->boolean('restock_off')->default(false);
    });
  }
  public function down(): void {
    Schema::dropIfExists('restock_events'); Schema::dropIfExists('restock_tasks');
    Schema::table('products', function (Blueprint $t) { foreach (['restock_lead_days', 'restock_off'] as $c) if (Schema::hasColumn('products', $c)) $t->dropColumn($c); });
  }
};
