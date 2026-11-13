<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Staff-only: order lines sold from source shops, grouped by vendor, with the vendor link and a to-order / ordered / shipped / received status. Customers never see any of it. */
class VendorOrderController extends Controller {
  const OPEN = ['Cancel', 'Cancelled', 'Returned', 'Return Pending', 'Draft'];
  public function index(Request $r) {
    abort_unless(Schema::hasColumn('order_items', 'src_store_id'), 404);
    $st = (string)$r->query('status', 'todo');
    $q = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotNull('i.src_store_id')->whereNotIn('o.status', self::OPEN)
      ->select('i.*', 'o.invoice', 'o.status as ostatus', 'o.created_at as odate', 'o.customer_name as cname', 'o.phone as cphone', 'o.address as caddr')->orderBy('i.src_store')->orderByDesc('i.id');
    if ($st === 'todo') $q->whereNull('i.vendor_status'); elseif (in_array($st, ['ordered', 'shipped', 'received'], true)) $q->where('i.vendor_status', $st);
    $rows = $q->limit(500)->get()->groupBy('src_store');
    $counts = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotNull('i.src_store_id')->whereNotIn('o.status', self::OPEN)->selectRaw("coalesce(i.vendor_status,'todo') s, count(*) n")->groupBy('s')->pluck('n', 's');
    // sourcing commission: who sourced each line (default = the person whose agreement covers that source store)
    $src = ['on' => \App\Support\SourcingCommission::ready(), 'users' => collect(), 'byStore' => [], 'me' => auth()->id(), 'checker' => in_array((string)optional(auth()->user())->role, ['super', 'admin', 'manager'], true)];
    if ($src['on']) { $ags = DB::table('sourcing_agreements')->where('active', true)->get(['user_id', 'name', 'store_ids']); $src['users'] = $ags->pluck('name', 'user_id'); foreach ($ags as $g) foreach ((json_decode((string)$g->store_ids, true) ?: []) as $sid) $src['byStore'][(int)$sid] = (int)$g->user_id; }
    return view('vendor_orders', compact('rows', 'st', 'counts', 'src'));
  }
  public function update(Request $r, $id) {
    $d = $r->validate(['vendor_status' => 'nullable|in:ordered,shipped,received', 'vendor_ref' => 'nullable|string|max:160']);
    DB::table('order_items')->where('id', $id)->whereNotNull('src_store_id')->update(['vendor_status' => $d['vendor_status'] ?? null, 'vendor_ref' => $d['vendor_ref'] ?? null]);
    $this->sourcing($r, (int)$id);
    return back();
  }
  /** Sourcing entry on a line: buy price + who sourced it. Staff may enter only themselves; admin/manager may set anyone. A new or changed entry waits for approval (an admin's own change is approved at once). Lines of an approved month are locked. */
  private function sourcing(Request $r, int $id): void {
    if (!\App\Support\SourcingCommission::ready() || !$r->has('sourced_by')) return;
    $d = $r->validate(['sourced_by' => 'nullable|integer', 'sourced_cost' => 'nullable|numeric|min:0|max:100000000']);
    $l = DB::table('order_items')->where('id', $id)->whereNotNull('src_store_id')->first(); if (!$l || \App\Support\SourcingCommission::locked($l)) return;
    $role = (string)optional(auth()->user())->role; $checker = in_array($role, ['super', 'admin', 'manager'], true); $me = (int)auth()->id();
    $by = $d['sourced_by'] ?? null; $by = $by ? (int)$by : null;
    if ($by && !DB::table('sourcing_agreements')->where('user_id', $by)->where('active', true)->exists()) return; // only people with an agreement
    if (!$checker && $by !== null && $by !== $me) return;                                                      // staff: only yourself
    if (!$checker && $by === null && (int)$l->sourced_by !== $me) return;                                      // staff cannot clear someone else
    $cost = array_key_exists('sourced_cost', $d) && $d['sourced_cost'] !== null ? (float)$d['sourced_cost'] : null;
    $same = (int)$l->sourced_by === (int)$by && (($cost === null && $l->sourced_cost === null) || ($cost !== null && $l->sourced_cost !== null && abs((float)$l->sourced_cost - $cost) < 0.005));
    if ($same) return;
    $up = $by ? ['sourced_by' => $by, 'sourced_cost' => $cost, 'sourced_at' => now(), 'sourced_status' => in_array($role, ['super', 'admin'], true) ? 'approved' : 'pending', 'sourced_checked_by' => in_array($role, ['super', 'admin'], true) ? $me : null, 'sourced_checked_at' => in_array($role, ['super', 'admin'], true) ? now() : null]
      : ['sourced_by' => null, 'sourced_cost' => null, 'sourced_at' => null, 'sourced_status' => null, 'sourced_checked_by' => null, 'sourced_checked_at' => null];
    DB::table('order_items')->where('id', $id)->update($up);
    try { DB::table('audit_logs')->insert(['user_id' => $me, 'user_name' => optional(auth()->user())->name, 'action' => 'update', 'subject' => 'sourcing_entry.'.$id.' by='.($by ?? 'none').' cost='.($cost ?? '-'), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
}
