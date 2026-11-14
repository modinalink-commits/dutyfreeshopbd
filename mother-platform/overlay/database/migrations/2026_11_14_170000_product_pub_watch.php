<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Publish-state memory for the "back in store" SMS (owner, 14 Nov 2026): the last publish state seen for each product and when it changed, so an Unpublished -> Published move is noticed whichever way it happened (by hand, bulk, or stock-in). */
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('product_pub_watch')) return;
    Schema::create('product_pub_watch', function (Blueprint $t) {
      $t->unsignedBigInteger('product_id')->primary();
      $t->boolean('published')->default(false);
      $t->timestamp('changed_at')->nullable();
    });
  }
  public function down(): void { Schema::dropIfExists('product_pub_watch'); }
};
