<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) { if (!Schema::hasColumn('orders', 'assigned_admin')) $t->string('assigned_admin', 80)->nullable(); });
  }
  public function down(): void {}
};
