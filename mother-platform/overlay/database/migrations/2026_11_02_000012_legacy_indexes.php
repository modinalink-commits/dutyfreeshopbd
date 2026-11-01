<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    DB::statement("create index if not exists customers_phone11_idx on customers ((right(phone,11)))");
    DB::statement("create index if not exists legacy_customers_action_idx on legacy_customers (action)");
    DB::statement("analyze customers");
    DB::statement("analyze legacy_customers");
  }
  public function down(): void {
    DB::statement("drop index if exists customers_phone11_idx");
    DB::statement("drop index if exists legacy_customers_action_idx");
  }
};
