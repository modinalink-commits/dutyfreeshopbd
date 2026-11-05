<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Supplier auto account setup: username, single-use activation link (hash only), SMS log. No plain passwords or tokens are stored. */
return new class extends Migration {
  public function up(): void {
    Schema::table('supplier_users', function (Blueprint $t) {
      if (!Schema::hasColumn('supplier_users','username')) $t->string('username',80)->nullable()->unique();
      if (!Schema::hasColumn('supplier_users','invite_hash')) $t->string('invite_hash',64)->nullable()->index();
      foreach (['invite_expires_at','invite_sent_at','activated_at','password_changed_at'] as $c) if (!Schema::hasColumn('supplier_users',$c)) $t->timestamp($c)->nullable();
      if (!Schema::hasColumn('supplier_users','invite_status')) $t->string('invite_status',12)->nullable();
    });
    if (!Schema::hasTable('supplier_sms_log')) Schema::create('supplier_sms_log', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->unsignedBigInteger('supplier_user_id')->nullable(); $t->string('mobile',20); $t->string('type',20); $t->string('status',12)->default('pending');
      $t->string('triggered_by',120)->nullable(); $t->string('response',200)->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
