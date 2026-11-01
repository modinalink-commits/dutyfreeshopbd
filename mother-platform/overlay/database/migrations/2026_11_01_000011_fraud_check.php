<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('fraud_checks')) Schema::create('fraud_checks', function (Blueprint $t) {
      $t->id(); $t->string('phone', 15)->index(); $t->string('risk_level', 12)->nullable(); $t->unsignedSmallInteger('risk_score')->nullable(); $t->unsignedInteger('total')->default(0); $t->unsignedInteger('delivered')->default(0); $t->unsignedInteger('returned')->default(0);
      $t->decimal('success_rate', 5, 1)->nullable(); $t->longText('data')->nullable(); $t->string('checked_by', 80)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
