<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Removes the "[reaction/edit/revoke ...]" placeholder bubbles older webhook code saved as chat messages, then refreshes each affected thread's preview. Awaiting-reply state is never cleared here. Safe to run again. */
return new class extends Migration {
  public function up(): void {
    try {
      if (!Schema::hasTable('chat_messages') || !Schema::hasTable('chat_threads')) return;
      $q = fn() => DB::table('chat_messages')->where(function ($w) {
        foreach (['reaction', 'edit', 'revoke', 'unsupported'] as $t) $w->orWhere('body', '[' . $t . ' received — open WhatsApp to view]')->orWhere('body', '[' . $t . ' sent from the WhatsApp app]');
      });
      $tids = $q()->distinct()->pluck('thread_id');
      if ($tids->isEmpty()) return;
      $q()->delete();
      foreach ($tids as $tid) {
        $last = DB::table('chat_messages')->where('thread_id', $tid)->orderByDesc('id')->first(['body', 'dir', 'created_at']);
        if ($last) DB::table('chat_threads')->where('id', $tid)->update(['last_message' => mb_substr((string)($last->body ?? ''), 0, 300), 'last_dir' => $last->dir, 'last_at' => $last->created_at]);
      }
      \Illuminate\Support\Facades\Log::info('chat placeholder bubbles removed from threads: ' . $tids->count());
    } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
