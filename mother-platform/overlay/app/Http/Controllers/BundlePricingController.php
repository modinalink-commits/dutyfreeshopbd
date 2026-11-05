<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\BundlePricing;
use App\Support\ProfitFloor;

/** Sales & Bundle Pricing Settings (Admin only): the global Buy 2-5 ratio, unsafe handling, rounding, and the Bundle Performance report. */
class BundlePricingController extends Controller {
  public function index() {
    abort_unless(ProfitFloor::admin(), 403, 'Bundle pricing settings are for the Admin only.');
    BundlePricing::flush(); $cfg = BundlePricing::cfg(); $ready = BundlePricing::ready();
    $st = ['global' => 0, 'custom' => 0, 'off' => 0, 'capped' => 0, 'limited' => 0]; $capped = [];
    if ($ready) {
      foreach (DB::table('products')->selectRaw("coalesce(bundle_rule,'global') as r, count(*) c")->groupBy('r')->get() as $x) $st[$x->r] = (int)$x->c;
      $rows = DB::table('products as p')->selectRaw('p.id, p.name, p.price, p.bundle_rule as rule, '.\App\Support\Costing::fifoSql('p').' as base, p.buy2_price, p.buy3_price, p.buy4_price, p.buy5_price')->where('p.published', true)->orderBy('p.name')->get();
      foreach ($rows as $x) {
        if (($x->rule ?: 'global') !== 'global' || (float)$x->base <= 0) continue;
        $t = BundlePricing::compute((float)$x->price, (float)$x->base, 'global', [], $cfg); $caps = []; $miss = [];
        foreach ([2, 3, 4, 5] as $n) { if (!$t[$n]) $miss[] = $n; elseif ($t[$n]['status'] === 'Capped') $caps[] = $n.' → '.rtrim(rtrim(number_format($t[$n]['pct'], 2), '0'), '.').'%'; }
        if ($miss || $caps) { if ($miss) $st['limited']++; else $st['capped']++; if (count($capped) < 60) $capped[] = ['id' => $x->id, 'name' => $x->name, 'price' => (float)$x->price, 'caps' => $caps, 'miss' => $miss]; }
      }
    }
    $perf = [];
    if (BundlePricing::snapReady()) {
      $perf = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereBetween('i.bundle_quantity', [2, 100])->whereNotIn('o.status', ['Cancelled', 'Canceled', 'Rejected'])
        ->selectRaw("i.name, sum(case when i.bundle_quantity=2 then 1 else 0 end) b2, sum(case when i.bundle_quantity=3 then 1 else 0 end) b3, sum(case when i.bundle_quantity=4 then 1 else 0 end) b4, sum(case when i.bundle_quantity>=5 then 1 else 0 end) b5, sum(i.bundle_total) rev, sum(i.original_sale_price*i.bundle_quantity - i.bundle_total) disc, sum(i.cogs) filter (where i.cogs is not null) cogs, sum(i.bundle_total) filter (where i.cogs is not null) rev_c")
        ->groupBy('i.name')->orderByDesc('rev')->limit(100)->get();
    }
    return view('bundle_pricing', ['cfg' => $cfg, 'st' => $st, 'capped' => $capped, 'perf' => $perf, 'ready' => $ready]);
  }
  public function save(Request $r) {
    abort_unless(ProfitFloor::admin(), 403, 'Bundle pricing settings are for the Admin only.');
    abort_unless(BundlePricing::ready(), 409, 'Bundle pricing is still being installed. Try again in a minute.');
    $d = $r->validate(['bundle_on' => 'nullable', 'pct2' => 'required|numeric|min:0|max:20', 'pct3' => 'required|numeric|min:0|max:20', 'pct4' => 'required|numeric|min:0|max:20', 'pct5' => 'required|numeric|min:0|max:20',
      'unsafe' => 'required|in:reduce,disable', 'round' => 'required|in:none,1,5,10']);
    $old = BundlePricing::cfg(); $new = ['on' => $r->boolean('bundle_on'), 'pct' => [2 => (float)$d['pct2'], 3 => (float)$d['pct3'], 4 => (float)$d['pct4'], 5 => (float)$d['pct5']], 'unsafe' => $d['unsafe'], 'round' => $d['round'] === 'none' ? 0 : (int)$d['round']];
    $o = ['on' => $old['on'], 'pct' => $old['pct'], 'unsafe' => $old['unsafe'], 'round' => $old['round']];
    if ($o == $new) return redirect('/admin/bundle-pricing')->with('ok', 'Nothing changed.');
    \App\Support\Procure::put('bundle_on', $new['on'] ? '1' : '0'); foreach ([2, 3, 4, 5] as $n) \App\Support\Procure::put("bundle_pct_$n", rtrim(rtrim(number_format($new['pct'][$n], 2, '.', ''), '0'), '.') ?: '0');
    \App\Support\Procure::put('bundle_unsafe', $new['unsafe']); \App\Support\Procure::put('bundle_round', $d['round']); \App\Support\Procure::put('bundle_rule_version', (string)($old['version'] + 1));
    BundlePricing::flush(); [$ch, $cap, $dis] = BundlePricing::sync();
    ProfitFloor::audit('Global bundle ratio changed', 'Sales & Bundle Pricing Settings', $o, $new + ['by' => auth()->user()->name ?? null, 'recalculated' => $ch, 'auto_reduced' => $cap, 'tiers_unavailable' => $dis]);
    if ($cap > 0) ProfitFloor::audit('Bundle discount auto-reduced', "$cap products", null, ['reason' => 'protect the ' . ProfitFloor::pct() . '% minimum profit', 'unsafe_handling' => $new['unsafe']]);
    return redirect('/admin/bundle-pricing')->with('ok', "Saved. $ch products recalculated; $cap had a bundle discount reduced to protect the " . ProfitFloor::pct() . "% minimum profit.");
  }
}
