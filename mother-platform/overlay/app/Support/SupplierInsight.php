<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Supplier score, performance, price history/alerts, forecast and reorder suggestions. Read-only analytics on the canonical purchase/portal tables. */
class SupplierInsight
{
  const W = ['price'=>.20,'delivery'=>.20,'quality'=>.15,'returns'=>.10,'damage'=>.05,'lead'=>.10,'fulfil'=>.10,'response'=>.10];
  const LABEL = ['price'=>'Price Competitiveness','delivery'=>'Delivery Reliability','quality'=>'Product Quality','returns'=>'Return Rate','damage'=>'Damage Rate','lead'=>'Lead Time','fulfil'=>'Order Fulfillment','response'=>'Response Speed'];

  /** @return array{score:int, m:array, raw:array} each metric 0..1 (higher = better) */
  public static function score(int $sid): array {
    $r = []; $m = [];
    // price: our quotes vs the lowest quote on the same RFQ
    $qs = DB::table('supplier_quotes as q')->where('q.supplier_id',$sid)->get(['q.rfq_id','q.unit_price']); $ratios = [];
    foreach ($qs as $q) { $min = (float)DB::table('supplier_quotes')->where('rfq_id',$q->rfq_id)->min('unit_price'); if ($q->unit_price > 0 && $min > 0) $ratios[] = $min / (float)$q->unit_price; }
    $m['price'] = $ratios ? array_sum($ratios)/count($ratios) : null; $r['quotes'] = count($ratios);
    // delivery: delivered on or before the expected date
    $d = DB::table('supplier_deliveries')->where('supplier_id',$sid)->whereNotNull('received_at')->get(['expected_arrival','received_at']); $ok = 0;
    foreach ($d as $x) if (!$x->expected_arrival || substr((string)$x->received_at,0,10) <= $x->expected_arrival) $ok++;
    $m['delivery'] = $d->count() ? $ok / $d->count() : null; $r['deliveries'] = $d->count();
    // quality / damage / returns by quantity
    $bought = (int)DB::table('purchase_items as i')->join('purchases as p','p.id','=','i.purchase_id')->where('p.supplier_id',$sid)->whereNotIn('p.status',Procure::BAD)->sum('i.qty');
    $dmg = (int)DB::table('damaged_products')->where('supplier_id',$sid)->where('responsibility','supplier')->sum('qty');
    $ret = (int)DB::table('purchase_returns')->where('supplier_id',$sid)->sum('qty');
    $rej = (int)DB::table('supplier_delivery_items as di')->join('supplier_deliveries as d','d.id','=','di.delivery_id')->where('d.supplier_id',$sid)->sum('di.rejected_qty');
    $m['quality'] = $bought ? max(0, 1 - ($dmg + $rej) / $bought * 5) : null;   // x5: a few % defects is already a quality problem
    $m['returns'] = $bought ? max(0, 1 - $ret / $bought * 5) : null;
    $m['damage'] = $bought ? max(0, 1 - $dmg / $bought * 5) : null; $r['bought'] = $bought; $r['ret'] = $ret; $r['dmg'] = $dmg;
    // lead time from quotes + deliveries
    $lead = DB::table('supplier_quotes')->where('supplier_id',$sid)->avg('lead_days'); $m['lead'] = $lead === null ? null : 1 / (1 + (float)$lead / 14); $r['lead_days'] = $lead === null ? null : round((float)$lead,1);
    // fulfilment: accepted qty / ordered qty over answered POs
    $po = DB::table('supplier_po_items as i')->join('supplier_pos as p','p.id','=','i.po_id')->where('p.supplier_id',$sid)->whereNotNull('p.responded_at')->selectRaw('sum(i.qty) q, sum(coalesce(i.accepted_qty,0)) a')->first();
    $m['fulfil'] = ($po && $po->q > 0) ? (float)$po->a / (float)$po->q : null;
    // response: hours from PO sent / RFQ invite to answer
    $hrs = [];
    foreach (DB::table('supplier_pos')->where('supplier_id',$sid)->whereNotNull('responded_at')->get(['created_at','updated_at','responded_at']) as $p) $hrs[] = max(0, (strtotime((string)$p->responded_at) - strtotime((string)$p->created_at)) / 3600);
    foreach (DB::table('supplier_quotes as q')->join('supplier_rfq_invites as i', fn($j) => $j->on('i.rfq_id','=','q.rfq_id')->on('i.supplier_id','=','q.supplier_id'))->where('q.supplier_id',$sid)->get(['i.created_at as ic','q.created_at as qc']) as $p) $hrs[] = max(0, (strtotime((string)$p->qc) - strtotime((string)$p->ic)) / 3600);
    $avg = $hrs ? array_sum($hrs)/count($hrs) : null; $m['response'] = $avg === null ? null : 1 / (1 + $avg / 24); $r['resp_hours'] = $avg === null ? null : round($avg,1);
    // weighted score over metrics that have data; no data at all = null
    $w = 0; $s = 0; foreach ($m as $k=>$v) if ($v !== null) { $w += self::W[$k]; $s += self::W[$k] * min(1,$v); }
    return ['score'=>$w > 0 ? (int)round($s / $w * 100) : null, 'm'=>$m, 'raw'=>$r];
  }
  public static function band(?int $s): string { return $s === null ? 'draft' : ($s >= 80 ? 'received' : ($s >= 60 ? 'pending' : 'cancelled')); }

