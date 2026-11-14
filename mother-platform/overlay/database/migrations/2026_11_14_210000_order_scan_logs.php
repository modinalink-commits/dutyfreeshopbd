<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('order_scan_logs')) return;
    Schema::create('order_scan_logs', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->nullable()->index(); $t->string('invoice', 40)->nullable(); $t->string('code', 80);
      $t->string('result', 20)->index(); $t->string('message', 300)->nullable();
      $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable(); $t->timestamp('created_at')->nullable()->index();
    });
  }
  public function down(): void { Schema::dropIfExists('order_scan_logs'); }
};
