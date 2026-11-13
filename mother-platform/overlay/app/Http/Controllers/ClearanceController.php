<?php
namespace App\Http\Controllers;

use App\Support\Clearance as C;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Products & Inventory -> Clearance: owner-approved sale at purchase cost for stock that is about to be lost. */
class ClearanceController extends Controller
{
  private function guard(): void { abort_unless(\App\Support\ProfitFloor::admin(), 403); abort_unless(C::ready(), 404); }

  public function index(Request $r) {
    $this->guard();
    $rows = DB::table('product_clearances as c')->leftJoin('products as p', 'p.id', '=', 'c.product_id')->orderByRaw("case c.status when 'pending' then 0 when 'active' then 1 else 2 end")->orderByDesc('c.id')->limit(200)
      ->get(['c.*', 'p.name', 'p.sku', 'p.stock', 'p.price as cur_price']);
    foreach ($rows as $x) $x->st = in_array($x->status, ['active', 'ended'], true) ? C::stats($x) : ['qty' => 0, 'rev' => 0.0, 'loss' => 0.0];
    $pre = null; $pid = (int)$r->query('product', 0);
    if ($pid && ($p = DB::table('products')->where('id', $pid)->first(['id', 'name', 'sku', 'price', 'stock']))) { $pre = $p; $pre->cost = C::cost($pid); }
    $pack = 30.0; try { $pack = (float)(DB::table('settings')->where('key', 'packaging_cost')->value('value') ?: 30); } catch (\Throwable $e) {}
    return view('clearance', ['rows' => $rows, 'pre' => $pre, 'super' => C::isSuper(), 'pack' => $pack]);
  }
  /** Find a product by ID, SKU or name for the form (cost and price included). */
  public function find(Request $r) {
    $this->guard(); $q = trim((string)$r->query('q', '')); if (mb_strlen($q) < 2) return response()->json(['rows' => []]);
    $b = DB::table('products')->limit(8);
    if (ctype_digit($q)) $b->where(fn($w) => $w->where('id', (int)$q)->orWhere('sku', 'ilike', '%' . $q . '%')); else $b->where(fn($w) => $w->where('name', 'ilike', '%' . $q . '%')->orWhere('sku', 'ilike', '%' . $q . '%'));
    $out = []; foreach ($b->orderBy('name')->get(['id', 'name', 'sku', 'price', 'stock']) as $p) $out[] = ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'price' => (float)$p->price, 'stock' => $p->stock, 'cost' => round(C::cost((int)$p->id), 2), 'on' => C::on((int)$p->id)];
    return response()->json(['rows' => $out]);
  }
  public function store(Request $r) {
    $this->guard();
    $d = $r->validate(['product_id' => 'required|integer', 'price' => 'required|numeric|min:1', 'reason' => 'required|string|max:40', 'ends_on' => 'nullable|date|after_or_equal:today', 'note' => 'nullable|string|max:300', 'ack' => 'accepted'],
      ['ack.accepted' => 'নিশ্চিত করুন: কেনা দামে বিক্রি করলে ডেলিভারি চার্জ ও প্যাকেজিং খরচ কোম্পানির লস হবে।']);
    [$ok, $msg] = C::request((int)$d['product_id'], (float)$d['price'], (string)$d['reason'], $d['ends_on'] ?? null, $d['note'] ?? null);
    return redirect('/admin/clearance')->with($ok ? 'ok' : 'err', $msg);
  }
  public function act(Request $r, int $id) {
    $this->guard(); $a = (string)$r->input('action');
    if ($a === 'approve') [$ok, $msg] = C::approve($id);
    elseif ($a === 'reject') [$ok, $msg] = C::reject($id);
    elseif ($a === 'end') { if (!C::isSuper()) { $ok = false; $msg = 'Only the Super Admin can end a clearance.'; } else [$ok, $msg] = C::end($id, 'Ended by ' . (auth()->user()->name ?? 'Super Admin')); }
    else { $ok = false; $msg = 'Unknown action.'; }
    return redirect('/admin/clearance')->with($ok ? 'ok' : 'err', $msg);
  }
}
