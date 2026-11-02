<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    // New-site orders that never got a courier name: show the default (Pathao), exactly as the old site did. No parcel is booked.
    DB::table('orders')->whereNull('legacy_order_id')->whereNull('courier')->whereNull('consignment_id')->where('created_at', '>=', '2026-10-25')->update(['courier' => 'Pathao']);
  }
  public function down(): void {}
};
