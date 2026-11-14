<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Marketing email (owner, 14 Nov 2026): extra addresses the owner adds for offer emails, and everyone who asked not to get them any more. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('mail_list')) Schema::create('mail_list', function (Blueprint $t) { $t->id(); $t->string('email', 190)->unique(); $t->string('name', 120)->nullable(); $t->string('added_by', 120)->nullable(); $t->timestamps(); });
    if (!Schema::hasTable('mail_optouts')) Schema::create('mail_optouts', function (Blueprint $t) { $t->string('email', 190)->primary(); $t->timestamp('created_at')->nullable(); });
    try { DB::statement('create index if not exists mail_outbox_to_type_idx on mail_outbox ("to", type, created_at)'); } catch (\Throwable $e) {}
  }
  public function down(): void { Schema::dropIfExists('mail_list'); Schema::dropIfExists('mail_optouts'); }
};