  /** Supplier-visible performance: only own figures, no ranking. */
  public static function own(int $sid): array {
    $x = self::score($sid); $m = $x['m'];
    $pos = DB::table('supplier_pos')->where('supplier_id',$sid)->whereNotNull('responded_at')->count();
    return ['on_time'=>$m['delivery'] === null ? null : round($m['delivery']*100), 'fulfil'=>$m['fulfil'] === null ? null : round($m['fulfil']*100), 'return_pct'=>$x['raw']['bought'] ? round($x['raw']['ret'] / $x['raw']['bought'] * 100, 1) : null, 'resp_hours'=>$x['raw']['resp_hours'], 'pos'=>$pos];
  }

  /** Purchase + quote price points for a product. */
  public static function priceHistory(int $productId, ?int $sid = null): array {
    $a = DB::table('purchase_items as i')->join('purchases as p','p.id','=','i.purchase_id')->join('suppliers as s','s.id','=','p.supplier_id')->where('i.product_id',$productId)->whereNotIn('p.status',Procure::BAD)->when($sid, fn($q) => $q->where('p.supplier_id',$sid))->orderBy('p.purchase_date')->get(['p.purchase_date as d','i.cost as price','s.name as sname','p.supplier_id'])->map(fn($x) => ['d'=>$x->d,'price'=>(float)$x->price,'sname'=>$x->sname,'sid'=>$x->supplier_id,'src'=>'purchase'])->all();
    $b = DB::table('supplier_quotes as q')->join('supplier_rfqs as r','r.id','=','q.rfq_id')->join('suppliers as s','s.id','=','q.supplier_id')->where('r.product_id',$productId)->when($sid, fn($q) => $q->where('q.supplier_id',$sid))->orderBy('q.created_at')->get(['q.created_at as d','q.unit_price as price','s.name as sname','q.supplier_id'])->map(fn($x) => ['d'=>substr((string)$x->d,0,10),'price'=>(float)$x->price,'sname'=>$x->sname,'sid'=>$x->supplier_id,'src'=>'quote'])->all();
    $all = array_merge($a,$b); usort($all, fn($x,$y) => strcmp($x['d'],$y['d'])); return $all;
  }
  /** Flag an unusual quote against the last 180 days of purchase cost for the same product. */
  public static function priceCheck(object $rfq, int $sid, float $price): void {
    if (!$rfq->product_id) return;
    $avg = DB::table('purchase_items as i')->join('purchases as p','p.id','=','i.purchase_id')->where('i.product_id',$rfq->product_id)->whereNotIn('p.status',Procure::BAD)->where('p.purchase_date','>=',now('Asia/Dhaka')->subDays(180)->toDateString())->avg('i.cost');
    if (!$avg || $avg <= 0) return; $pct = ($price - $avg) / $avg * 100; if (abs($pct) < 10) return;
    DB::table('supplier_price_alerts')->insert(['supplier_id'=>$sid,'rfq_id'=>$rfq->id,'kind'=>$pct > 0 ? 'above-average' : 'below-average','product'=>mb_substr($rfq->product,0,250),'price'=>$price,'ref_price'=>round($avg,2),'pct'=>round($pct,2),'created_at'=>now(),'updated_at'=>now()]);
  }

  /** Sales velocity and cover per product from real orders (counted statuses only) + stock. */
  public static function forecast(int $limit = 60): array {
    $since = now('Asia/Dhaka')->subDays(30)->toDateTimeString();
    $sold = DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->whereIn('o.status',['Approved','Packaging','Shipment','Partial Delivered','Delivered'])->where('o.created_at','>=',$since)->whereNotNull('i.product_id')->groupBy('i.product_id')->selectRaw('i.product_id, sum(i.qty) q')->pluck('q','product_id');
    if ($sold->isEmpty()) return [];
    $rows = [];
    foreach (DB::table('products')->whereIn('id',$sold->keys())->get(['id','name','sku','stock','cost','low_stock_alert']) as $p) {
      $q = (int)$sold[$p->id]; $daily = $q / 30; $stock = (int)$p->stock; $cover = $daily > 0 ? $stock / $daily : 999;
      $need = (int)max(0, ceil($daily * 30) - $stock); if ($cover > 21 && $stock > (int)$p->low_stock_alert) continue;
      $rows[] = ['p'=>$p,'sold'=>$q,'daily'=>round($daily,2),'stock'=>$stock,'cover'=>round($cover,1),'need'=>$need,'level'=>$cover <= 7 ? 'urgent' : 'action'];
    }
    usort($rows, fn($a,$b) => $a['cover'] <=> $b['cover']); return array_slice($rows, 0, $limit);
  }
  /** Suppliers who can supply a product: mapped first, then anyone who sold it to us. */
  public static function suppliersFor(int $productId): array {
    $a = DB::table('supplier_products')->where('product_id',$productId)->pluck('supplier_id')->all();
    $b = DB::table('purchase_items as i')->join('purchases as p','p.id','=','i.purchase_id')->where('i.product_id',$productId)->whereNotIn('p.status',Procure::BAD)->distinct()->pluck('p.supplier_id')->all();
    $ids = array_values(array_unique(array_merge($a,$b)));
    return $ids ? DB::table('suppliers')->whereIn('id',$ids)->where('active',true)->pluck('id')->all() : [];
  }
}
