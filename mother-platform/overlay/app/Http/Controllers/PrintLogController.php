<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Server-side record of invoice / label print requests. One row per order per print click (batch), with duplicate guards. */
class PrintLogController extends Controller {
  public function store(Request $r, $order = null) {
    $d = $r->validate(['type' => 'required|in:invoice,label', 'ids' => 'nullable|array|max:200', 'ids.*' => 'integer', 'batch' => 'nullable|string|max:64', 'device' => 'nullable|string|max:120']);
    $ids = $order ? [(int)$order] : array_values(array_unique(array_map('intval', $d['ids'] ?? []))); abort_unless($ids, 422);
    $u = auth()->user(); $batch = $d['batch'] ?? bin2hex(random_bytes(8)); $ty = $d['type']; $label = $ty === 'invoice' ? 'Invoice' : 'Label'; $saved = 0;
    $ip = $r->ip(); $ua = mb_substr((string)$r->userAgent(), 0, 300);
    foreach (DB::table('orders')->whereIn('id', $ids)->get(['id', 'invoice', 'customer_id']) as $o) {
      // duplicate guards: same batch id, or the same user printing the same order and type within 5 seconds
      if (DB::table('order_print_logs')->where('order_id', $o->id)->where('print_type', $ty)->where(fn($w) => $w->where('batch_id', $batch)->orWhere(fn($x) => $x->where('printed_by', $u->id)->where('printed_at', '>', now()->subSeconds(5))))->exists()) continue;
      DB::transaction(function () use ($o, $ty, $batch, $u, $ip, $ua, $d, $label) {
        DB::table('order_print_logs')->insert(['order_id' => $o->id, 'customer_id' => $o->customer_id, 'invoice' => $o->invoice, 'print_type' => $ty, 'batch_id' => $batch, 'printed_by' => $u->id, 'printed_by_name' => $u->name,
          'printed_at' => now(), 'ip_address' => $ip, 'user_agent' => $ua, 'device' => $d['device'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('orders')->where('id', $o->id)->update([$ty . '_print_count' => DB::raw($ty . '_print_count + 1'), 'last_' . $ty . '_printed_at' => now()]);
        DB::table('audit_logs')->insert(['user_id' => $u->id, 'user_name' => $u->name, 'action' => 'print.' . $ty, 'subject' => mb_substr($u->name . ' printed ' . $label . ' ' . $o->invoice, 0, 240), 'created_at' => now(), 'updated_at' => now()]);
      });
      $saved++;
    }
    // Owner rule (14 Nov 2026): printing the invoice / label of an Approved order moves it to Packaging by itself, so it leaves the Approved list and is not printed a second time by mistake.
    $moved = 0;
    foreach (DB::table('orders')->whereIn('id', $ids)->where('status', 'Approved')->get() as $o) {
      try {
        if (\App\Support\DupGuard::block($o, 'Packaging')) continue; // a same-day duplicate still needs its manual Allow
        $c = DB::transaction(function () use ($o, $u, $label) {
          $n = DB::table('orders')->where('id', $o->id)->where('status', 'Approved')->update(['status' => 'Packaging', 'updated_at' => now()]);
          if ($n) DB::table('order_status_logs')->insert(['order_id' => $o->id, 'user_id' => $u->id, 'user_name' => $u->name, 'from_status' => 'Approved', 'to_status' => 'Packaging', 'note' => $label . ' printed: moved to Packaging automatically', 'created_at' => now(), 'updated_at' => now()]);
          return $n;
        });
        if ($c) { $moved++; DB::table('audit_logs')->insert(['user_id' => $u->id, 'user_name' => $u->name, 'action' => 'order.status', 'subject' => \App\Support\Inv::show($o->invoice) . ' · Approved → Packaging (' . $label . ' printed)', 'created_at' => now(), 'updated_at' => now()]); }
      } catch (\Throwable $e) { report($e); }
    }
    return response()->json(['ok' => true, 'saved' => $saved, 'moved' => $moved]);
  }
}
