<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Requests are visible in the supplier app at once; the push notification / SMS goes at the daily send time (notified_at marks it). */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('reorder_requests')) return;
    Schema::table('reorder_requests', function (Blueprint $t) { if (!Schema::hasColumn('reorder_requests', 'notified_at')) $t->timestamp('notified_at')->nullable(); });
    DB::table('reorder_requests')->whereNull('notified_at')->whereNotNull('rfq_id')->where('status', '!=', 'New')->update(['notified_at' => DB::raw('created_at')]);
    // requests still waiting as New already have a draft RFQ: open it now so the supplier app shows it
    DB::table('supplier_rfqs')->whereIn('id', DB::table('reorder_requests')->where('status', 'New')->whereNotNull('rfq_id')->pluck('rfq_id'))->where('status', 'draft')->update(['status' => 'sent', 'expires_at' => now()->addDays(3), 'updated_at' => now()]);
  }
  public function down(): void {}
};
