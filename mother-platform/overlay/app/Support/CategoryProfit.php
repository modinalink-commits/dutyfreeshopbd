<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Category Wise Profit. Same money rules as the Profit & Loss Statement (ProfitReportController):
 *  - a full return sells nothing (its discount is reversed too); a partial return keeps only the received quantity;
 *  - COGS = the FIFO batch cost saved on the order line (order_items.cogs), older lines fall back to the product cost;
 *  - net sales = gross - discount - return; gross profit = net sales - COGS.
 * The order discount is spread over the order's lines by their share of the order's gross. All numbers are made here, on the server.
 */
class CategoryProfit {
  public const FULL_RET = "o.status in ('Return Pending','Returned') and coalesce(o.return_type,'') <> 'Partial'";

  public static function filters(Request $r): array {
    $from = (string)$r->query('from') ?: now('Asia/Dhaka')->startOfMonth()->toDateString(); $to = (string)$r->query('to') ?: now('Asia/Dhaka')->toDateString();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = now('Asia/Dhaka')->startOfMonth()->toDateString();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = now()->toDateString();
    if ($from > $to) [$from, $to] = [$to, $from];
    $s = fn($k) => trim((string)$r->query($k, ''));
    return ['from' => $from, 'to' => $to, 'status' => $s('status') === 'all' ? 'all' : 'delivered', 'store' => $s('store'), 'cat' => $s('cat'), 'sub' => $s('sub'), 'child' => $s('child'),
      'brand' => $s('brand'), 'q' => mb_substr($s('q'), 0, 80), 'pay' => $s('pay'), 'courier' => $s('courier'), 'appr' => in_array(strtoupper($s('appr')), ['AI', 'MANUAL'], true) ? strtoupper($s('appr')) : '',
      'ccat' => $s('ccat'), 'mem' => $s('mem'), 'cmp' => $s('cmp') === '1' ? '1' : ''];
  }

