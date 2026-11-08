<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_wa_numbers')) Schema::create('chat_wa_numbers', function (Blueprint $t) {
      $t->id(); $t->string('phone_id', 40)->unique(); $t->string('waba_id', 40)->nullable(); $t->string('display_phone', 40)->nullable(); $t->string('verified_name', 160)->nullable();
      $t->text('token'); $t->boolean('subscribed')->default(false); $t->text('note')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('chat_wa_numbers'); }
};
