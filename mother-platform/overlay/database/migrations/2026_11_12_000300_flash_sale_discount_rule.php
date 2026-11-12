<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    if (!DB::table('discount_rules')->where('key', 'flashsale')->exists()) DB::table('discount_rules')->insert(['key' => 'flashsale', 'title' => 'Flash Sale discount', 'help' => 'Applies to Flash Sale products only, once per customer per day.', 'enabled' => true, 'config' => json_encode(['Discount %' => 2]), 'position' => 90, 'created_at' => now(), 'updated_at' => now()]);
  }
  public function down(): void { DB::table('discount_rules')->where('key', 'flashsale')->delete(); }
};
