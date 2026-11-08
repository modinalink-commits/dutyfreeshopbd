<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\OrderIntent;
return new class extends Migration {
  /** One-time: old chats (last 21 days) where the customer asked to order and no order followed become Order Request leads. */
  public function up(): void {
    try {
      if (!Schema::hasTable('chat_order_requests') || !Schema::hasTable('chat_messages')) return;
      $since = now()->subDays(21);
      $rows = DB::table('chat_messages')->where('dir', 'in')->where('created_at', '>=', $since)->whereNotNull('body')->where('body', '!=', '')->orderBy('id')->get(['thread_id', 'body', 'created_at']);
      $last = [];
      foreach ($rows as $m) { if (OrderIntent::detect((string)$m->body)) $last[$m->thread_id] = $m; }
      foreach ($last as $tid => $m) {
        $t = DB::table('chat_threads')->find($tid); if (!$t) continue;
        if (DB::table('chat_order_requests')->where('thread_id', $tid)->exists()) continue;
        if (!empty($t->phone) && DB::table('orders')->where('phone', $t->phone)->where('created_at', '>=', $m->created_at)->exists()) continue;
        OrderIntent::onCustomer($t, (string)$m->body);
      }
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
