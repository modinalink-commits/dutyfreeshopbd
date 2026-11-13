<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void { DB::table('discount_rules')->where('key', 'flashsale')->update(['config' => json_encode(['Discount %' => 3]), 'help' => 'Applies to Flash Sale products only (slow-selling Cigarette & Vape, rolling every 30 minutes). No daily limit.', 'updated_at' => now()]); }
  public function down(): void { DB::table('discount_rules')->where('key', 'flashsale')->update(['config' => json_encode(['Discount %' => 2]), 'updated_at' => now()]); }
};
