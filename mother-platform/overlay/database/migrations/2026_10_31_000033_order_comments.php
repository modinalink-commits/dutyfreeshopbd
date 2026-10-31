<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('order_comments')) return;
    Schema::create('order_comments', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('order_id')->index(); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name', 120)->nullable();
      $t->string('category', 80)->nullable(); $t->text('body'); $t->timestamps();
    });
  }
  public function down(): void {}
};
