<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Incomplete checkouts get the same data an order has (coupon, courier, notes, activity history) while staying a separate table: no stock, SMS, courier booking or revenue is touched. */
return new class extends Migration {
  public function up(): void {
    foreach (['coupon' => 40, 'courier' => 40, 'consignment' => 80] as $c => $len) if (!Schema::hasColumn('incomplete_orders', $c)) Schema::table('incomplete_orders', fn(Blueprint $t) => $t->string($c, $len)->nullable());
    if (!Schema::hasTable('incomplete_activity')) Schema::create('incomplete_activity', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('incomplete_id')->index(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable();
      $t->string('kind', 40); $t->string('category', 40)->nullable(); $t->text('old_value')->nullable(); $t->text('new_value')->nullable(); $t->text('body')->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
