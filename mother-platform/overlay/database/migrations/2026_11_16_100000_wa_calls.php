<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Owner 16 Nov 2026: WhatsApp calls (Cloud API Calling) answered inside the admin, like the PBX call centre. One row per call. Additive; down() drops only this table. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('wa_calls')) Schema::create('wa_calls', function (Blueprint $t) {
      $t->id(); $t->string('call_id', 190)->unique(); $t->string('phone_id', 40)->index(); $t->string('from_wa', 40)->nullable()->index(); $t->string('from_name', 160)->nullable();
      $t->string('direction', 30)->default('USER_INITIATED'); $t->string('status', 20)->default('ringing')->index(); // ringing | answering | active | ended | missed | rejected | failed
      $t->text('sdp_offer')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable();
      $t->timestamp('answered_at')->nullable(); $t->timestamp('ended_at')->nullable(); $t->unsignedInteger('duration')->nullable(); $t->string('note', 300)->nullable();
      $t->timestamps(); $t->index('created_at');
    });
  }
  public function down(): void { Schema::dropIfExists('wa_calls'); }
};
