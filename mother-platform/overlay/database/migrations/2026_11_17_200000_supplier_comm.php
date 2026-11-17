<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Owner 17 Nov 2026: supplier communication record. Contact changes keep a history; internal notes live apart from messages and are never sent. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('supplier_contact_log')) Schema::create('supplier_contact_log', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->string('field', 40); $t->text('old_value')->nullable(); $t->text('new_value')->nullable();
      $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_comm_notes')) Schema::create('supplier_comm_notes', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('supplier_id')->index(); $t->text('body'); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('supplier_comm_notes'); Schema::dropIfExists('supplier_contact_log'); }
};