  /** Order date is the basis, like the Profit & Loss Statement. */
  private static function lines(array $f) {
    $og = DB::table('order_items')->selectRaw('order_id, sum(price * qty) as g')->groupBy('order_id');
    $q = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')
      // old order lines that lost their product link are matched to a product by exact (unique) name
      ->leftJoinSub(DB::table('products')->selectRaw('min(id) as id, lower(trim(name)) as nm')->groupByRaw('lower(trim(name))')->havingRaw('count(*) = 1'), 'pn', function ($j) { $j->whereRaw('i.product_id is null and pn.nm = lower(trim(i.name))'); })
      ->leftJoin('products as p', 'p.id', '=', DB::raw('coalesce(i.product_id, pn.id)'))
      ->leftJoin('categories as c', 'c.id', '=', DB::raw('coalesce(i.cat_id, p.category_id)'))
      ->joinSub($og, 'og', 'og.order_id', '=', 'i.order_id')
      ->when(self::hasInv(), fn($qq) => $qq->leftJoinSub(DB::table('pathao_invoice_lines')->selectRaw('consignment_id, max(fee) as fee')->where('fee', '>', 0)->groupBy('consignment_id'), 'pl', 'pl.consignment_id', '=', 'o.consignment_id'))
      ->whereDate('o.created_at', '>=', $f['from'])->whereDate('o.created_at', '<=', $f['to']);
    // delivered mode also keeps parcels that came back: their sale is reversed by the return, and the courier cost they cost us is still a parcel loss
    if ($f['status'] === 'delivered') $q->whereIn('o.status', ['Delivered', 'Returned', 'Return Pending']); else $q->whereNotIn('o.status', ['Cancelled', 'Canceled', 'Failed', 'Cancelled by Customer']);
    if ($f['store'] === 'main') $q->whereNull('o.store_id'); elseif (ctype_digit($f['store'])) $q->where('o.store_id', (int)$f['store']);
    if (ctype_digit($f['cat'])) $q->whereRaw('coalesce(i.cat_id, p.category_id) = ?', [(int)$f['cat']]);
    if ($f['sub'] !== '') $q->whereRaw("coalesce(nullif(i.cat_sub,''), nullif(p.sub,''), '—') = ?", [$f['sub']]);
    if ($f['child'] !== '') $q->whereRaw("coalesce(nullif(i.cat_sub_sub,''), nullif(p.sub_sub,''), '—') = ?", [$f['child']]);
    if (ctype_digit($f['brand'])) $q->where('p.brand_id', (int)$f['brand']);
    if ($f['q'] !== '') { $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $f['q']) . '%'; $q->where(fn($w) => $w->where('i.name', 'ilike', $like)->orWhere('p.sku', 'ilike', $like)->orWhere('c.name', 'ilike', $like)->orWhere('p.sub', 'ilike', $like)->orWhere('p.sub_sub', 'ilike', $like)); }
    if ($f['pay'] !== '') $q->where('o.payment_method', $f['pay']);
    if ($f['courier'] !== '') $q->where('o.courier', $f['courier']);
    if ($f['appr'] !== '' && Schema::hasColumn('orders', 'approval_source')) $q->where('o.approval_source', $f['appr']);
    if ($f['ccat'] !== '' && Schema::hasColumn('orders', 'customer_category')) $q->where('o.customer_category', $f['ccat']);
    if ($f['mem'] !== '' && Schema::hasColumn('orders', 'membership')) $q->where('o.membership', $f['mem']);
    return $q;
  }

  /** Per-line money, in SQL. ret = returned qty, disc = share of the order discount, net, cogs kept, cogs of returned goods. */
  private const M = [
    'ret' => "(case when (FULL) then i.qty else least(i.qty, greatest(0, coalesce(i.ret_qty,0))) end)",
  ];
  private static function hasInv(): bool { static $v = null; try { return $v ??= Schema::hasTable('pathao_invoice_lines') && Schema::hasColumn('pathao_invoice_lines', 'fee') && Schema::hasColumn('pathao_invoice_lines', 'consignment_id'); } catch (\Throwable $e) { return false; } }
  private static function hasRet(): bool { static $v = null; try { return $v ??= Schema::hasColumn('orders', 'courier_return_fee'); } catch (\Throwable $e) { return false; } }
  /** Courier cost of one parcel (the whole order), same order of rules as CourierBook::fee. */
  public static function courierOrderSql(?float $flat = null, ?bool $inv = null, ?bool $ret = null): string {
    $flat = $flat ?? max(0.0, (float)self::setting('pathao_flat_charge', '90')); $inv = $inv ?? self::hasInv(); $ret = $ret ?? self::hasRet();
    $fee = ($inv ? 'nullif(pl.fee,0), ' : '') . "(case when coalesce(nullif(o.courier,''),'Pathao') = 'Pathao' and " . (float)$flat . " > 0 then " . (float)$flat . " else coalesce(o.courier_fee,0) end)";
    $full = self::FULL_RET;
    return "(case when coalesce(o.consignment_id::text,'') = '' then 0 else coalesce($fee) + (case when ($full) then " . ($ret ? 'coalesce(o.courier_return_fee,0)' : '0') . " else 0 end) end)";
  }
  private static function agg(): string {
    $full = self::FULL_RET; $ret = str_replace('FULL', $full, self::M['ret']);
    $gross = '(i.price * i.qty)';
    $disc = "(case when ($full) then 0 else coalesce(o.discount,0) * $gross / nullif(og.g,0) end)";
    $retAmt = "(i.price * $ret)";
    $cogsKept = "(case when i.cogs is not null and i.qty > 0 then i.cogs * (i.qty - $ret) / i.qty else coalesce(p.cost,0) * (i.qty - $ret) end)";
    // parcel cost per order = the real Pathao payout-invoice fee, else the fixed Pathao charge (setting pathao_flat_charge), else the saved courier fee; a fully returned parcel also pays its return fee. Spread over the order's lines by their share of the order gross.
    $cour = '(' . self::courierOrderSql() . " * $gross / nullif(og.g,0))";
    $cogsRet = "(case when i.cogs is not null and i.qty > 0 then i.cogs * $ret / i.qty else coalesce(p.cost,0) * $ret end)";
    return "count(distinct o.id) as orders, count(distinct case when o.status = 'Delivered' then o.id end) as delivered, sum(i.qty) as qty, sum($ret) as ret_qty,
      sum($gross) as gross, sum($disc) as disc, sum($retAmt) as ret_amt, sum($gross - $disc - $retAmt) as net, sum($cogsKept) as cogs, sum($cogsRet) as cogs_rev,
      sum($gross - $disc - $retAmt - $cogsKept) as profit, sum($cour) as courier, sum(case when coalesce(i.cogs,0) = 0 and coalesce(p.cost,0) = 0 then $gross - $disc - $retAmt else 0 end) as nocost_sales";
  }

  private static function setting(string $k, string $d): float { try { return (float)Procure::set($k, $d); } catch (\Throwable $e) { return (float)$d; } }

  public static function status(float $net, float $profit, float $margin, float $prot): string {
    if ($profit < 0) return 'Loss';
    if ($net <= 0) return 'Watch';
    $healthy = self::setting('catprofit_healthy_percent', '15'); $strong = self::setting('catprofit_strong_percent', '25');
    if ($margin < $prot) return 'Low Margin'; if ($margin < $healthy) return 'Watch'; if ($margin < $strong) return 'Healthy'; return 'Strong';
  }

  private static function shape($x, array $tot, float $prot): array {
    $net = (float)$x->net; $gp = (float)$x->profit; $qty = (int)$x->qty; $gross = (float)$x->gross; $del = (int)$x->delivered;
    $margin = $net > 0 ? round($gp / $net * 100, 2) : 0.0;
    return ['orders' => (int)$x->orders, 'delivered' => $del, 'qty' => $qty, 'gross' => round($gross, 2), 'disc' => round((float)$x->disc, 2), 'ret_qty' => (int)$x->ret_qty, 'ret_amt' => round((float)$x->ret_amt, 2),
      'net' => round($net, 2), 'cogs' => round((float)$x->cogs, 2), 'cogs_rev' => round((float)$x->cogs_rev, 2), 'profit' => round($gp, 2), 'margin' => $margin, 'courier' => round((float)$x->courier, 2), 'net_profit' => round($gp - (float)$x->courier, 2), 'net_margin' => $net > 0 ? round(($gp - (float)$x->courier) / $net * 100, 2) : 0.0,
      'avg_order' => $del > 0 ? round($gp / $del, 2) : 0.0, 'avg_unit' => $qty > 0 ? round($gp / $qty, 2) : 0.0,
      'ret_rate' => $qty > 0 ? round((int)$x->ret_qty / $qty * 100, 2) : 0.0, 'disc_rate' => $gross > 0 ? round((float)$x->disc / $gross * 100, 2) : 0.0,
      'profit_share' => ($tot['profit'] ?? 0) != 0 ? round($gp / $tot['profit'] * 100, 2) : 0.0, 'sales_share' => ($tot['net'] ?? 0) != 0 ? round($net / $tot['net'] * 100, 2) : 0.0,
      'profit_before_disc' => round($gp + (float)$x->disc, 2), 'nocost_sales' => round((float)$x->nocost_sales, 2),
      'below' => $net > 0 && $margin < $prot, 'status' => self::status($net, $gp, $margin, $prot)];
  }

  public static function totals(array $f): array {
    $x = self::lines($f)->selectRaw(self::agg())->first(); $prot = ProfitFloor::percent();
    $t = self::shape($x, [], $prot); $t['profit_share'] = $t['profit'] != 0 ? 100.0 : 0.0; $t['sales_share'] = $t['net'] != 0 ? 100.0 : 0.0; return $t;
  }

  /** Lines with no category at all (after the name match), grouped by name. */
  public static function uncategorized(array $f) {
    return self::lines($f)->whereRaw('coalesce(i.cat_id, p.category_id) is null')
      ->selectRaw("max(coalesce(p.name, i.name)) as name, coalesce(p.id, 0) as pid, max(p.sku) as sku, count(distinct o.id) as orders, sum(i.qty) as qty, sum(i.price * i.qty) as gross, sum(i.price * i.qty) / nullif(sum(i.qty), 0) as avgp")
      ->groupByRaw('coalesce(p.id, 0), lower(trim(coalesce(p.name, i.name)))')->orderByDesc('gross')->get();
  }

  /** level: main | sub | child | product | order. $p = the parent keys picked on the way down. */
  public static function rows(array $f, string $level, array $p): array {
    $key = 'catprofit.' . md5(json_encode([$f, $level, $p]));
    return Cache::remember($key, 60, function () use ($f, $level, $p) {
      $prot = ProfitFloor::percent(); $tot = self::totals($f);
      $q = self::lines($f);
      $mainExpr = 'coalesce(i.cat_id, p.category_id, 0)'; $subExpr = "coalesce(nullif(i.cat_sub,''), nullif(p.sub,''), '—')"; $childExpr = "coalesce(nullif(i.cat_sub_sub,''), nullif(p.sub_sub,''), '—')";
      if (isset($p['main']) && $p['main'] !== '') $q->whereRaw("$mainExpr = ?", [(int)$p['main']]);
      if (isset($p['sub']) && $p['sub'] !== '') $q->whereRaw("$subExpr = ?", [$p['sub']]);
      if (isset($p['child']) && $p['child'] !== '') $q->whereRaw("$childExpr = ?", [$p['child']]);
      if ($level === 'order') {
        if (isset($p['product'])) $q->whereRaw('coalesce(p.id, 0) = ?', [(int)$p['product']]);
        $full = self::FULL_RET; $ret = str_replace('FULL', $full, self::M['ret']);
        $rows = $q->selectRaw("o.id as oid, o.invoice, o.created_at, o.status, i.qty, i.price, ($ret) as ret_qty, (i.price * i.qty) as gross,
          (case when ($full) then 0 else coalesce(o.discount,0) * (i.price * i.qty) / nullif(og.g,0) end) as disc,
          (case when i.cogs is not null and i.qty > 0 then i.cogs * (i.qty - $ret) / i.qty else coalesce(p.cost,0) * (i.qty - $ret) end) as cogs, (i.cogs is not null) as fifo")
          ->orderByDesc('o.created_at')->limit(200)->get()->map(function ($x) {
            $net = (float)$x->gross - (float)$x->disc - (float)$x->price * (int)$x->ret_qty; $gp = $net - (float)$x->cogs;
            return ['oid' => (int)$x->oid, 'invoice' => Inv::show($x->invoice), 'date' => substr((string)$x->created_at, 0, 10), 'qty' => (int)$x->qty, 'price' => round((float)$x->price, 2), 'disc' => round((float)$x->disc, 2),
              'cogs' => round((float)$x->cogs, 2), 'fifo' => (bool)$x->fifo, 'net' => round($net, 2), 'profit' => round($gp, 2), 'margin' => $net > 0 ? round($gp / $net * 100, 1) : 0.0,
              'ret' => (int)$x->ret_qty > 0 ? ((int)$x->ret_qty >= (int)$x->qty ? 'Returned' : 'Partial · ' . (int)$x->ret_qty) : '—'];
          })->all();
        return ['rows' => $rows];
      }
      if ($level === 'product') $q->selectRaw("coalesce(p.id, 0) as k, max(coalesce(p.name, i.name)) as name, max(p.sku) as sku, " . self::agg())->groupByRaw('coalesce(p.id, 0)');
      elseif ($level === 'sub') $q->selectRaw("$subExpr as k, $subExpr as name, " . self::agg())->groupByRaw($subExpr);
      elseif ($level === 'child') $q->selectRaw("$childExpr as k, $childExpr as name, " . self::agg())->groupByRaw($childExpr);
      else $q->selectRaw("$mainExpr as k, max(coalesce(c.name, 'Uncategorized')) as name, " . self::agg())->groupByRaw($mainExpr);
      $rows = [];
      foreach ($q->get() as $x) {
        $r = self::shape($x, $tot, $prot) + ['key' => (string)$x->k, 'name' => (string)$x->name, 'sku' => $x->sku ?? null];
        $r['avg_price'] = $r['qty'] > 0 ? round($r['gross'] / $r['qty'], 2) : 0.0; $r['avg_cogs'] = ($r['qty'] - $r['ret_qty']) > 0 ? round($r['cogs'] / ($r['qty'] - $r['ret_qty']), 2) : 0.0;
        $rows[] = $r;
      }
      usort($rows, fn($a, $b) => $b['profit'] <=> $a['profit']);
      return ['rows' => $rows];
    });
  }

  /** Deterministic insights, only from the report numbers. */
  public static function insights(array $rows): array {
    $r = array_values(array_filter($rows, fn($x) => $x['net'] != 0 || $x['profit'] != 0)); if (!$r) return [];
    $by = function ($k, $dir = 1) use ($r) { $c = $r; usort($c, fn($a, $b) => $dir * ($b[$k] <=> $a[$k])); return $c[0]; };
    $out = []; $pc = fn($x) => $x['name'];
    $a = $by('profit'); $out[] = ['k' => 'Highest profit', 'v' => $pc($a), 'n' => number_format($a['profit']) . ' · ' . $a['profit_share'] . '% of profit'];
    $a = $by('margin'); $out[] = ['k' => 'Highest margin', 'v' => $pc($a), 'n' => $a['margin'] . '%'];
    $withSales = array_values(array_filter($r, fn($x) => $x['net'] > 0)); if ($withSales) { usort($withSales, fn($a, $b) => $a['margin'] <=> $b['margin']); $out[] = ['k' => 'Lowest margin', 'v' => $withSales[0]['name'], 'n' => $withSales[0]['margin'] . '%']; }
    $a = $by('net'); $out[] = ['k' => 'Highest sales', 'v' => $pc($a), 'n' => number_format($a['net']) . ' · ' . $a['sales_share'] . '% of sales'];
    $a = $by('ret_rate'); if ($a['ret_rate'] > 0) $out[] = ['k' => 'Highest return rate', 'v' => $pc($a), 'n' => $a['ret_rate'] . '%'];
    $a = $by('disc_rate'); if ($a['disc_rate'] > 0) $out[] = ['k' => 'Highest discount impact', 'v' => $pc($a), 'n' => $a['disc_rate'] . '% of gross'];
    foreach ($r as $x) if ($x['sales_share'] - $x['profit_share'] >= 10 && $x['sales_share'] >= 5) $out[] = ['k' => 'High sales, low profit', 'v' => $x['name'], 'n' => $x['sales_share'] . '% of sales but ' . $x['profit_share'] . '% of profit'];
    foreach ($r as $x) if ($x['profit_share'] - $x['sales_share'] >= 8 && $x['sales_share'] <= 20) $out[] = ['k' => 'High margin, small sales', 'v' => $x['name'], 'n' => $x['sales_share'] . '% of sales but ' . $x['profit_share'] . '% of profit'];
    return $out;
  }

  /** Second, independent calculation (the same line rules as the Profit & Loss Statement, done in PHP) to prove the SQL totals. */
  public static function reconcile(array $f, array $tot): array {
    $n = (clone self::lines($f))->count(); if ($n > 60000) return ['ok' => null, 'note' => 'Skipped: more than 60,000 lines in this range.'];
    $net = 0.0; $cogs = 0.0; $cour = 0.0;
    $og = [];
    foreach (self::lines($f)->selectRaw('i.id as iid, i.order_id, i.qty, i.price, i.ret_qty, i.cogs, p.cost, o.status, o.return_type, o.discount, og.g, o.courier_fee, o.consignment_id, o.courier, ' . (self::hasInv() ? 'pl.fee' : 'null') . ' as plfee, ' . (self::hasRet() ? 'o.courier_return_fee' : 'null') . ' as retfee')->orderBy('i.id')->cursor() as $x) {
      $full = in_array($x->status, ['Return Pending', 'Returned'], true) && ($x->return_type ?? '') !== 'Partial';
      $q0 = (int)$x->qty; $kept = $full ? 0 : max(0, $q0 - max(0, (int)$x->ret_qty));
      $b = ($x->cogs !== null && $q0 > 0) ? (float)$x->cogs * $kept / $q0 : (float)($x->cost ?? 0) * $kept;
      $gross = (float)$x->price * $q0; $disc = $full || (float)$x->g == 0.0 ? 0.0 : (float)$x->discount * $gross / (float)$x->g;
      $net += (float)$x->price * $kept - $disc; $cogs += $b;
      if (!empty($x->consignment_id) && (float)$x->g != 0.0) {
        $flat = max(0.0, (float)self::setting('pathao_flat_charge', '90'));
        $pc = (float)($x->plfee ?? 0) > 0 ? (float)$x->plfee : ((($x->courier ?: 'Pathao') === 'Pathao' && $flat > 0) ? $flat : (float)($x->courier_fee ?? 0));
        if ($full) $pc += (float)($x->retfee ?? 0);
        $cour += $pc * $gross / (float)$x->g;
      }
    }
    $d = fn($a, $b) => abs($a - $b) <= 1.0;
    $ok = $d($net, $tot['net']) && $d($cogs, $tot['cogs']) && $d($net - $cogs, $tot['profit']) && $d($net - $cogs - $cour, $tot['net_profit']);
    return ['ok' => $ok, 'note' => $ok ? 'Net sales, COGS, gross profit and net profit (after courier) match the line-by-line check.' : 'Report Reconciliation Issue: SQL net ' . round($tot['net']) . ' / COGS ' . round($tot['cogs']) . ' vs check ' . round($net) . ' / ' . round($cogs)];
  }

  public static function previous(array $f): array {
    $days = max(1, (int)((strtotime($f['to']) - strtotime($f['from'])) / 86400) + 1);
    $p = $f; $p['to'] = date('Y-m-d', strtotime($f['from'] . ' -1 day')); $p['from'] = date('Y-m-d', strtotime($p['to'] . ' -' . ($days - 1) . ' days')); return $p;
  }

  public static function options(): array {
    $o = ['stores' => [], 'cats' => [], 'brands' => [], 'pay' => [], 'courier' => []];
    try { $o['stores'] = Schema::hasTable('stores') ? DB::table('stores')->orderBy('name')->get(['id', 'name'])->all() : []; } catch (\Throwable $e) {}
    try { $o['cats'] = DB::table('categories')->whereNull('parent_id')->orderBy('name')->get(['id', 'name'])->all(); } catch (\Throwable $e) {}
    try { $o['brands'] = Schema::hasTable('brands') ? DB::table('brands')->orderBy('name')->limit(400)->get(['id', 'name'])->all() : []; } catch (\Throwable $e) {}
    try { $o['pay'] = DB::table('orders')->whereNotNull('payment_method')->distinct()->orderBy('payment_method')->pluck('payment_method')->all(); } catch (\Throwable $e) {}
    try { $o['courier'] = DB::table('orders')->whereNotNull('courier')->where('courier', '<>', '')->distinct()->orderBy('courier')->pluck('courier')->all(); } catch (\Throwable $e) {}
    return $o;
  }
}
