<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('sms_logs', function (Blueprint $t) {
      if (!Schema::hasColumn('sms_logs', 'order_id')) $t->unsignedBigInteger('order_id')->nullable()->index();
      if (!Schema::hasColumn('sms_logs', 'discount_mentioned')) $t->string('discount_mentioned', 250)->nullable();
    });
  }
  public function down(): void {}
};
