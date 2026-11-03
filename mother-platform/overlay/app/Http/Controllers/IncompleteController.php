<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Incomplete checkouts live inside the Orders page (status chip "Incomplete"). They are NOT orders: nothing is confirmed, no SMS, no stock, no courier until an admin converts one. */
class IncompleteController extends Controller {
  const TABS = ['open'=>'Open', 'recovered'=>'Recovered', 'converted'=>'Converted to order', 'archived'=>'Archived'];
  /** product id => image for the given incomplete rows */
  public static function imgs($rows): array {
    $ids = []; foreach ($rows as $o) foreach (json_decode($o->items ?: '[]', true) ?: [] as $i) if (!empty($i['id'])) $ids[] = (int)$i['id'];
    return $ids ? DB::table('products')->whereIn('id', array_unique($ids))->pluck('image', 'id')->all() : [];
  }
  /** Open incomplete checkouts merged into All Orders by creation time: returns [order id => rows to print before that order] plus 'tail' (older than the last order on the last page). */
  public static function mix(Request $r, $orders, ?array $range): array {
    $none = ['incHtml'=>[], 'incTailHtml'=>'', 'incTop'=>collect(), 'incTopImgs'=>[]];
    if ($r->filled('status') || $r->filled('print') || !\Illuminate\Support\Facades\Schema::hasTable('incomplete_orders')) return $none;
    $items = collect($orders->items()); $first = $items->first(); $last = $items->last();
    $q = DB::table('incomplete_orders')->where('status', 'open');
    if ($r->filled('q')) { $qs = trim((string)$r->q); $qd = preg_replace('/\D/', '', $qs); $q->where(fn($w) => $w->where('phone', 'like', '%'.($qd !== '' ? $qd : $qs).'%')->orWhere('name', 'ilike', '%'.$qs.'%')); }
    if ($range) { if ($range[0]) $q->where('created_at', '>=', $range[0]); if ($range[1]) $q->where('created_at', '<=', $range[1]); }
    $pg = (int)$r->query('page', 1);
    if ($pg > 1 && $first) $q->where('created_at', '<', $first->created_at);   // belongs to an earlier page
    if ($orders->hasMorePages() && $last) $q->where('created_at', '>=', $last->created_at); // belongs to a later page
    $rows = $q->orderByDesc('created_at')->orderByDesc('id')->limit(200)->get();
    $imgs = self::imgs($rows); $html = []; $tailHtml = '';
    $draw = fn($row) => view('orders_inc_row', ['withPay'=>true, 'o'=>$row, 'imgs'=>$imgs, 'cid'=>null])->render();
    foreach ($rows as $row) {
      $target = $items->first(fn($o) => $o->created_at <= $row->created_at);
      if ($target) $html[$target->id] = ($html[$target->id] ?? '').$draw($row); else $tailHtml .= $draw($row);
    }
    return ['incHtml'=>$html, 'incTailHtml'=>$tailHtml, 'incTop'=>collect(), 'incTopImgs'=>[]];
  }
  public static function data(Request $r): array {
    $st = array_key_exists($r->query('inc'), self::TABS) ? $r->query('inc') : 'open'; $q = trim((string)$r->query('q', ''));
    $rows = DB::table('incomplete_orders')->where('status', $st)->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('phone', 'like', '%'.$q.'%')->orWhere('name', 'ilike', '%'.$q.'%')))->orderByDesc('created_at')->orderByDesc('id')->paginate(\App\Support\Pg::per(24))->withQueryString();
    $counts = DB::table('incomplete_orders')->select('status', DB::raw('count(*) n'))->groupBy('status')->pluck('n', 'status');
    $open = DB::table('incomplete_orders')->where('status', 'open')->selectRaw('coalesce(sum(total),0) s')->first();
    $ph = collect($rows->items())->pluck('phone')->all();
    $cust = $ph ? DB::table('customers')->whereIn('phone', $ph)->pluck('id', 'phone') : collect();
    return ['incRows'=>$rows, 'incSt'=>$st, 'incCounts'=>$counts, 'incSum'=>(float)$open->s, 'incTabs'=>self::TABS, 'incCust'=>$cust, 'incImgs'=>self::imgs($rows->items())];
  }
  /** Read-only detail page of one incomplete checkout. */
  public function show($id) {
    $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404);
    return view('incomplete_show', ['o'=>$o, 'items'=>json_decode($o->items ?: '[]', true) ?: [], 'imgs'=>self::imgs([$o]), 'cust'=>DB::table('customers')->where('phone', $o->phone)->value('id')]);
  }
  public function edit($id) {
    $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404);
    return view('incomplete_edit', ['o'=>$o, 'items'=>json_decode($o->items ?: '[]', true) ?: [], 'imgs'=>self::imgs([$o]),
      'cities'=>DB::table('districts')->where('active', true)->orderBy('name')->pluck('name')->all()]);
  }
  /** Full edit: contact fields, note, item quantities/removal and delivery charge. Totals are recomputed here, never trusted from the browser. */
  public function update(Request $r, $id) {
    $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404);
    if ($o->status === 'converted') return back()->with('bad', 'This checkout is already converted to an order - edit the order instead.');
    $d = $r->validate(['name'=>'nullable|string|max:120', 'phone'=>['required', 'string', new \App\Support\PhoneRule], 'email'=>'nullable|string|max:120', 'address'=>'nullable|string|max:500', 'district'=>'nullable|string|max:80',
      'note'=>'nullable|string|max:500', 'shipping'=>'nullable|numeric|min:0|max:100000', 'qty'=>'nullable|array', 'qty.*'=>'nullable|integer|min:0|max:999']);
    $items = json_decode($o->items ?: '[]', true) ?: []; $out = []; $sub = 0; $q = 0;
    foreach ($items as $k => $i) {
      $n = array_key_exists($k, $d['qty'] ?? []) ? (int)$d['qty'][$k] : (int)($i['qty'] ?? 0);
      if ($n <= 0) continue; $i['qty'] = $n; $out[] = $i; $sub += (float)($i['price'] ?? 0) * $n; $q += $n;
    }
    if (!$out) return back()->withInput()->with('bad', 'At least one product must stay in the checkout. Use Archive to remove the whole entry.');
    $ship = array_key_exists('shipping', $d) && $d['shipping'] !== null ? (float)$d['shipping'] : (float)$o->shipping;
    $disc = min((float)$o->discount, $sub);
    DB::table('incomplete_orders')->where('id', $id)->update(['name'=>trim((string)($d['name'] ?? '')) ?: null, 'phone'=>$d['phone'], 'email'=>trim((string)($d['email'] ?? '')) ?: null,
      'address'=>trim((string)($d['address'] ?? '')) ?: null, 'district'=>trim((string)($d['district'] ?? '')) ?: null, 'note'=>trim((string)($d['note'] ?? '')) ?: null,
      'items'=>json_encode($out, JSON_UNESCAPED_UNICODE), 'qty'=>$q, 'subtotal'=>$sub, 'discount'=>$disc, 'shipping'=>$ship, 'total'=>max(0, $sub - $disc + $ship), 'updated_at'=>now()]);
    return redirect('/admin/incomplete/'.$id)->with('ok', 'Saved');
  }
  /** Quick edit popup: contact details + note only (JSON). */
  public function quick(Request $r, $id) {
    $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404);
    if ($o->status === 'converted') return response()->json(['ok'=>false, 'message'=>'Already converted to an order'], 422);
    $d = $r->validate(['name'=>'nullable|string|max:120', 'phone'=>['required', 'string', new \App\Support\PhoneRule], 'address'=>'nullable|string|max:500', 'note'=>'nullable|string|max:500']);
    DB::table('incomplete_orders')->where('id', $id)->update(['name'=>trim((string)($d['name'] ?? '')) ?: null, 'phone'=>$d['phone'], 'address'=>trim((string)($d['address'] ?? '')) ?: null, 'note'=>trim((string)($d['note'] ?? '')) ?: null, 'updated_at'=>now()]);
    return response()->json(['ok'=>true, 'message'=>'Saved']);
  }
  public function act(Request $r, $id, $act) {
    $o = DB::table('incomplete_orders')->find($id); abort_unless($o, 404);
    DB::table('incomplete_orders')->where('id', $id)->update(['status'=>['recovered'=>'recovered', 'archive'=>'archived', 'reopen'=>'open'][$act], 'updated_at'=>now()]);
    return redirect('/admin/orders?status=Incomplete&inc='.($act === 'reopen' ? 'open' : $o->status));
  }
}
