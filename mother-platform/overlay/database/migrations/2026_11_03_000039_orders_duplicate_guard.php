<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Duplicate-order protection: one checkout token = one order (unique), plus a content fingerprint for near-simultaneous identical submits. Additive only; existing orders keep NULL. */
return new class extends Migration {
  public function up(): void {
    Schema::table('orders', function (Blueprint $t) {
      if (!Schema::hasColumn('orders', 'idempotency_key')) $t->string('idempotency_key', 80)->nullable();
      if (!Schema::hasColumn('orders', 'order_fingerprint')) $t->string('order_fingerprint', 64)->nullable();
      if (!Schema::hasColumn('orders', 'duplicate_of_order_id')) $t->unsignedBigInteger('duplicate_of_order_id')->nullable();
    });
    DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS orders_idempotency_key_uq ON orders (idempotency_key) WHERE idempotency_key IS NOT NULL');
    DB::statement('CREATE INDEX IF NOT EXISTS orders_fingerprint_recent_idx ON orders (order_fingerprint, created_at) WHERE order_fingerprint IS NOT NULL');
  }
  public function down(): void {}
};
