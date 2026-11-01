<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('dob_change_requests')) Schema::create('dob_change_requests', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('customer_id')->index(); $t->date('old_dob')->nullable(); $t->date('new_dob'); $t->string('reason', 300);
      $t->string('status', 12)->default('pending')->index(); $t->string('admin_note', 300)->nullable(); $t->string('decided_by', 80)->nullable(); $t->timestamp('decided_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void { Schema::dropIfExists('dob_change_requests'); }
};
