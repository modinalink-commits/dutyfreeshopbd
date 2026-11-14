<?php
namespace App\Http\Controllers;
use App\Support\AutoPrice;
use App\Support\ProfitFloor;
use App\Support\Procure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Best Sellers: homepage card switch and the automatic (cost-following) sale price. Owner / Admin only: the page shows purchase costs. */
class BestSellerController extends Controller {
  private function gate(): void { abort_unless(ProfitFloor::admin(), 403); }
  public function index() {
    $this->gate(); $C = \App\Support\BestSellerCampaign::class;
    if (AutoPrice::on()) $C::markStart();
    $camp = ['on' => $C::remindOn(), 'days' => $C::days(), 'remind' => $C::text('remind'), 'once' => $C::text('once'), 'results' => $C::results(), 'ex' => [], 'onceN' => []];
    foreach (AutoPrice::ids() as $pid) { $p = DB::table('products')->find($pid); if (!$p) continue;
      $camp['ex'][$pid] = ['name' => $p->name, 'remind' => $C::body('remind', $p), 'once' => $C::body('once', $p)];
      $camp['onceN'][$pid] = \Illuminate\Support\Facades\Cache::get('bscamp.once.' . $pid); }
    return view('best_sellers_admin', ['camp' => $camp, 'unit' => \App\Support\SmsMarketing::unit(), 'rows' => AutoPrice::rows(), 'auto' => AutoPrice::on(), 'card' => Procure::set('home_bestseller', '0') === '1', 'pct' => ProfitFloor::pct(), 'parcel' => AutoPrice::parcel(), 'profit' => AutoPrice::profit(), 'ls' => ['on' => \App\Support\BestSellerStock::on(), 'limit' => \App\Support\BestSellerStock::limit(), 'to' => \App\Support\BestSellerStock::set('to', "Mamun|01824522800"), 'status' => \App\Support\BestSellerStock::status(), 'body' => \App\Support\BestSellerStock::BODY],
      'log' => DB::table('audit_logs')->whereIn('action', ['Auto price updated', 'Auto price paused (custom price)', 'Auto price resumed', 'Best Sellers setting changed', 'bestseller.low_stock'])->orderByDesc('id')->limit(15)->get()]);
  }
  /** Best Seller Orders report (SAL-010): how many orders the flagship products got, by day and by product, with the order list. */
  public function report(Request $r) {
    $this->gate(); $C = \App\Support\BestSellerCampaign::class; $tz = 'Asia/Dhaka'; $today = now($tz)->format('Y-m-d');
    $since = $C::since(); $start = $since ? \Carbon\Carbon::parse($since, 'UTC')->timezone($tz)->format('Y-m-d') : now($tz)->subDays(29)->format('Y-m-d');
    $ok = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
    $from = $ok($r->query('from')) ? $r->query('from') : $start; $to = $ok($r->query('to')) ? $r->query('to') : $today; if ($from > $to) [$from, $to] = [$to, $from];
    $pid = (int)$r->query('product', 0); $type = in_array($r->query('type'), ['pack', 'pre', 'single'], true) ? $r->query('type') : '';
    return view('best_sellers_report', ['d' => $C::report($from, $to, $pid, $type), 'from' => $from, 'to' => $to, 'pid' => $pid, 'type' => $type, 'start' => $start, 'today' => $today,
      'products' => DB::table('products')->whereIn('id', AutoPrice::ids())->pluck('name', 'id'), 'profit' => AutoPrice::profit(), 'since' => $since ? \Carbon\Carbon::parse($since, 'UTC')->timezone($tz)->format('d M, h:i A') : null]);
  }
  public function save(Request $r) {
    $this->gate();
    $d = $r->validate(['act' => 'required|in:switches,auto,custom,apply,stock,tpl_submit,tpl_sync,stock_test,camp,once_count,once_send', 'remind_on' => 'nullable|in:0,1', 'remind_days' => 'nullable|integer|min:3|max:60', 'remind_text' => 'nullable|string|max:400', 'once_text' => 'nullable|string|max:400', 'ls_on' => 'nullable|in:0,1', 'limit' => 'nullable|integer|min:1|max:100000', 'to' => 'nullable|string|max:500', 'phone' => 'nullable|string|max:20', 'id' => 'nullable|integer', 'price' => 'nullable|numeric|min:1', 'auto' => 'nullable|in:0,1', 'card' => 'nullable|in:0,1', 'parcel' => 'nullable|numeric|min:0|max:2000', 'profit' => 'nullable|numeric|min:0|max:5000']);
    if ($d['act'] === 'switches') {
      $was = ['auto' => AutoPrice::on(), 'card' => Procure::set('home_bestseller', '0') === '1', 'parcel' => AutoPrice::parcel(), 'profit' => AutoPrice::profit()]; $new = ['auto' => ($d['auto'] ?? '0') === '1', 'card' => ($d['card'] ?? '0') === '1', 'parcel' => isset($d['parcel']) ? (float)$d['parcel'] : AutoPrice::parcel(), 'profit' => isset($d['profit']) ? (float)$d['profit'] : AutoPrice::profit()];
      Procure::put(AutoPrice::ON, $new['auto'] ? '1' : '0'); if ($new['auto']) \App\Support\BestSellerCampaign::markStart(); Procure::put('home_bestseller', $new['card'] ? '1' : '0'); Procure::put(AutoPrice::PARCEL, (string)$new['parcel']); Procure::put(AutoPrice::PROFIT, (string)$new['profit']);
      if ($was != $new) Procure::audit('Best Sellers setting changed', 'Best Sellers', $was, $new + ['by' => auth()->user()->name ?? null]);
      $ch = AutoPrice::sync('switch', $was['parcel'] != $new['parcel'] || $was['profit'] != $new['profit'] || !$was['auto']);
      try { \Illuminate\Support\Facades\Cache::forget('store.home6'); StoreController::bust(); } catch (\Throwable $e) {}
      return back()->with('ok', 'Saved.'.($ch ? ' '.count($ch).' price'.(count($ch) === 1 ? '' : 's').' updated.' : ''));
    }
    $C = \App\Support\BestSellerCampaign::class;
    if ($d['act'] === 'camp') {
      foreach (['remind_text', 'once_text'] as $k) { $t = trim((string)($d[$k] ?? '')); if ($t !== '' && (!str_contains($t, '{product}') || !str_contains($t, '{link}'))) return back()->withInput()->with('err', 'Each text must contain {product} and {link}.'); Procure::put('bscamp_' . $k, $t); }
      $was = ['on' => $C::remindOn(), 'days' => $C::days()];
      Procure::put('bscamp_remind_on', ($d['remind_on'] ?? '0') === '1' ? '1' : '0'); if (isset($d['remind_days'])) Procure::put('bscamp_remind_days', (string)(int)$d['remind_days']);
      Procure::audit('Best Sellers setting changed', 'Campaign messages', $was, ['on' => $C::remindOn(), 'days' => $C::days(), 'by' => auth()->user()->name ?? null]);
      return back()->with('ok', 'Campaign messages saved.');
    }
    if ($d['act'] === 'once_count' || $d['act'] === 'once_send') {
      $pid = (int)($d['id'] ?? 0); abort_unless(AutoPrice::has($pid), 404);
      if ($d['act'] === 'once_count') { $n = count($C::onceList($pid)); \Illuminate\Support\Facades\Cache::put('bscamp.once.' . $pid, $n, 1800); return back()->with('ok', number_format($n) . ' one-time buyers would get the message.'); }
      $n = $C::sendOnce($pid); \Illuminate\Support\Facades\Cache::forget('bscamp.once.' . $pid); return back()->with($n ? 'ok' : 'err', $n ? number_format($n) . ' SMS queued for one-time buyers.' : 'Nothing to send: no offer price, product unpublished, or nobody left to message.');
    }
    $S = \App\Support\BestSellerStock::class;
    if ($d['act'] === 'stock') {
      $was = ['on' => $S::on(), 'limit' => $S::limit(), 'to' => $S::set('to', "Mamun|01824522800")];
      Procure::put('bs_stock_on', ($d['ls_on'] ?? '0') === '1' ? '1' : '0'); Procure::put('bs_stock_limit', (string)(int)($d['limit'] ?? 100)); Procure::put('bs_stock_to', trim((string)($d['to'] ?? '')));
      Procure::audit('Best Sellers setting changed', 'Low-stock notice', $was, ['on' => $S::on(), 'limit' => $S::limit(), 'to' => $S::set('to', ''), 'by' => auth()->user()->name ?? null]);
      return back()->with('ok', 'Low-stock notice saved. '.count($S::recipients()).' valid recipient'.(count($S::recipients()) === 1 ? '' : 's').'.');
    }
    if ($d['act'] === 'tpl_submit') { [$ok, $m] = $S::submitTemplate(); return back()->with($ok ? 'ok' : 'err', $m); }
    if ($d['act'] === 'tpl_sync') { [$ok, $m] = $S::syncTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template: '.$m); }
    if ($d['act'] === 'stock_test') {
      $wa = \App\Support\WhatsApp::norm((string)($d['phone'] ?? '')); if (!preg_match('/^8801[3-9]\d{8}$/', $wa)) return back()->with('err', 'Enter a valid mobile number.');
      $p = DB::table('products')->whereIn('id', AutoPrice::ids() ?: [0])->first(['name', 'stock']); if (!$p) return back()->with('err', 'No Best Seller product found.');
      [$ok, $ch, $m] = $S::call($wa, 'Test', (string)$p->name, (int)$p->stock); return back()->with($ok ? 'ok' : 'err', 'Test to …'.substr($wa, -4).' by '.$ch.': '.($ok ? 'sent' : 'failed · '.$m));
    }
    $id = (int)($d['id'] ?? 0); abort_unless(AutoPrice::has($id), 404); $p = DB::table('products')->find($id); abort_unless($p, 404);
    if ($d['act'] === 'auto') { AutoPrice::setCustom($id, false, 'switched back on the Best Sellers page'); AutoPrice::sync('resume', true); return back()->with('ok', $p->name.' is on the automatic pack price again.'); }
    if ($d['act'] === 'apply') { AutoPrice::setCustom($id, false, 'applied by hand'); $ch = AutoPrice::sync('apply', true); return back()->with('ok', isset($ch[$id]) ? $p->name.': '.number_format($ch[$id][0]).' → '.number_format($ch[$id][1]).' TK for 2 cartons.' : 'Already at the automatic pack price.'); }
    $price = (float)($d['price'] ?? 0); if ($price <= 0) return back()->with('err', 'Enter a price per carton.');
    $min = AutoPrice::unitCost($p, 2) + AutoPrice::profit();
    if ($price + 0.004 < $min) return back()->with('err', 'The price per carton cannot go below cost + the fixed profit ('.number_format($min, 2).' TK).');
    if ($price * 2 > (float)$p->price * 2 - 1) return back()->with('err', 'The pack price per carton must be lower than the single price ('.number_format($p->price).' TK).');
    $old = AutoPrice::pack2($p); AutoPrice::setCustom($id, true, 'custom pack price '.$price.' per carton set on the Best Sellers page'); AutoPrice::write($p, $price);
    ProfitFloor::audit('Selling price changed', 'Product '.$p->name, ['pack_of_2' => $old], ['pack_unit' => $price, 'source' => 'Best Sellers custom pack price']);
    try { \Illuminate\Support\Facades\Cache::forget('store.home6'); StoreController::bust(); } catch (\Throwable $e) {}
    return back()->with('ok', $p->name.' is on a custom pack price of '.number_format($price).' TK per carton. The automatic price is paused for it.');
  }
}
