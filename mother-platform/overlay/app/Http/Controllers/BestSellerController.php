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
    $this->gate();
    return view('best_sellers_admin', ['rows' => AutoPrice::rows(), 'auto' => AutoPrice::on(), 'card' => Procure::set('home_bestseller', '0') === '1', 'pct' => ProfitFloor::pct(), 'parcel' => AutoPrice::parcel(), 'profit' => AutoPrice::profit(), 'ls' => ['on' => \App\Support\BestSellerStock::on(), 'limit' => \App\Support\BestSellerStock::limit(), 'to' => \App\Support\BestSellerStock::set('to', "Mamun|01824522800"), 'status' => \App\Support\BestSellerStock::status(), 'body' => \App\Support\BestSellerStock::BODY],
      'log' => DB::table('audit_logs')->whereIn('action', ['Auto price updated', 'Auto price paused (custom price)', 'Auto price resumed', 'Best Sellers setting changed', 'bestseller.low_stock'])->orderByDesc('id')->limit(15)->get()]);
  }
  public function save(Request $r) {
    $this->gate();
    $d = $r->validate(['act' => 'required|in:switches,auto,custom,apply,stock,tpl_submit,tpl_sync,stock_test', 'ls_on' => 'nullable|in:0,1', 'limit' => 'nullable|integer|min:1|max:100000', 'to' => 'nullable|string|max:500', 'phone' => 'nullable|string|max:20', 'id' => 'nullable|integer', 'price' => 'nullable|numeric|min:1', 'auto' => 'nullable|in:0,1', 'card' => 'nullable|in:0,1', 'parcel' => 'nullable|numeric|min:0|max:2000', 'profit' => 'nullable|numeric|min:0|max:5000']);
    if ($d['act'] === 'switches') {
      $was = ['auto' => AutoPrice::on(), 'card' => Procure::set('home_bestseller', '0') === '1', 'parcel' => AutoPrice::parcel(), 'profit' => AutoPrice::profit()]; $new = ['auto' => ($d['auto'] ?? '0') === '1', 'card' => ($d['card'] ?? '0') === '1', 'parcel' => isset($d['parcel']) ? (float)$d['parcel'] : AutoPrice::parcel(), 'profit' => isset($d['profit']) ? (float)$d['profit'] : AutoPrice::profit()];
      Procure::put(AutoPrice::ON, $new['auto'] ? '1' : '0'); Procure::put('home_bestseller', $new['card'] ? '1' : '0'); Procure::put(AutoPrice::PARCEL, (string)$new['parcel']); Procure::put(AutoPrice::PROFIT, (string)$new['profit']);
      if ($was != $new) Procure::audit('Best Sellers setting changed', 'Best Sellers', $was, $new + ['by' => auth()->user()->name ?? null]);
      $ch = AutoPrice::sync('switch', $was['parcel'] != $new['parcel'] || $was['profit'] != $new['profit'] || !$was['auto']);
      try { \Illuminate\Support\Facades\Cache::forget('store.home6'); StoreController::bust(); } catch (\Throwable $e) {}
      return back()->with('ok', 'Saved.'.($ch ? ' '.count($ch).' price'.(count($ch) === 1 ? '' : 's').' updated.' : ''));
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
