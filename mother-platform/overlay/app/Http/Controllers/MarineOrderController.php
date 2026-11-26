<?php
namespace App\Http\Controllers;

use App\Support\MarineAccess as A;
use App\Support\MarineOrders as O;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Marine Supply: requisitions, USD quotations, purchase orders, crew personal orders and port calls.
 *  Portal side (/marine-portal/...): every read and action is checked against what the signed-in account may see (MarineOrders rules).
 *  Desk side (/admin/marine/orders, /admin/marine/port-calls): the desk prices the quotation and moves the order; each step is in the audit log. */
class MarineOrderController extends Controller
{
  private function pg(string $view, array $d = [], int $code = 200) { return response(view('mp.' . $view, $d + ['u' => A::user(), 'nav' => true]), $code)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Frame-Options', 'DENY'); }
  private function me(): ?object { return O::ready() ? A::user() : null; }
  private function bd(?string $v): ?\Carbon\Carbon { try { return $v ? \Carbon\Carbon::parse($v, 'Asia/Dhaka')->setTimezone(config('app.timezone')) : null; } catch (\Throwable $e) { return null; } }
  private function find(object $u, $id): ?object { $o = DB::table('marine_orders')->where('id', (int)$id)->first(); return ($o && O::canSee($u, $o, O::scopeOf($u))) ? $o : null; }

  // ================= portal: requisitions and orders =================
  public function orders(Request $r) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    $st = isset(O::STATUS[$r->query('status')]) ? $r->query('status') : '';
    $rows = O::visible($u)->when($st !== '', fn($w) => $w->where('status', $st))->orderByDesc('id')->paginate(30)->withQueryString();
    return $this->pg('orders', ['rows' => $rows, 'st' => $st, 'status' => O::STATUS, 'ports' => O::PORTS, 'canVessel' => O::canRaise($u, 'vessel'), 'canPersonal' => O::canRaise($u, 'personal'), 'crew' => O::isCrew($u)]);
  }
  public function orderNew(Request $r) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    $kind = ($r->query('kind') === 'personal' || O::isCrew($u)) ? 'personal' : 'vessel'; if (!O::canRaise($u, $kind)) return redirect('/marine-portal/orders')->with('bad', 'Your account cannot raise this kind of request.');
    $pc = null; if ($r->query('pc')) { $pc = O::callsVisible($u)->where('id', (int)$r->query('pc'))->first(); }
    return $this->pg('order_new', ['add' => max(0, (int)$r->query('add')), 'kind' => $kind, 'vessels' => O::vessels($u, false), 'ports' => O::PORTS, 'places' => O::PLACES, 'pc' => $pc, 'max' => O::MAX_LINES, 'pre' => ['imo' => preg_match('/^\d{7}$/', (string)$r->query('imo')) ? (string)$r->query('imo') : '', 'vessel' => mb_substr(trim(strip_tags((string)$r->query('vessel'))), 0, 120), 'port' => isset(O::PORTS[(string)$r->query('port')]) ? (string)$r->query('port') : '']]);
  }
  /** Catalog search for a signed-in account (bonded goods included, baby goods never). List prices only; the quotation sets the price. */
  public function products(Request $r) {
    $u = $this->me(); if (!$u) return response()->json(['items' => []], 401);
    $q = mb_substr(trim((string)$r->query('q')), 0, 60); if (mb_strlen($q) < 2 && (int)$r->query('id') < 1) return response()->json(['items' => []]);
    [$hid, $bok] = O::limits($u); $b = O::products($q, $hid, 12, $bok); if ((int)$r->query('id') > 0) $b = O::products('', $hid, 1, $bok)->where('p.id', (int)$r->query('id'));
    $rows = $b->get(['p.id', 'p.sku', 'p.name', 'p.size', 'm.usd_price']);
    return response()->json(['items' => $rows->map(fn($x) => ['id' => (int)$x->id, 'sku' => (string)$x->sku, 'name' => (string)$x->name, 'size' => (string)($x->size ?? ''), 'usd' => number_format((float)$x->usd_price, 2, '.', '')])->all()])->header('Cache-Control', 'no-store, private');
  }
  /** Catalog for a signed-in account, by marine category. Bonded Stores opens only with bonded approval; without it the tab explains how to get it. */
  public function catalog(Request $r) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    $MC = \App\Support\MarineCats::class; [$hid, $bok] = O::limits($u); $mc = ($MC::ready() && $MC::ok($r->query('mcat'))) ? (string)$r->query('mcat') : ''; $ms = (string)$MC::sub($mc, $r->query('sub')); $q = mb_substr(trim((string)$r->query('q')), 0, 60);
    $locked = $mc === 'bonded' && !$bok; $rows = null;
    if (!$locked) { $b = O::products($q, $hid, 48, $bok)->leftJoin('categories as c', 'c.id', '=', 'p.category_id'); if ($mc !== '') $b->where('m.mcat', $mc); if ($ms !== '') $b->where('m.msub', $ms);
      $rows = $b->limit(100000)->select('p.id', 'p.sku', 'p.name', 'p.size', 'p.image', 'm.usd_price', 'm.mcat', 'm.msub', 'c.name as cat')->paginate(48)->withQueryString(); }
    return $this->pg('catalog', ['rows' => $rows, 'mc' => $mc, 'ms' => $ms, 'q' => $q, 'cats' => $MC::CATS, 'locked' => $locked, 'bok' => $bok, 'canOrder' => O::canRaise($u, O::isCrew($u) ? 'personal' : 'vessel'), 'crew' => O::isCrew($u)]);
  }
  public function orderStore(Request $r) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    $kind = ($r->input('kind') === 'personal' || O::isCrew($u)) ? 'personal' : 'vessel'; if (!O::canRaise($u, $kind)) abort(403);
    $bad = fn($m) => back()->withInput()->with('bad', $m);
    if (DB::table('marine_orders')->where('account_id', $u->id)->where('created_at', '>', now()->subDay())->count() >= 12) return $bad('You have sent many requests today. Please contact our marine desk.');
    $port = isset(O::PORTS[$r->input('port')]) ? $r->input('port') : null; if (!$port) return $bad('Choose the port.');
    $place = isset(O::PLACES[$r->input('place')]) ? $r->input('place') : 'unknown'; $eta = $this->bd($r->input('eta'));
    $imo = null; $vessel = null; $declared = false;
    if ($u->type === 'vessel') { $imo = $u->imo ?: null; $vessel = $u->vessel; if ($kind === 'vessel' && !$imo) return $bad('Your account has no vessel IMO number. Please contact our marine desk.'); }
    else {
      $imo = O::cleanImo($r->input('imo')); if (!$imo) return $bad('Choose the vessel, or enter its 7-digit IMO number.');
      $v = DB::table('marine_account_vessels')->where('account_id', $u->id)->where('imo', $imo)->first();
      if ($v) $vessel = $v->name; else { $vessel = mb_substr(trim(strip_tags((string)$r->input('vessel'))), 0, 120) ?: null; if (!$vessel) return $bad('Enter the name of the vessel for a new IMO number.'); $declared = true; }
    }
    // lines: catalog products are re-read from the database (name and list price never come from the browser); free-text items keep the buyer's wording
    $in = array_slice(array_values(array_filter((array)$r->input('lines', []), 'is_array')), 0, O::MAX_LINES); $lines = []; $pids = [];
    foreach ($in as $l) if (!empty($l['product_id'])) $pids[] = (int)$l['product_id'];
    [$hid, $bok] = O::limits($u); // bonded goods reach a requisition only from an account with bonded approval
    $prod = $pids ? O::products('', $hid, 500, $bok)->whereIn('p.id', array_unique($pids))->get(['p.id', 'p.sku', 'p.name', 'p.size', 'm.usd_price'])->keyBy('id') : collect();
    foreach ($in as $l) {
      $q = O::qty($l['qty'] ?? null); if ($q === null) continue; $note = mb_substr(trim(strip_tags((string)($l['note'] ?? ''))), 0, 200) ?: null; $unit = mb_substr(trim(strip_tags((string)($l['unit'] ?? ''))), 0, 20) ?: null;
      if (!empty($l['product_id'])) { $p = $prod[(int)$l['product_id']] ?? null; if (!$p) continue; $lines[] = ['product_id' => (int)$p->id, 'sku' => mb_substr((string)$p->sku, 0, 60), 'name' => mb_substr((string)$p->name, 0, 200), 'size' => mb_substr((string)($p->size ?? ''), 0, 80) ?: null, 'qty' => $q, 'unit' => $unit, 'list_usd' => $p->usd_price, 'note' => $note]; }
      else { $name = mb_substr(trim(strip_tags((string)($l['name'] ?? ''))), 0, 200); if (mb_strlen($name) < 2) continue; $lines[] = ['product_id' => null, 'sku' => null, 'name' => $name, 'size' => null, 'qty' => $q, 'unit' => $unit, 'list_usd' => null, 'note' => $note]; }
    }
    $files = array_slice(array_values(array_filter((array)$r->file('files', []))), 0, O::FILE_COUNT);
    foreach ($files as $f) { $why = O::fileProblem((string)$f->getClientOriginalExtension(), $f->isValid() ? $f->getMimeType() : null, $f->isValid() ? (int)$f->getSize() : 0); if ($why) return $bad($f->getClientOriginalName() . ': ' . $why); }
    if (!$lines && !$files) return $bad('Add at least one item, or attach your store list.');
    $pcId = null; if ($r->input('port_call_id')) { $pc = O::callsVisible($u)->where('id', (int)$r->input('port_call_id'))->first(); if ($pc && (!$imo || $pc->imo === $imo)) $pcId = (int)$pc->id; }
    $id = DB::transaction(function () use ($u, $kind, $imo, $vessel, $port, $place, $eta, $r, $lines, $declared, $pcId) {
      if ($declared) DB::table('marine_account_vessels')->insertOrIgnore(['account_id' => $u->id, 'imo' => $imo, 'name' => $vessel, 'note' => 'Declared by the account, not verified', 'verified' => false, 'added_by' => $u->username, 'created_at' => now(), 'updated_at' => now()]);
      $id = DB::table('marine_orders')->insertGetId(['kind' => $kind, 'account_id' => $u->id, 'port_call_id' => $pcId, 'imo' => $imo, 'vessel' => $vessel, 'port' => $port, 'place' => $place, 'eta' => $eta, 'status' => 'requested', 'note' => mb_substr(trim(strip_tags((string)$r->input('note'))), 0, 1000) ?: null, 'created_at' => now(), 'updated_at' => now()]);
      DB::table('marine_orders')->where('id', $id)->update(['number' => O::number($id)]);
      foreach ($lines as $l) DB::table('marine_order_lines')->insert($l + ['order_id' => $id, 'available' => true, 'created_at' => now(), 'updated_at' => now()]);
      return $id;
    });
    // attached files: every one is kept with the order; Excel, CSV and Word tables are also read into order lines (exact catalog matches get the list price, the rest keep the customer's wording)
    $read = []; $room = O::TOTAL_LINES - count($lines);
    foreach ($files as $f) {
      try {
        [$did, $path, $why] = O::storeFile($f, (int)$id, (int)$u->id); if (!$did) { $read[] = $f->getClientOriginalName() . ': not kept (' . $why . ')'; continue; }
        $ext = strtolower((string)$f->getClientOriginalExtension()); if (!\App\Support\MarineSheet::kind($ext)) { $read[] = $f->getClientOriginalName() . ': attached for our desk'; continue; }
        [$rows, $err] = \App\Support\MarineSheet::rows(\Illuminate\Support\Facades\Storage::disk('local')->path($path), $ext); $x = \App\Support\MarineSheet::items($rows);
        $take = array_slice($x['items'], 0, max(0, $room)); $over = count($x['items']) - count($take) + $x['more']; $room -= count($take); $n = 0; $m = 0;
        foreach (O::match($take, $hid, $bok) as $l) { DB::table('marine_order_lines')->insert($l + ['order_id' => $id, 'available' => true, 'created_at' => now(), 'updated_at' => now()]); $n++; if ($l['product_id']) $m++; }
        $read[] = $f->getClientOriginalName() . ': ' . ($n ? $n . ' item(s) read, ' . $m . ' matched to our catalog' : ($err ?: 'no item with a quantity was found; attached for our desk')) . ($x['skipped'] ? ', ' . $x['skipped'] . ' row(s) without a quantity left out' : '') . ($over > 0 ? ', ' . $over . ' more row(s) not read (limit ' . O::TOTAL_LINES . ' per requisition)' : '');
      } catch (\Throwable $e) { report($e); $read[] = $f->getClientOriginalName() . ': attached, but it could not be read automatically'; }
    }
    if ($read) session()->flash('info', implode("\n", $read));
    try { \Illuminate\Support\Facades\Cache::forget('sb.counts.v3'); } catch (\Throwable $e) {}
    return redirect('/marine-portal/orders/' . $id)->with('ok', 'Your request was sent. Our marine desk will send a quotation in USD.');
  }
  public function orderShow($id) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    $o = $this->find($u, $id); if (!$o) abort(404);
    $by = DB::table('marine_accounts')->where('id', $o->account_id)->first(['username', 'name', 'company', 'type']);
    return $this->pg('order', ['files' => DB::table('marine_documents')->where('order_id', $o->id)->orderBy('id')->get(['id', 'name', 'size']), 'o' => $o, 'lines' => DB::table('marine_order_lines')->where('order_id', $o->id)->orderBy('id')->get(), 'by' => $by, 'status' => O::STATUS, 'ports' => O::PORTS, 'places' => O::PLACES,
      'canAccept' => O::canAccept($u, $o, O::scopeOf($u)), 'canWithdraw' => O::canWithdraw($u, $o), 'expired' => $o->status === 'quoted' && O::expired($o), 'captainWait' => $o->status === 'quoted' && $o->kind === 'vessel' && $u->type === 'vessel']);
  }
  /** A file attached to an order: only for an account that may see that order. */
  public function orderFile($id) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    $d = DB::table('marine_documents')->where('id', (int)$id)->whereNotNull('order_id')->first(); if (!$d || !$this->find($u, $d->order_id) || !\Illuminate\Support\Facades\Storage::disk('local')->exists($d->path)) abort(404);
    return \Illuminate\Support\Facades\Storage::disk('local')->download($d->path, $d->name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
  }
  /** accept = place the purchase order; decline; withdraw (own request, before a purchase order). The rule is re-checked here, whatever the page showed. */
  public function orderAct(Request $r, $id) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    $o = $this->find($u, $id); if (!$o) abort(404); $do = (string)$r->input('action'); $back = '/marine-portal/orders/' . $o->id; $who = $u->username . ' (' . ($u->name ?: $u->company) . ')';
    $log = fn($a, $s, $p, $n) => DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'marine: ' . $u->username, 'action' => 'marine.order.' . $a, 'subject' => $s, 'meta' => json_encode(['previous' => $p, 'new' => $n]), 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
    if ($do === 'accept') {
      if (!O::canAccept($u, $o, O::scopeOf($u))) return redirect($back)->with('bad', O::expired($o) ? 'This quotation has expired. Ask our marine desk for a new one.' : 'Your account cannot place this purchase order.');
      if (!$r->input('agree')) return redirect($back)->with('bad', 'Tick the box to confirm the purchase order.');
      $n = DB::table('marine_orders')->where('id', $o->id)->where('status', 'quoted')->update(['status' => 'ordered', 'po_ref' => mb_substr(trim(strip_tags((string)$r->input('po_ref'))), 0, 60) ?: null, 'ordered_by' => mb_substr($who, 0, 160), 'ordered_at' => now(), 'updated_at' => now()]);
      if ($n) { try { $log('ordered', 'Marine purchase order placed for ' . $o->number . ' (USD ' . $o->total . ')', 'quoted', 'ordered'); } catch (\Throwable $e) {} }
      return redirect($back)->with('ok', $n ? 'Purchase order placed. Our marine desk will confirm the delivery time.' : 'This quotation was already answered.');
    }
    if ($do === 'decline') {
      if (!O::canAccept($u, $o, O::scopeOf($u)) && !($o->status === 'quoted' && O::canWithdraw($u, $o))) return redirect($back)->with('bad', 'Your account cannot answer this quotation.');
      $n = DB::table('marine_orders')->where('id', $o->id)->where('status', 'quoted')->update(['status' => 'declined', 'closed_at' => now(), 'closed_by' => mb_substr($who, 0, 160), 'close_reason' => mb_substr(trim(strip_tags((string)$r->input('reason'))), 0, 300) ?: null, 'updated_at' => now()]);
      if ($n) { try { $log('declined', 'Marine quotation declined: ' . $o->number, 'quoted', 'declined'); } catch (\Throwable $e) {} }
      return redirect($back)->with('ok', 'Quotation declined.');
    }
    if ($do === 'withdraw') {
      if (!O::canWithdraw($u, $o)) return redirect($back)->with('bad', 'This request can no longer be withdrawn here. Please contact our marine desk.');
      $n = DB::table('marine_orders')->where('id', $o->id)->whereIn('status', ['requested', 'quoted'])->update(['status' => 'cancelled', 'closed_at' => now(), 'closed_by' => mb_substr($who, 0, 160), 'close_reason' => 'Withdrawn by the account', 'updated_at' => now()]);
      if ($n) { try { $log('withdrawn', 'Marine request withdrawn: ' . $o->number, $o->status, 'cancelled'); \Illuminate\Support\Facades\Cache::forget('sb.counts.v3'); } catch (\Throwable $e) {} }
      return redirect($back)->with('ok', 'Request withdrawn.');
    }
    return redirect($back);
  }

  // ================= portal: port calls =================
  public function calls() {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    return $this->pg('calls', ['rows' => O::callsVisible($u)->orderByRaw("case when status in ('expected','arrived') then 0 else 1 end")->orderByDesc('eta')->paginate(30), 'vessels' => O::vessels($u, false), 'ports' => O::PORTS, 'places' => O::PLACES, 'call' => O::CALL, 'canAdd' => !O::isCrew($u), 'canOrder' => O::canRaise($u, 'vessel')]);
  }
  public function callStore(Request $r) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login'); if (O::isCrew($u)) abort(403);
    $bad = fn($m) => back()->withInput()->with('bad', $m);
    $port = isset(O::PORTS[$r->input('port')]) ? $r->input('port') : null; if (!$port) return $bad('Choose the port.');
    $eta = $this->bd($r->input('eta')); if (!$eta) return $bad('Enter the expected time of arrival.'); $etd = $this->bd($r->input('etd')); if ($etd && $etd->lessThan($eta)) return $bad('The departure cannot be before the arrival.');
    if ($u->type === 'vessel') { $imo = $u->imo; $vessel = $u->vessel; if (!$imo) return $bad('Your account has no vessel IMO number.'); }
    else {
      $imo = O::cleanImo($r->input('imo')); if (!$imo) return $bad('Choose the vessel, or enter its 7-digit IMO number.');
      $v = DB::table('marine_account_vessels')->where('account_id', $u->id)->where('imo', $imo)->first(); $vessel = $v ? $v->name : (mb_substr(trim(strip_tags((string)$r->input('vessel'))), 0, 120) ?: null);
      if (!$v) { if (!$vessel) return $bad('Enter the name of the vessel for a new IMO number.'); DB::table('marine_account_vessels')->insertOrIgnore(['account_id' => $u->id, 'imo' => $imo, 'name' => $vessel, 'note' => 'Declared by the account, not verified', 'verified' => false, 'added_by' => $u->username, 'created_at' => now(), 'updated_at' => now()]); }
    }
    if (DB::table('marine_port_calls')->where('account_id', $u->id)->where('created_at', '>', now()->subDay())->count() >= 20) return $bad('You have added many port calls today. Please contact our marine desk.');
    $f = fn($k, $n) => mb_substr(trim(strip_tags((string)$r->input($k))), 0, $n) ?: null;
    DB::table('marine_port_calls')->insert(['account_id' => $u->id, 'imo' => $imo, 'vessel' => $vessel, 'port' => $port, 'place' => isset(O::PLACES[$r->input('place')]) ? $r->input('place') : 'unknown', 'berth' => $f('berth', 80), 'eta' => $eta, 'etd' => $etd, 'agent_name' => $f('agent_name', 120), 'agent_contact' => $f('agent_contact', 120), 'status' => 'expected', 'note' => $f('note', 400), 'updated_by' => $u->username, 'created_at' => now(), 'updated_at' => now()]);
    return redirect('/marine-portal/port-calls')->with('ok', 'Port call added.');
  }
  public function callCancel($id) {
    $u = $this->me(); if (!$u) return redirect('/marine-portal/login');
    DB::table('marine_port_calls')->where('id', (int)$id)->where('account_id', $u->id)->where('status', 'expected')->update(['status' => 'cancelled', 'updated_by' => $u->username, 'updated_at' => now()]);
    return redirect('/marine-portal/port-calls')->with('ok', 'Port call cancelled.');
  }

  // ================= desk =================
  public function adminOrders(Request $r) {
    if (!O::ready()) return view('marine.orders', ['ready' => false]);
    $st = $r->query('status'); $st = ($st === 'all' || isset(O::STATUS[$st])) ? $st : 'requested'; $kind = in_array($r->query('kind'), ['vessel', 'personal'], true) ? $r->query('kind') : '';
    $n = DB::table('marine_orders')->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
    $rows = DB::table('marine_orders as o')->leftJoin('marine_accounts as a', 'a.id', '=', 'o.account_id')->when($st !== 'all', fn($w) => $w->where('o.status', $st))->when($kind !== '', fn($w) => $w->where('o.kind', $kind))
      ->orderByDesc('o.id')->select('o.*', 'a.username', 'a.company', 'a.name as person', 'a.type as atype', 'a.vessel_role')->paginate(40)->withQueryString();
    return view('marine.orders', ['ready' => true, 'rows' => $rows, 'st' => $st, 'kind' => $kind, 'n' => $n, 'status' => O::STATUS, 'ports' => O::PORTS, 'types' => A::TYPES]);
  }
  public function adminOrder($id) {
    if (!O::ready()) return redirect('/admin/marine/orders');
    $o = DB::table('marine_orders')->where('id', (int)$id)->first(); if (!$o) return redirect('/admin/marine/orders');
    $lines = DB::table('marine_order_lines')->where('order_id', $o->id)->orderBy('id')->get(); $acc = DB::table('marine_accounts')->where('id', $o->account_id)->first();
    $rate = MarineController::rate(); $pct = \App\Support\ProfitFloor::percent(); $floor = []; $pids = $lines->pluck('product_id')->filter()->unique()->all();
    foreach ($pids ? DB::table('products')->whereIn('id', $pids)->get() : [] as $p) $floor[$p->id] = O::floorUsd(\App\Support\ProfitFloor::base($p), $pct, $rate);
    $vv = ($acc && $o->imo && $acc->type !== 'vessel') ? DB::table('marine_account_vessels')->where('account_id', $acc->id)->where('imo', $o->imo)->value('verified') : true;
    return view('marine.order', ['o' => $o, 'lines' => $lines, 'acc' => $acc, 'floor' => $floor, 'rate' => $rate, 'pct' => $pct, 'status' => O::STATUS, 'ports' => O::PORTS, 'places' => O::PLACES, 'next' => O::NEXT[$o->status] ?? [], 'types' => A::TYPES, 'vroles' => A::VROLES,
      'files' => DB::table('marine_documents')->where('order_id', $o->id)->orderBy('id')->get(), 'verified' => $vv === null ? false : (bool)$vv, 'admin' => \App\Support\ProfitFloor::admin(), 'pc' => $o->port_call_id ? DB::table('marine_port_calls')->where('id', $o->port_call_id)->first() : null, 'mails' => DB::table('marine_invites')->where('account_id', $o->account_id)->where('kind', 'quotation')->orderByDesc('id')->limit(3)->get()]);
  }
  /** The desk prices the lines and sends the quotation. A unit price under the protected margin needs an admin and an explicit override. */
  public function adminQuote(Request $r, $id) {
    $o = DB::table('marine_orders')->where('id', (int)$id)->first(); if (!$o || !O::ready()) return redirect('/admin/marine/orders'); $back = '/admin/marine/orders/' . $o->id;
    if (!in_array($o->status, ['requested', 'quoted'], true)) return redirect($back)->with('ok', 'A quotation can no longer be changed for this order.');
    $lines = DB::table('marine_order_lines')->where('order_id', $o->id)->orderBy('id')->get(); $in = (array)$r->input('l', []); $rate = MarineController::rate(); $pct = \App\Support\ProfitFloor::percent();
    if ((int)$r->input('_n') !== $lines->count()) return redirect($back)->with('ok', 'The form did not arrive completely (or the lines changed). Nothing was saved; open the order again and resend.'); // a long list must never be half-saved
    $prod = DB::table('products')->whereIn('id', $lines->pluck('product_id')->filter()->unique()->all() ?: [0])->get()->keyBy('id'); $upd = []; $calc = []; $low = []; $any = false;
    foreach ($lines as $l) {
      $x = (array)($in[$l->id] ?? []); $av = !empty($x['available']); $price = (isset($x['unit_usd']) && is_numeric($x['unit_usd']) && (float)$x['unit_usd'] >= 0 && (float)$x['unit_usd'] < 1000000) ? round((float)$x['unit_usd'], 2) : null;
      if ($av && $price === null) return redirect($back)->withInput()->with('ok', 'Enter a USD unit price for every available line (' . $l->name . ').');
      if ($av) { $any = true; $fl = ($l->product_id && isset($prod[$l->product_id])) ? O::floorUsd(\App\Support\ProfitFloor::base($prod[$l->product_id]), $pct, $rate) : 0.0; if ($fl > 0 && $price + 0.004 < $fl) $low[] = $l->name . ' (USD ' . number_format($price, 2) . ', lowest USD ' . number_format($fl, 2) . ')'; }
      $upd[$l->id] = ['unit_usd' => $av ? $price : null, 'available' => $av, 'note' => mb_substr(trim((string)($x['note'] ?? '')), 0, 200) ?: $l->note, 'updated_at' => now()]; $calc[] = ['qty' => $l->qty, 'unit_usd' => $av ? $price : null, 'available' => $av];
    }
    if (!$any) return redirect($back)->withInput()->with('ok', 'Mark at least one line as available, or cancel the request instead.');
    $over = false; if ($low) { if (!\App\Support\ProfitFloor::admin() || !$r->input('override')) return redirect($back)->withInput()->with('ok', 'Below the protected margin of ' . \App\Support\ProfitFloor::pct() . '%: ' . implode('; ', array_slice($low, 0, 5)) . '. Raise the price, or an admin ticks the override box.'); $over = true; }
    $del = (is_numeric($r->input('delivery_charge')) && (float)$r->input('delivery_charge') >= 0 && (float)$r->input('delivery_charge') < 1000000) ? round((float)$r->input('delivery_charge'), 2) : null; if ($del === null) return redirect($back)->withInput()->with('ok', 'Enter the delivery charge in USD (0 if none). It is set per quotation by weight and distance.');
    try { $valid = \Carbon\Carbon::parse((string)$r->input('valid_until'), 'Asia/Dhaka')->startOfDay(); } catch (\Throwable $e) { $valid = null; } if (!$valid || $valid->lt(now('Asia/Dhaka')->startOfDay()) || $valid->gt(now('Asia/Dhaka')->addDays(60))) return redirect($back)->withInput()->with('ok', 'Enter until when the quotation is valid (today to 60 days).');
    [$sub, $tot] = O::totals($calc, $del); $by = A::staff();
    DB::transaction(function () use ($o, $upd, $sub, $del, $tot, $valid, $by, $r) {
      foreach ($upd as $lid => $u) DB::table('marine_order_lines')->where('id', $lid)->where('order_id', $o->id)->update($u);
      DB::table('marine_orders')->where('id', $o->id)->update(['status' => 'quoted', 'subtotal' => $sub, 'delivery_charge' => $del, 'total' => $tot, 'valid_until' => $valid->toDateString(), 'quoted_by' => $by, 'quoted_at' => now(), 'desk_note' => mb_substr(trim((string)$r->input('desk_note')), 0, 1000) ?: null, 'internal_note' => mb_substr(trim((string)$r->input('internal_note')), 0, 1000) ?: null, 'updated_at' => now()]);
    });
    \App\Support\Procure::audit('marine.order.quoted', 'Marine quotation sent: ' . $o->number . ' USD ' . number_format($tot, 2) . ($over ? ' (below-margin override)' : ''), $o->status, ['total' => $tot, 'delivery' => $del, 'valid_until' => $valid->toDateString(), 'override' => $over ? $low : null]);
    try { \Illuminate\Support\Facades\Cache::forget('sb.counts.v3'); } catch (\Throwable $e) {}
    $acc = DB::table('marine_accounts')->where('id', $o->account_id)->first(); $ok = false; $err = 'no account email';
    if ($acc && $acc->status === 'active') [$ok, $err] = A::mail('quotation', $acc->email, 'Quotation ' . $o->number . ' from DutyFree BD Marine Supply', 'Your quotation is ready', '<p>Dear ' . e($acc->name ?: 'Sir or Madam') . ',</p><p>Our marine desk has prepared quotation <b>' . e($o->number) . '</b>' . ($o->vessel ? ' for <b>' . e($o->vessel) . '</b>' : '') . '. It is valid until ' . e($valid->format('d M Y')) . '.</p><p style="margin:18px 0"><a href="' . A::PORTAL . '/orders/' . (int)$o->id . '" style="display:inline-block;background:#F95505;color:#FFFFFF;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:8px">Open the quotation</a></p><p>Sign in with your username to see the prices in USD and to answer.</p>', $acc->request_id, $acc->id, $by);
    return redirect($back)->with('ok', 'Quotation saved: USD ' . number_format($tot, 2) . '. ' . ($ok ? 'The account was told by email.' : 'The email was NOT sent (' . $err . '); the quotation is visible in the account.'));
  }
  /** The desk adds a line (for a PDF, photo or other file that could not be read) or removes one, while the quotation can still change. */
  public function adminLine(Request $r, $id) {
    $o = DB::table('marine_orders')->where('id', (int)$id)->first(); if (!$o || !O::ready()) return redirect('/admin/marine/orders'); $back = '/admin/marine/orders/' . $o->id;
    if (!in_array($o->status, ['requested', 'quoted'], true)) return redirect($back)->with('ok', 'Lines can no longer be changed for this order.');
    if ($r->input('remove')) { $l = DB::table('marine_order_lines')->where('id', (int)$r->input('remove'))->where('order_id', $o->id)->first(); if ($l) { DB::table('marine_order_lines')->where('id', $l->id)->delete(); \App\Support\Procure::audit('marine.order.line', 'Line removed from ' . $o->number . ': ' . $l->name . ' x ' . $l->qty, null, null); } return redirect($back)->with('ok', 'Line removed.'); }
    $q = O::qty($r->input('qty')); $name = mb_substr(trim((string)$r->input('name')), 0, 200); $code = mb_substr(trim((string)$r->input('sku')), 0, 60);
    if ($q === null || (mb_strlen($name) < 2 && $code === '')) return redirect($back)->with('ok', 'Enter a product code or a description, and a quantity.');
    if (DB::table('marine_order_lines')->where('order_id', $o->id)->count() >= O::TOTAL_LINES) return redirect($back)->with('ok', 'This order already has ' . O::TOTAL_LINES . ' lines.');
    $l = O::match([['code' => $code ?: null, 'name' => $name !== '' ? $name : $code, 'qty' => $q, 'unit' => mb_substr(trim((string)$r->input('unit')), 0, 20) ?: null]], MarineController::hiddenIds(), true)[0];
    if ($code !== '' && !$l['product_id'] && $name === '') return redirect($back)->with('ok', 'No marine catalog product has the code ' . $code . '. Add a description to keep it as a free-text line.');
    DB::table('marine_order_lines')->insert($l + ['order_id' => $o->id, 'available' => true, 'created_at' => now(), 'updated_at' => now()]);
    \App\Support\Procure::audit('marine.order.line', 'Line added to ' . $o->number . ' by the desk: ' . $l['name'] . ' x ' . $q, null, ['product_id' => $l['product_id']]);
    return redirect($back)->with('ok', 'Line added' . ($l['product_id'] ? ' (catalog product, list price filled in).' : ' as a free-text item.'));
  }
  public function adminMove(Request $r, $id) {
    $o = DB::table('marine_orders')->where('id', (int)$id)->first(); if (!$o || !O::ready()) return redirect('/admin/marine/orders'); $back = '/admin/marine/orders/' . $o->id; $to = (string)$r->input('to'); $by = A::staff();
    if (!O::canMove($o->status, $to)) return redirect($back)->with('ok', 'That step is not possible from "' . (O::STATUS[$o->status] ?? $o->status) . '".');
    $u = ['status' => $to, 'updated_at' => now()]; $reason = mb_substr(trim((string)$r->input('reason')), 0, 300);
    if ($to === 'ordered') { $ref = mb_substr(trim((string)$r->input('po_ref')), 0, 60); if ($ref === '') return redirect($back)->with('ok', 'Enter the purchase order reference you received (email, letter or message).'); if (O::expired($o) && !$r->input('sure')) return redirect($back)->with('ok', 'This quotation has expired. Send a new quotation, or tick the box to accept the old prices.'); $u += ['po_ref' => $ref, 'ordered_by' => 'desk: ' . $by, 'ordered_at' => now()]; }
    if ($to === 'preparing' || $to === 'ordered') { try { $d = $r->input('delivery_at') ? \Carbon\Carbon::parse((string)$r->input('delivery_at'), 'Asia/Dhaka')->setTimezone(config('app.timezone')) : null; } catch (\Throwable $e) { $d = null; } if ($d) $u['delivery_at'] = $d; }
    if ($to === 'delivered') $u += ['delivered_at' => now(), 'closed_at' => now(), 'closed_by' => 'desk: ' . $by];
    if ($to === 'cancelled') { if (mb_strlen($reason) < 3) return redirect($back)->with('ok', 'Write the reason for cancelling.'); $u += ['closed_at' => now(), 'closed_by' => 'desk: ' . $by, 'close_reason' => $reason]; }
    $n = DB::table('marine_orders')->where('id', $o->id)->where('status', $o->status)->update($u);
    if ($n) \App\Support\Procure::audit('marine.order.' . $to, 'Marine order ' . $o->number . ': ' . (O::STATUS[$o->status] ?? $o->status) . ' -> ' . (O::STATUS[$to] ?? $to), $o->status, ['status' => $to, 'po_ref' => $u['po_ref'] ?? null, 'reason' => $reason ?: null]);
    try { \Illuminate\Support\Facades\Cache::forget('sb.counts.v3'); } catch (\Throwable $e) {}
    return redirect($back)->with('ok', $n ? 'Saved: ' . (O::STATUS[$to] ?? $to) . '.' : 'The order changed in the meantime. Check it again.');
  }
  /** Admin-only sample preview of the account pages and the desk order page, drawn with made-up rows that are never saved. No account is signed in and nothing is written. */
  public function preview(Request $r, $page = 'home') {
    if (!\App\Support\ProfitFloor::admin()) abort(403);
    $as = in_array($r->query('as'), ['owner', 'agency', 'captain', 'crew'], true) ? $r->query('as') : 'owner'; $type = in_array($as, ['captain', 'crew'], true) ? 'vessel' : $as; $now = now();
    $u = (object)['id' => 0, 'type' => $type, 'username' => ['owner' => 'OWN-00001', 'agency' => 'AGT-00001', 'captain' => 'VSL-00001', 'crew' => 'VSL-00002'][$as], 'email' => 'sample@example.com', 'name' => 'Sample Person', 'company' => $type === 'agency' ? 'Sample Agency Ltd' : 'Sample Shipping Co', 'role' => 'Purchasing officer', 'vessel_role' => $type === 'vessel' ? ($as === 'captain' ? 'captain' : 'crew') : null, 'crew_rank' => $as === 'crew' ? 'AB' : null, 'phone' => '+000 0000', 'port' => 'Chattogram', 'imo' => $type === 'vessel' ? '9000001' : null, 'vessel' => $type === 'vessel' ? 'MV SAMPLE' : null, 'status' => 'active', 'request_id' => 0];
    $st = in_array($r->query('status'), array_keys(O::STATUS), true) ? $r->query('status') : 'quoted'; $priced = $st !== 'requested';
    $o = (object)['id' => 0, 'number' => 'RQ-26-00000', 'kind' => $as === 'crew' ? 'personal' : 'vessel', 'account_id' => 0, 'port_call_id' => null, 'imo' => '9000001', 'vessel' => 'MV SAMPLE', 'port' => 'chattogram', 'place' => 'anchorage', 'eta' => $now->copy()->addDays(3), 'status' => $st, 'note' => 'Sample note from the account.', 'desk_note' => $priced ? 'Sample: delivery by boat at outer anchorage, weather permitting.' : null, 'internal_note' => null,
      'subtotal' => $priced ? 157.50 : null, 'delivery_charge' => $priced ? 40.00 : null, 'total' => $priced ? 197.50 : null, 'valid_until' => $priced ? $now->copy()->addDays(7)->toDateString() : null, 'quoted_by' => $priced ? 'Sample Staff' : null, 'quoted_at' => $priced ? $now : null, 'po_ref' => in_array($st, ['ordered', 'preparing', 'delivered'], true) ? 'PO-SAMPLE-1' : null, 'ordered_by' => in_array($st, ['ordered', 'preparing', 'delivered'], true) ? 'OWN-00001 (Sample Person)' : null, 'ordered_at' => in_array($st, ['ordered', 'preparing', 'delivered'], true) ? $now : null, 'delivery_at' => null, 'delivered_at' => $st === 'delivered' ? $now : null, 'closed_at' => in_array($st, ['declined', 'cancelled', 'delivered'], true) ? $now : null, 'close_reason' => null, 'closed_by' => null, 'created_at' => $now->copy()->subDay(), 'updated_at' => $now];
    $lines = collect([(object)['id' => 1, 'order_id' => 0, 'product_id' => null, 'sku' => 'SAMPLE-1', 'name' => 'Sample product A', 'size' => '500 g', 'qty' => 10, 'unit' => 'pcs', 'list_usd' => 12.50, 'unit_usd' => $priced ? 12.00 : null, 'available' => true, 'note' => null], (object)['id' => 2, 'order_id' => 0, 'product_id' => null, 'sku' => null, 'name' => 'Fresh vegetables, mixed (free-text item)', 'size' => null, 'qty' => 25, 'unit' => 'kg', 'list_usd' => null, 'unit_usd' => $priced ? 1.50 : null, 'available' => true, 'note' => 'Sample line note'], (object)['id' => 3, 'order_id' => 0, 'product_id' => null, 'sku' => 'SAMPLE-3', 'name' => 'Sample product C', 'size' => null, 'qty' => 2, 'unit' => null, 'list_usd' => 40.00, 'unit_usd' => null, 'available' => !$priced, 'note' => null]]);
    $pager = fn($items) => new \Illuminate\Pagination\LengthAwarePaginator($items, count($items), 30, 1, ['path' => $r->url()]);
    $call = (object)['id' => 0, 'account_id' => 0, 'imo' => '9000001', 'vessel' => 'MV SAMPLE', 'port' => 'chattogram', 'place' => 'anchorage', 'berth' => 'Outer anchorage B', 'eta' => $now->copy()->addDays(3), 'etd' => $now->copy()->addDays(6), 'agent_name' => 'Sample Agency', 'agent_contact' => '+000', 'status' => 'expected', 'note' => null];
    $ves = [(object)['imo' => '9000001', 'name' => 'MV SAMPLE', 'verified' => true], (object)['imo' => '9000002', 'name' => 'MV SAMPLE TWO', 'verified' => false]]; $scope = $type === 'vessel' ? O::scope($u, []) : ['9000001'];
    $d = match ($page) {
      'home' => ['v' => 'mp.home', 'd' => ['docs' => collect(), 'ves' => collect($type === 'vessel' ? [] : [(object)['imo' => '9000001', 'name' => 'MV SAMPLE', 'note' => 'Sample']]), 'types' => A::TYPES, 'vroles' => A::VROLES]],
      'orders' => ['v' => 'mp.orders', 'd' => ['rows' => $pager([$o]), 'st' => '', 'status' => O::STATUS, 'ports' => O::PORTS, 'canVessel' => O::canRaise($u, 'vessel'), 'canPersonal' => O::canRaise($u, 'personal'), 'crew' => O::isCrew($u)]],
      'catalog' => ['v' => 'mp.catalog', 'd' => ['rows' => $r->query('locked') ? null : $pager([(object)['id' => 0, 'sku' => 'SAMPLE-1', 'name' => 'Sample product A', 'size' => '500 g', 'image' => '', 'usd_price' => 12.5, 'mcat' => 'crew', 'msub' => null, 'cat' => 'Sample'], (object)['id' => 0, 'sku' => 'SAMPLE-2', 'name' => 'Sample product B with a longer name to show wrapping', 'size' => '', 'image' => '', 'usd_price' => 149, 'mcat' => 'crew', 'msub' => null, 'cat' => 'Sample']]), 'mc' => $r->query('locked') ? 'bonded' : 'crew', 'ms' => '', 'q' => '', 'cats' => \App\Support\MarineCats::CATS, 'locked' => (bool)$r->query('locked'), 'bok' => false, 'canOrder' => true, 'crew' => O::isCrew($u)]],
      'order_new' => ['v' => 'mp.order_new', 'd' => ['add' => 0, 'kind' => O::isCrew($u) ? 'personal' : 'vessel', 'vessels' => $type === 'vessel' ? [] : $ves, 'ports' => O::PORTS, 'places' => O::PLACES, 'pc' => null, 'max' => O::MAX_LINES]],
      'order' => ['v' => 'mp.order', 'd' => ['files' => collect([(object)['id' => 0, 'name' => 'store-list-sample.xlsx', 'size' => 18432]]), 'o' => $o, 'lines' => $lines, 'by' => (object)['username' => $u->username, 'name' => $u->name, 'company' => $u->company, 'type' => $u->type], 'status' => O::STATUS, 'ports' => O::PORTS, 'places' => O::PLACES, 'canAccept' => O::canAccept($u, $o, $scope), 'canWithdraw' => O::canWithdraw($u, $o), 'expired' => false, 'captainWait' => $st === 'quoted' && $o->kind === 'vessel' && $type === 'vessel']],
      'calls' => ['v' => 'mp.calls', 'd' => ['rows' => $pager([$call]), 'vessels' => $type === 'vessel' ? [] : $ves, 'ports' => O::PORTS, 'places' => O::PLACES, 'call' => O::CALL, 'canAdd' => !O::isCrew($u), 'canOrder' => O::canRaise($u, 'vessel')]],
      'desk_order' => ['v' => 'marine.order', 'd' => ['files' => collect([(object)['id' => 0, 'name' => 'store-list-sample.xlsx', 'size' => 18432, 'by_side' => 'account'], (object)['id' => 0, 'name' => 'photo-of-list.jpg', 'size' => 734003, 'by_side' => 'account']]), 'o' => $o, 'lines' => $lines, 'acc' => $u, 'floor' => [], 'rate' => MarineController::rate(), 'pct' => \App\Support\ProfitFloor::percent(), 'status' => O::STATUS, 'ports' => O::PORTS, 'places' => O::PLACES, 'next' => O::NEXT[$o->status] ?? [], 'types' => A::TYPES, 'vroles' => A::VROLES, 'verified' => true, 'admin' => true, 'pc' => null, 'mails' => collect()]],
      default => null };
    if (!$d) abort(404);
    return response(view($d['v'], $d['d'] + ['u' => $u]))->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store, private');
  }
  public function adminCalls(Request $r) {
    if (!O::ready()) return view('marine.calls', ['ready' => false]);
    $st = $r->query('status'); $st = ($st === 'all' || isset(O::CALL[$st])) ? $st : 'open';
    $rows = DB::table('marine_port_calls as c')->leftJoin('marine_accounts as a', 'a.id', '=', 'c.account_id')->when($st === 'open', fn($w) => $w->whereIn('c.status', ['expected', 'arrived']))->when($st !== 'open' && $st !== 'all', fn($w) => $w->where('c.status', $st))
      ->orderBy('c.eta')->select('c.*', 'a.username', 'a.company', 'a.name as person', 'a.type as atype')->paginate(50)->withQueryString();
    $ord = DB::table('marine_orders')->whereIn('port_call_id', collect($rows->items())->pluck('id')->all() ?: [0])->get(['id', 'number', 'status', 'port_call_id'])->groupBy('port_call_id');
    return view('marine.calls', ['ready' => true, 'rows' => $rows, 'st' => $st, 'call' => O::CALL, 'ports' => O::PORTS, 'places' => O::PLACES, 'ord' => $ord, 'status' => O::STATUS]);
  }
  public function adminCallMove(Request $r, $id) {
    $c = DB::table('marine_port_calls')->where('id', (int)$id)->first(); $to = (string)$r->input('to'); if (!$c || !isset(O::CALL[$to]) || $to === $c->status) return redirect('/admin/marine/port-calls');
    DB::table('marine_port_calls')->where('id', $c->id)->update(['status' => $to, 'updated_by' => 'desk: ' . A::staff(), 'updated_at' => now()]);
    \App\Support\Procure::audit('marine.portcall', 'Marine port call ' . ($c->vessel ?: 'IMO ' . $c->imo) . ' at ' . (O::PORTS[$c->port] ?? $c->port) . ': ' . $c->status . ' -> ' . $to, $c->status, $to);
    return redirect('/admin/marine/port-calls')->with('ok', 'Saved.');
  }
}
