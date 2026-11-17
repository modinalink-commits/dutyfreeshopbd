<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner 17 Nov 2026: order follow-up by responsible staff. The person who approves an order is responsible for it until it is settled; a supervisor can hand it to someone else (logged). */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'owner_id')) $t->unsignedBigInteger('owner_id')->nullable()->index();
      if (!Schema::hasColumn('orders', 'owner_at')) $t->timestamp('owner_at')->nullable();
    });
    if (!Schema::hasTable('order_owner_log')) Schema::create('order_owner_log', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('from_id')->nullable(); $t->unsignedBigInteger('to_id')->nullable();
      $t->unsignedBigInteger('by_id')->nullable(); $t->string('by_name', 120)->nullable(); $t->string('reason', 300)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('order_watch_notes')) Schema::create('order_watch_notes', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->string('issue', 20)->nullable();
      $t->text('note')->nullable(); $t->string('action', 300)->nullable(); $t->timestamp('next_followup_at')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('order_watch_reports')) Schema::create('order_watch_reports', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('user_id')->index(); $t->string('hour_key', 16); $t->string('phone', 20)->nullable(); $t->string('channel', 12)->nullable(); $t->string('status', 12)->default('queued');
      $t->integer('total')->default(0); $t->text('counts')->nullable(); $t->text('body')->nullable(); $t->string('wamid', 120)->nullable()->index(); $t->string('error', 300)->nullable(); $t->smallInteger('attempts')->default(0); $t->timestamps();
      $t->unique(['user_id', 'hour_key']); // one report per person per hour, enforced by the database
    });
    if (!Schema::hasTable('order_watch_staff')) Schema::create('order_watch_staff', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('user_id')->unique(); $t->string('phone', 20)->nullable(); $t->boolean('active')->default(true); $t->timestamps();
    });
  }
  public function down(): void {
    foreach (['order_watch_staff', 'order_watch_reports', 'order_watch_notes', 'order_owner_log'] as $t) Schema::dropIfExists($t);
    Schema::table('orders', function (Blueprint $t) { foreach (['owner_id', 'owner_at'] as $c) if (Schema::hasColumn('orders', $c)) $t->dropColumn($c); });
  }
};
