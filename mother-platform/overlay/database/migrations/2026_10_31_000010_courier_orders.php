<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) { $t->string('consignment_id', 60)->nullable()->index(); $t->string('courier_status', 60)->nullable(); $t->decimal('courier_fee', 10, 2)->nullable(); $t->timestamp('courier_sent_at')->nullable(); });
  }
  public function down(): void {}
};
