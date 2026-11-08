<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  /** The earlier cleanup compared a json column and silently failed on Postgres. Match on the reason text instead. */
  public function up(): void {
    try { if (Schema::hasTable('chat_order_requests')) DB::table('chat_order_requests')->where('status', 'review')->where('reason', 'like', 'Wants to order (word:%')->update(['status'=>'dismissed', 'handled_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
