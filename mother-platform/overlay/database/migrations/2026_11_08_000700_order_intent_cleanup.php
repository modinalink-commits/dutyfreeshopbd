<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\OrderIntent;
return new class extends Migration {
  /** The first keyword list was too loose (92 leads, many not orders). Close those untouched leads and re-scan the last 21 days with the stricter rules. */
  public function up(): void {
    try {
      if (!Schema::hasTable('chat_order_requests') || !Schema::hasTable('chat_messages')) return;
      DB::table('chat_order_requests')->where('status', 'review')->where('cart', '[]')->where('reason', 'like', 'Wants to order (word:%')->update(['status'=>'dismissed', 'handled_at'=>now(), 'updated_at'=>now()]);
      $rows = DB::table('chat_messages')->where('dir', 'in')->where('created_at', '>=', now()->subDays(21))->whereNotNull('body')->where('body', '!=', '')->orderBy('id')->get(['thread_id', 'body', 'created_at']);
      $last = [];
      foreach ($rows as $m) { if (OrderIntent::detect((string)$m->body)) $last[$m->thread_id] = $m; }
      foreach ($last as $tid => $m) {
        $t = DB::table('chat_threads')->find($tid); if (!$t) continue;
        if (DB::table('chat_order_requests')->where('thread_id', $tid)->where('status', 'review')->exists()) continue;
        if (!empty($t->phone) && DB::table('orders')->where('phone', $t->phone)->where('created_at', '>=', $m->created_at)->exists()) continue;
        OrderIntent::onCustomer($t, (string)$m->body);
      }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
