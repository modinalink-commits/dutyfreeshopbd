<?php
namespace App\Http\Controllers;

use App\Support\Delivery as D;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Courier & Delivery -> Delivery Charge */
class DeliveryController extends Controller
{
  public function index(Request $r) {
    $o = DB::table('orders')->whereNotIn('status', ['Cancelled', 'Canceled']);
    $kpi = (clone $o)->selectRaw("coalesce(sum(shipping),0) as collected, count(*) filter (where shipping = 0) as free_n, count(*) filter (where shipping > 0) as paid_n, coalesce(avg(nullif(shipping,0)),0) as avg_ch, coalesce(sum(delivery_free_discount),0) as waived, count(*) filter (where delivery_manual_adj is not null) as manual_n")->first();
    $areas = (clone $o)->whereNotNull('delivery_zone')->selectRaw("delivery_zone as z, count(*) as n, coalesce(sum(shipping),0) as s, coalesce(sum(delivery_free_discount),0) as w")->groupBy('delivery_zone')->orderByDesc('n')->limit(20)->get();
    $zones = DB::table('delivery_zones')->orderByRaw("case level when 'division' then 1 when 'district' then 2 when 'thana' then 3 else 4 end")->orderBy('name')->get();
    $ov = DB::table('delivery_overrides')->orderByDesc('id')->get();
    return view('delivery', ['cfg' => D::cfg(), 'kpi' => $kpi, 'areas' => $areas, 'zones' => $zones, 'ov' => $ov, 'cats' => DB::table('categories')->orderBy('name')->get(['id', 'name']), 'edit' => $r->query('edit') ? DB::table('delivery_zones')->find((int)$r->query('edit')) : null]);
  }
  public function save(Request $r) {
    $old = D::cfg();
    foreach (['delv_on', 'delv_free_on'] as $k) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $r->boolean($k) ? '1' : '0', 'updated_at' => now(), 'created_at' => now()]);
    foreach (['delv_default', 'delv_inside', 'delv_outside', 'delv_express', 'delv_free_min'] as $k) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => (string)max(0, round((float)$r->input($k, 0), 2)), 'updated_at' => now(), 'created_at' => now()]);
    foreach (['delv_inside_label' => 60, 'delv_outside_label' => 60, 'delv_express_label' => 60, 'delv_inside_names' => 300] as $k => $n) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => mb_substr(trim((string)$r->input($k)), 0, $n), 'updated_at' => now(), 'created_at' => now()]);
    D::flush(); \App\Support\Procure::audit('delivery.settings', 'Delivery charge settings', $old, $r->except(['_token']));
    return redirect('/admin/delivery')->with('ok', 'Saved. New rates apply to new orders only; existing orders keep their original delivery charge.');
  }
  public function zoneSave(Request $r, $id = null) {
    $v = $r->validate(['level' => 'required|in:division,district,thana,area', 'name' => 'required|string|max:120', 'parent' => 'nullable|string|max:120', 'charge' => 'required|numeric|min:0', 'express_charge' => 'nullable|numeric|min:0']);
    $d = ['level' => $v['level'], 'name' => trim($v['name']), 'parent' => $v['parent'] ?? null, 'charge' => $v['charge'], 'express_charge' => ($v['express_charge'] ?? '') === '' ? null : $v['express_charge'], 'active' => $r->boolean('active', true), 'updated_at' => now()];
    if ($id) DB::table('delivery_zones')->where('id', $id)->update($d); else DB::table('delivery_zones')->insert($d + ['created_at' => now()]);
    D::flush(); \App\Support\Procure::audit('delivery.zone.' . ($id ? 'edit' : 'add'), $d['name'], null, $d);
    return redirect('/admin/delivery#zones')->with('ok', 'Zone saved');
  }
  public function zoneAct(Request $r, $id) {
    $z = DB::table('delivery_zones')->find($id); if (!$z) return back();
    if ($r->input('do') === 'delete') DB::table('delivery_zones')->where('id', $id)->delete(); else DB::table('delivery_zones')->where('id', $id)->update(['active' => !$z->active, 'updated_at' => now()]);
    D::flush(); \App\Support\Procure::audit('delivery.zone.' . $r->input('do', 'toggle'), $z->name); return redirect('/admin/delivery#zones')->with('ok', 'Updated');
  }
  public function ovSave(Request $r) {
    $v = $r->validate(['kind' => 'required|in:product,category,campaign', 'ref' => 'nullable|string|max:160', 'mode' => 'required|in:free,custom', 'charge' => 'nullable|numeric|min:0', 'label' => 'nullable|string|max:120', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date']);
    $refId = null; $refName = null;
    if ($v['kind'] === 'product') { $q = trim((string)$v['ref']); $p = DB::table('products')->where(fn($x) => $x->where('sku', $q)->orWhere('id', ctype_digit($q) ? (int)$q : 0)->orWhere('name', 'ilike', $q))->first(['id', 'name']); if (!$p) return back()->with('ok', 'Product not found (enter ID, SKU or exact name)'); $refId = $p->id; $refName = $p->name; }
    if ($v['kind'] === 'category') { $c = DB::table('categories')->find((int)$v['ref']); if (!$c) return back()->with('ok', 'Choose a category'); $refId = $c->id; $refName = $c->name; }
    $t = fn($x) => $x ? \Carbon\Carbon::parse($x, 'Asia/Dhaka')->utc()->toDateTimeString() : null;
    DB::table('delivery_overrides')->insert(['kind' => $v['kind'], 'ref_id' => $refId, 'ref_name' => $refName, 'mode' => $v['mode'], 'charge' => $v['mode'] === 'custom' ? (float)($v['charge'] ?? 0) : null, 'label' => $v['label'] ?? null, 'starts_at' => $t($v['starts_at'] ?? null), 'ends_at' => $t($v['ends_at'] ?? null), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    \App\Support\Procure::audit('delivery.override.add', $v['kind'] . ' ' . ($refName ?? ''), null, $v); return redirect('/admin/delivery#ov')->with('ok', 'Override added');
  }
  public function ovAct(Request $r, $id) {
    if ($r->input('do') === 'delete') DB::table('delivery_overrides')->where('id', $id)->delete(); else DB::table('delivery_overrides')->where('id', $id)->update(['active' => DB::raw('not active'), 'updated_at' => now()]);
    \App\Support\Procure::audit('delivery.override.' . $r->input('do', 'toggle'), '#' . $id); return redirect('/admin/delivery#ov')->with('ok', 'Updated');
  }
  /** Admin create-order helper: GET /admin/orders/delivery */
  public function quote(Request $r) {
    $pids = array_values(array_filter(array_map('intval', explode(',', (string)$r->query('pids')))));
    return response()->json(D::compute((float)$r->query('subtotal', 0), 0, (string)$r->query('address'), (string)$r->query('district'), $pids))->header('Cache-Control', 'no-store');
  }
  /** Admin diagnostic (read-only): Pathao cities, zones of one city, and a live price. Creates no parcel. */
  public function pathaoDiag(Request $r) {
    if (!\App\Support\Pathao::configured()) return response()->json(['ok' => false, 'msg' => 'Pathao not configured']);
    $cities = \App\Support\Pathao::cities(); $out = ['ok' => true, 'store' => \App\Support\Pathao::storeId(), 'cities' => count($cities), 'sample' => array_slice($cities, 0, 6)];
    if ($c = (int)$r->query('city')) { $z = \App\Support\Pathao::zones($c); $out['zones'] = count($z); $out['zone_sample'] = array_slice($z, 0, 6); if ($zz = (int)$r->query('zone')) $out['price'] = \App\Support\Pathao::price($c, $zz); }
    if ($r->query('find')) $out['find'] = array_values(array_filter($cities, fn($x) => stripos($x['city_name'] ?? '', (string)$r->query('find')) !== false));
    return response()->json($out);
  }
}
