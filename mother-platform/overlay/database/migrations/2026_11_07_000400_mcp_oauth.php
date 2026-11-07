<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** OAuth 2.1 (authorization code + PKCE S256, dynamic client registration, rotating refresh tokens) for the read-only MCP server. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('mcp_clients')) Schema::create('mcp_clients', function (Blueprint $t) { $t->id(); $t->string('client_id', 60)->unique(); $t->string('name', 160)->nullable(); $t->text('redirect_uris'); $t->timestamps(); });
    if (!Schema::hasTable('mcp_oauth_codes')) Schema::create('mcp_oauth_codes', function (Blueprint $t) { $t->id(); $t->string('code_hash', 64)->unique(); $t->string('client_id', 60); $t->text('redirect_uri'); $t->string('code_challenge', 128); $t->string('scope', 80)->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamp('expires_at'); $t->timestamp('used_at')->nullable(); $t->timestamps(); });
    if (!Schema::hasTable('mcp_refresh_tokens')) Schema::create('mcp_refresh_tokens', function (Blueprint $t) { $t->id(); $t->string('token_hash', 64)->unique(); $t->string('client_id', 60); $t->string('scope', 80)->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamp('expires_at'); $t->timestamp('revoked_at')->nullable(); $t->timestamps(); });
  }
  public function down(): void {}
};
