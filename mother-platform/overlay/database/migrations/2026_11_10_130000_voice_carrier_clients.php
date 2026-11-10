<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
/** Wholesale call clients: a client's IP + port + prefix. Test mode: stores clients and generates the PBX config; it does not push to the PBX. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('voice_carrier_clients')) return;
    Schema::create('voice_carrier_clients', function (Blueprint $t) {
      $t->id(); $t->string('name', 120); $t->string('ip', 64); $t->unsignedInteger('port')->default(5060);
      $t->string('prefix', 20); $t->unsignedInteger('max_calls')->default(20); $t->string('status', 12)->default('active');
      $t->string('note', 300)->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
      $t->unique('prefix'); $t->index(['ip', 'status']);
    });
  }
  public function down(): void { Schema::dropIfExists('voice_carrier_clients'); }
};
