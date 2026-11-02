<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('image_audit_log')) return;
    Schema::create('image_audit_log', function (Blueprint $t) {
      $t->id(); $t->string('kind', 10); $t->string('table_name', 40)->nullable(); $t->unsignedBigInteger('row_id')->nullable(); $t->string('col', 40)->nullable();
      $t->string('sku', 80)->nullable(); $t->string('name', 190)->nullable(); $t->text('old_value')->nullable(); $t->string('state', 20)->nullable(); $t->string('attempt', 40)->nullable(); $t->string('result', 12)->nullable(); $t->text('error')->nullable(); $t->timestamps();
      $t->index(['kind', 'row_id']);
    });
  }
  public function down(): void {}
};
