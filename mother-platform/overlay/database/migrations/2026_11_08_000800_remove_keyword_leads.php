<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  /** Owner's rule: an Order Request exists only after the customer has seen the full preview and confirmed it. Keyword-only leads are removed. */
  public function up(): void {
    try { if (Schema::hasTable('chat_order_requests')) DB::table('chat_order_requests')->where('status', 'review')->where('cart', '[]')->where('reason', 'like', 'Wants to order (word:%')->update(['status'=>'dismissed', 'handled_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
