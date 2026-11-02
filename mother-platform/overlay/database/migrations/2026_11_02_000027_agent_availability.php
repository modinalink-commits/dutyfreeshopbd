<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('agent_availability')) Schema::create('agent_availability', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('user_id')->unique(); $t->string('agent_status', 12)->default('available')->index(); $t->timestamp('status_updated_at')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
