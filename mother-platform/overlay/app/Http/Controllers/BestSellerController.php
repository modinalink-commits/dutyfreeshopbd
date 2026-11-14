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
    return view('best_sellers_admin', ['rows' => AutoPrice::rows(), 'auto' => AutoPrice::on(), 'card' => Procure::set('home_bestseller', '0') === '1', 'pct' => ProfitFloor::pct(),
      'log' => DB::table('audit_logs')->whereIn('action', ['Auto price updated', 'Auto price paused (custom price)', 'Auto price resumed', 'Best Sellers setting changed'])->orderByDesc('id')->limit(15)->get()]);
  }
  public function save(Request $r) {
    $this->gate();
    $d = $r->validate(['act' => 'required|in:switches,auto,custom,apply', 'id' => 'nullable|integer', 'price' => 'nullable|numeric|min:1', 'auto' => 'nullable|in:0,1', 'card' => 'nullable|in:0,1']);
    if ($d['act'] === 'switches') {
      $was = ['auto' => AutoPrice::on(), 'card' => Procure::set('home_bestseller', '0') === '1']; $new = ['auto' => ($d['auto'] ?? '0') === '1', 'card' => ($d['card'] ?? '0') === '1'];
      Procure::put(AutoPrice::ON, $new['auto'] ? '1' : '0'); Procure::put('home_bestseller', $new['card'] ? '1' : '0');
      if ($was != $new) Procure::audit('Best Sellers setting changed', 'Best Sellers', $was, $new + ['by' => auth()->user()->name ?? null]);
      $ch = AutoPrice::sync('switch');
      try { \Illuminate\Support\Facades\Cache::forget('store.home6'); StoreController::bust(); } catch (\Throwable $e) {}
      return back()->with('ok', 'Saved.'.($ch ? ' '.count($ch).' price'.(count($ch) === 1 ? '' : 's').' updated.' : ''));
    }
    $id = (int)($d['id'] ?? 0); abort_unless(AutoPrice::has($id), 404); $p = DB::table('products')->find($id); abort_unless($p, 404);
    if ($d['act'] === 'auto') { AutoPrice::setCustom($id, false, 'switched back on the Best Sellers page'); AutoPrice::sync('resume', true); return back()->with('ok', $p->name.' is on the automatic price again.'); }
    if ($d['act'] === 'apply') { AutoPrice::setCustom($id, false, 'applied by hand'); $ch = AutoPrice::sync('apply', true); return back()->with('ok', isset($ch[$id]) ? $p->name.': '.number_format($ch[$id][0]).' → '.number_format($ch[$id][1]).' TK.' : 'Already at the automatic price.'); }
    $price = (float)($d['price'] ?? 0); if ($price <= 0) return back()->with('err', 'Enter a price.');
    if ($e = ProfitFloor::productError(ProfitFloor::base($p), $price)) return back()->with('err', $e);
    $upd = ['price' => $price, 'updated_at' => now()]; if ($p->compare_price !== null && (float)$p->compare_price <= $price) $upd['compare_price'] = null;
    DB::table('products')->where('id', $id)->update($upd); AutoPrice::setCustom($id, true, 'custom price '.$price.' set on the Best Sellers page');
    ProfitFloor::audit('Selling price changed', 'Product '.$p->name, ['price' => $p->price], ['price' => $price, 'source' => 'Best Sellers custom price']);
    try { \App\Support\BundlePricing::sync([$id]); \Illuminate\Support\Facades\Cache::forget('store.home6'); StoreController::bust(); } catch (\Throwable $e) {}
    return back()->with('ok', $p->name.' is on a custom price of '.number_format($price).' TK. The automatic price is paused for it.');
  }
}
