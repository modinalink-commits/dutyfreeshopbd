<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Customer email (owner, 14 Nov 2026): every email the system queues or sends, with its result. One row per email; "ref" stops the same email going twice. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('mail_outbox')) return;
    Schema::create('mail_outbox', function (Blueprint $t) {
      $t->id(); $t->string('to', 190); $t->string('subject', 250); $t->string('type', 40)->index(); $t->string('ref', 120)->nullable()->unique();
      $t->unsignedBigInteger('order_id')->nullable()->index(); $t->string('status', 20)->default('queued')->index(); $t->text('html'); $t->string('error', 500)->nullable();
      $t->timestamp('sent_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('mail_outbox'); }
};
