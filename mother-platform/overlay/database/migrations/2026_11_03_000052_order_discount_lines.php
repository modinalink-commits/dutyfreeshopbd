<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
/** Every discount an order received, one entry each (name, kind, %, amount, source, rule, code, automatic/manual, who, when). JSON list; orders.discount stays the total. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('orders', 'discount_lines')) Schema::table('orders', function (Blueprint $t) { $t->text('discount_lines')->nullable(); });
  }
  public function down(): void {}
};
