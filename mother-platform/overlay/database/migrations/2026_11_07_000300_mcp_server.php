<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MCP server (phase 1, read-only): hashed bearer tokens + audit log of every call. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('mcp_tokens')) Schema::create('mcp_tokens', function (Blueprint $t) {
      $t->id(); $t->string('name', 120); $t->string('token_hash', 64)->unique(); $t->string('token_hint', 12)->nullable(); $t->string('scope', 20)->default('read');
      $t->timestamp('expires_at')->nullable(); $t->timestamp('revoked_at')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('mcp_audit')) Schema::create('mcp_audit', function (Blueprint $t) {
      $t->id(); $t->string('request_id', 40)->index(); $t->unsignedBigInteger('token_id')->nullable()->index(); $t->string('client', 80)->nullable(); $t->string('tool', 80)->nullable()->index(); $t->text('input')->nullable();
      $t->string('status', 20)->index(); $t->string('error', 240)->nullable(); $t->string('ip', 60)->nullable(); $t->unsignedInteger('ms')->default(0); $t->timestamp('created_at')->useCurrent();
    });
  }
  public function down(): void {}
};
