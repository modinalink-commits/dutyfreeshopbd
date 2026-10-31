<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('products', function (Blueprint $t) {
      foreach (['video_url'=>500,'sub_sub'=>120,'stock_status'=>20] as $c=>$n) if (!Schema::hasColumn('products', $c)) { $col = $t->string($c, $n)->nullable(); if ($c === 'stock_status') $col->default('in_stock'); }
      if (!Schema::hasColumn('products', 'meta_keywords')) $t->text('meta_keywords')->nullable();
      if (!Schema::hasColumn('products', 'affiliate_commission')) $t->decimal('affiliate_commission', 6, 2)->nullable();
      if (!Schema::hasColumn('products', 'low_stock_alert')) $t->integer('low_stock_alert')->nullable();
      foreach ([1,2,3,4,5] as $n) if (!Schema::hasColumn('products', "buy{$n}_price")) $t->decimal("buy{$n}_price", 12, 2)->nullable();
    });
  }
  public function down(): void {}
};
