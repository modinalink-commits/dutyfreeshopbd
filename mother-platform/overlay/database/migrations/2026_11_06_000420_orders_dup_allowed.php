<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('orders')) return;
    if (!Schema::hasColumn('orders', 'dup_allowed_at')) Schema::table('orders', fn(Blueprint $t) => $t->timestamp('dup_allowed_at')->nullable());
    if (!Schema::hasColumn('orders', 'dup_allowed_by')) Schema::table('orders', fn(Blueprint $t) => $t->string('dup_allowed_by', 80)->nullable());
  }
  public function down(): void {}
};
