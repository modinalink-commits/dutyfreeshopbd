<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Staff can flag a payment request as "partial / returned parcel: payment for the received part". Owner approves it like any other manual payment. */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('order_payments') && !Schema::hasColumn('order_payments', 'partial_parcel')) Schema::table('order_payments', fn($t) => $t->boolean('partial_parcel')->default(false));
  }
  public function down(): void {}
};
