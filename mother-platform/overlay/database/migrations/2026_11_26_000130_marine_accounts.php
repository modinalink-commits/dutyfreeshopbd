<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marine Supply Access Management (owner brief 26 Nov 2026): verified accounts for Shipping Owners, Local Agencies and Vessels.
 *  marine_accounts = one login per approved person (own username and password, set by the person through an emailed single-use link plus an emailed code);
 *  marine_account_vessels = IMO list of an owner / vessels an agency is appointed for; marine_documents = files sent with an application (private disk);
 *  marine_invites = record of every email sent for an application or account (who, when, delivered or the error). New tables, plus four columns on marine_access_requests. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('marine_access_requests')) Schema::table('marine_access_requests', function (Blueprint $t) {
      if (!Schema::hasColumn('marine_access_requests', 'vessel_role')) $t->string('vessel_role', 12)->nullable();
      if (!Schema::hasColumn('marine_access_requests', 'docs_note')) $t->string('docs_note', 600)->nullable();
      if (!Schema::hasColumn('marine_access_requests', 'docs_requested_at')) $t->timestamp('docs_requested_at')->nullable();
      if (!Schema::hasColumn('marine_access_requests', 'doc_hash')) $t->string('doc_hash', 64)->nullable()->index();
      if (!Schema::hasColumn('marine_access_requests', 'doc_expires_at')) $t->timestamp('doc_expires_at')->nullable();
    });
    if (!Schema::hasTable('marine_accounts')) Schema::create('marine_accounts', function (Blueprint $t) {
      $t->id(); $t->string('type', 10); $t->unsignedBigInteger('request_id')->nullable()->index(); $t->string('username', 40)->unique(); $t->string('email', 160)->index();
      $t->string('name', 120)->nullable(); $t->string('company', 160)->nullable(); $t->string('role', 80)->nullable(); $t->string('vessel_role', 12)->nullable(); $t->string('crew_rank', 80)->nullable();
      $t->string('phone', 40)->nullable(); $t->string('port', 40)->nullable(); $t->string('imo', 12)->nullable(); $t->string('vessel', 120)->nullable();
      $t->string('password', 255)->nullable(); $t->string('status', 12)->default('invited')->index();
      $t->string('invite_hash', 64)->nullable()->index(); $t->timestamp('invite_expires_at')->nullable(); $t->string('invite_purpose', 10)->nullable();
      $t->string('otp_hash', 255)->nullable(); $t->timestamp('otp_expires_at')->nullable(); $t->unsignedTinyInteger('otp_tries')->default(0); $t->timestamp('otp_sent_at')->nullable(); $t->unsignedSmallInteger('otp_sent_count')->default(0);
      $t->string('approved_by', 120)->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamp('activated_at')->nullable();
      $t->unsignedTinyInteger('failed_attempts')->default(0); $t->timestamp('locked_until')->nullable(); $t->timestamp('last_login_at')->nullable(); $t->string('last_login_ip', 45)->nullable(); $t->timestamp('force_logout_at')->nullable();
      $t->timestamps();
    });
    if (!Schema::hasTable('marine_account_vessels')) Schema::create('marine_account_vessels', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('account_id')->index(); $t->string('imo', 12); $t->string('name', 120)->nullable(); $t->string('note', 200)->nullable(); $t->string('added_by', 120)->nullable(); $t->timestamps(); $t->unique(['account_id', 'imo']);
    });
    if (!Schema::hasTable('marine_documents')) Schema::create('marine_documents', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('request_id')->nullable()->index(); $t->unsignedBigInteger('account_id')->nullable()->index(); $t->string('name', 190); $t->string('path', 190); $t->string('mime', 60)->nullable(); $t->unsignedInteger('size')->default(0); $t->string('by_side', 12)->default('applicant'); $t->timestamps();
    });
    if (!Schema::hasTable('marine_invites')) Schema::create('marine_invites', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('request_id')->nullable()->index(); $t->unsignedBigInteger('account_id')->nullable()->index(); $t->string('kind', 12); $t->string('to', 160); $t->boolean('ok')->default(false); $t->string('error', 400)->nullable(); $t->string('sent_by', 120)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {
    Schema::dropIfExists('marine_invites'); Schema::dropIfExists('marine_documents'); Schema::dropIfExists('marine_account_vessels'); Schema::dropIfExists('marine_accounts');
    if (Schema::hasTable('marine_access_requests')) Schema::table('marine_access_requests', function (Blueprint $t) { foreach (['vessel_role', 'docs_note', 'docs_requested_at', 'doc_hash', 'doc_expires_at'] as $c) if (Schema::hasColumn('marine_access_requests', $c)) $t->dropColumn($c); });
  }
};
