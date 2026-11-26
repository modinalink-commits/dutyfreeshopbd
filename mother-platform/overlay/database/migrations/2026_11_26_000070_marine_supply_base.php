<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** DutyFree BD Marine Supply (marinedutyfreebd.com), first step (owner instruction 26 Nov 2026): its own admin group.
 *  marine_access_requests = "Request Access" forms sent from the marine site (company / crew), decided by staff.
 *  marine_products = which catalog products are offered on the marine channel, where they show (public or only after login), at which ports and the USD price.
 *  New tables only; nothing in the store's own tables is changed. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('marine_access_requests')) Schema::create('marine_access_requests', function (Blueprint $t) {
      $t->id(); $t->string('kind', 12)->default('company'); $t->string('company', 160)->nullable(); $t->string('role', 80)->nullable();
      $t->string('name', 120)->nullable(); $t->string('crew_rank', 80)->nullable(); $t->string('email', 160)->nullable(); $t->string('phone', 40)->nullable();
      $t->string('imo', 12)->nullable(); $t->string('vessel', 120)->nullable(); $t->string('port', 40)->nullable(); $t->text('message')->nullable();
      $t->string('status', 12)->default('pending'); $t->string('staff_note', 300)->nullable(); $t->string('decided_by', 120)->nullable(); $t->timestamp('decided_at')->nullable();
      $t->string('ip', 45)->nullable(); $t->timestamps(); $t->index(['status', 'id']);
    });
    if (!Schema::hasTable('marine_products')) Schema::create('marine_products', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('product_id')->unique(); $t->boolean('enabled')->default(false); $t->string('visibility', 10)->default('login');
      $t->string('ports', 60)->nullable(); $t->decimal('usd_price', 12, 2)->nullable(); $t->string('updated_by', 120)->nullable(); $t->timestamps(); $t->index(['enabled', 'visibility']);
    });
  }
  public function down(): void { Schema::dropIfExists('marine_products'); Schema::dropIfExists('marine_access_requests'); }
};
