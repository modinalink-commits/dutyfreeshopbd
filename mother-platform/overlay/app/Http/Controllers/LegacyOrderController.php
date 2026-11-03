<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Old DutyFreeBD orders: token-protected intake into a staging table, then a batched import into the live orders / order_items. */
class LegacyOrderController extends Controller
{
  const STATUS = [1 => 'New', 2 => 'Pending', 3 => 'Approved', 4 => 'Shipment', 5 => 'Delivered', 6 => 'Cancel', 7 => 'Returned', 8 => 'Packaging', 9 => 'WFP', 10 => 'Partial Delivered', 11 => 'Return Pending', 12 => 'Incomplete', 13 => 'Assign'];
  private function ok(Request $r): bool { $t = (string)DB::table('settings')->where('key', 'legacy_import_token')->value('value'); return $t !== '' && hash_equals($t, (string)($r->header('X-Import-Key') ?: $r->query('k'))); }
  public function ingest(Request $r) {
    if (!$this->ok($r)) abort(404);
    $rows = json_decode((string)$r->input('rows'), true); if (!is_array($rows)) return response()->json(['ok' => false]);
    $n = 0;
    foreach ($rows as $x) {
      $id = (int)($x['id'] ?? 0); if (!$id) continue;
      DB::table('legacy_orders')->updateOrInsert(['old_id' => $id], ['invoice_no' => mb_substr((string)($x['invoice_no'] ?? ''), 0, 40) ?: null, 'phone' => mb_substr(preg_replace('/\D/', '', (string)($x['phone'] ?? '')), -11) ?: null,
        'status' => isset($x['status']) ? (int)$x['status'] : null, 'total' => (float)($x['total'] ?? 0), 'old_created_at' => ($x['created_at'] ?? null) ?: null, 'raw' => json_encode($x, JSON_UNESCAPED_UNICODE), 'src_page' => (int)($x['_page'] ?? 0), 'updated_at' => now(), 'created_at' => now()]);
      $n++;
    }
    return response()->json(['ok' => true, 'saved' => $n]);
  }
  public function stats(Request $r) { if (!$this->ok($r)) abort(404); return response()->json(['rows' => DB::table('legacy_orders')->count(), 'pages' => DB::table('legacy_orders')->distinct()->count('src_page')]); }
  public function review() {
    $d = ['staged' => DB::table('legacy_orders')->count(), 'done' => DB::table('legacy_orders')->whereNotNull('action')->count(), 'live_orders' => DB::table('orders')->count(), 'imported_orders' => DB::table('orders')->whereNotNull('legacy_order_id')->count(),
      'by_status' => DB::select('select status, count(*) n, coalesce(sum(total),0) t from legacy_orders group by 1 order by 1'), 'result' => DB::table('settings')->where('key', 'legacy_orders_result')->value('value'),
      'range' => DB::selectOne('select min(old_created_at) a, max(old_created_at) b, sum(total) t from legacy_orders'), 'dups' => (int)DB::selectOne("select count(*) n from legacy_orders l where exists (select 1 from orders o where o.invoice = 'INV-' || l.invoice_no)")->n];
    return view('legacy_orders', ['d' => $d, 'map' => self::STATUS]);
  }
  /** Import one batch (call repeatedly until left = 0). */
  public function apply(Request $r) {
    abort_unless($r->input('confirm') === 'IMPORT' || $r->boolean('go'), 422);
    @set_time_limit(120);
    $map = self::STATUS + [14 => 'Pre Order', 15 => 'Late Delivery', 16 => 'Scheduled', 17 => 'Scheduled'];
    $rows = DB::table('legacy_orders')->whereNull('action')->orderBy('old_id')->limit(max(50, min(1500, (int)($r->input('n') ?: 600))))->get();
    $pids = DB::table('products')->whereNotNull('ext_id')->pluck('id', 'ext_id')->all();
    $stats = ['imported' => 0, 'skipped' => 0];
    foreach ($rows->chunk(100) as $chunk) {
      DB::transaction(function () use ($chunk, $map, $pids, &$stats) {
        $phones = $chunk->pluck('phone')->filter()->unique()->values()->all();
        $cust = []; if ($phones) foreach (DB::table('customers')->whereRaw('right(phone,11) in (' . implode(',', array_fill(0, count($phones), '?')) . ')', $phones)->selectRaw('id, right(phone,11) p')->get() as $cr) $cust[$cr->p] = $cr->id;
        foreach ($chunk as $l) {
          $x = json_decode((string)$l->raw, true) ?: []; $inv = 'INV-' . $l->invoice_no;
          if (DB::table('orders')->where('legacy_order_id', $l->old_id)->exists() || DB::table('orders')->where('invoice', $inv)->exists()) { DB::table('legacy_orders')->where('old_id', $l->old_id)->update(['action' => 'skipped']); $stats['skipped']++; continue; }
          $tot = (float)($x['total'] ?? 0); $junk = $tot > 1000000 * max(1, count($x['order_item'] ?? [])); if ($junk) $tot = 0; $c = $x['customer'] ?? []; $created = $l->old_created_at ?: now();
          $oid = DB::table('orders')->insertGetId(['invoice' => $inv, 'customer_id' => $cust[$l->phone] ?? null, 'customer_name' => mb_substr((string)($c['name'] ?? 'Customer'), 0, 190) ?: 'Customer', 'phone' => $l->phone ?: '', 'address' => $c['address'] ?? null,
            'district' => $x['city']['name'] ?? ($x['select_area'] ?? null), 'source' => $x['source']['name'] ?? 'Web Site', 'courier' => $x['courier']['name'] ?? null, 'shipping' => (float)($x['shipping_cost'] ?? 0), 'total' => $tot,
            'status' => $map[(int)$l->status] ?? 'New', 'note' => trim(implode(' | ', array_filter([$x['note'] ?? null, $x['comment'] ?? null, $junk ? '[legacy total ignored: ' . ($x['total'] ?? '') . ']' : null]))) ?: null, 'payment_method' => $x['payment_method'] ?? null, 'discount' => (float)($x['discount'] ?? 0) + (float)($x['coupon_disocunt'] ?? 0),
            'tracking_code' => $x['tracking_id'] ?? null, 'legacy_order_id' => $l->old_id, 'legacy_source' => 'dutyfreebd.com', 'created_at' => $created, 'updated_at' => $x['updated_at'] ?? $created]);
          $items = [];
          foreach (($x['order_item'] ?? []) as $it) {
            $nm = $it['product']['name'] ?? 'Product'; $pid = $pids[(int)($it['product_id'] ?? 0) + 999] ?? null;
            $items[] = ['order_id' => $oid, 'product_id' => $pid, 'name' => mb_substr((string)$nm, 0, 250), 'qty' => max(1, (int)($it['quantity'] ?? 1)), 'price' => (float)($it['price'] ?? 0), 'created_at' => $created, 'updated_at' => $created];
          }
          if ($items) DB::table('order_items')->insert($items);
          DB::table('legacy_orders')->where('old_id', $l->old_id)->update(['action' => 'imported', 'new_order_id' => $oid]); $stats['imported']++;
        }
      });
    }
    $left = DB::table('legacy_orders')->whereNull('action')->count();
    if (!$left) { // all real orders are now rows, so the aggregate legacy counters would double-count
      DB::table('customers')->where(fn($q) => $q->where('legacy_orders', '>', 0)->orWhere('legacy_spent', '>', 0))->update(['legacy_orders' => 0, 'legacy_spent' => 0]);
      DB::table('settings')->updateOrInsert(['key' => 'legacy_orders_result'], ['value' => 'completed ' . now(), 'updated_at' => now(), 'created_at' => now()]);
    }
    return response()->json($stats + ['left' => $left]);
  }
}
