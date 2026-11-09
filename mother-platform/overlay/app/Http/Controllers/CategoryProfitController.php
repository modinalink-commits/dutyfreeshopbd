<?php
namespace App\Http\Controllers;
use App\Support\CategoryProfit;
use App\Support\ProfitFloor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Reports → Profit & Loss → Category Wise Profit. Numbers come only from App\Support\CategoryProfit (server side). */
class CategoryProfitController extends Controller {
  public function index(Request $r) {
    if ($r->query('export') === 'csv') return $this->export($r);
    if ($r->query('export') === 'uncat') return $this->uncat($r);
    if ($r->query('export') === 'cats') return $this->catAudit();
    return view('category_profit', ['f' => CategoryProfit::filters($r), 'opts' => CategoryProfit::options(), 'prot' => ProfitFloor::percent()]);
  }

  /** CSV (opens in Excel): every product / order line that has no category, so the owner can set categories by hand. */
  public function uncat(Request $r) {
    $rows = CategoryProfit::uncategorized(CategoryProfit::filters($r));
    return response()->streamDownload(function () use ($rows) {
      $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
      fputcsv($o, ['Product name', 'Product ID', 'SKU', 'Orders', 'Qty sold', 'Gross sales', 'Avg price', 'Product link', 'Suggested category (fill in)']);
      foreach ($rows as $x) fputcsv($o, [$x->name, $x->pid ?: '', $x->sku ?? '', $x->orders, $x->qty, round((float)$x->gross, 2), round((float)$x->avgp, 2), $x->pid ? 'linked' : 'NO PRODUCT LINK', '']);
      fclose($o);
    }, 'uncategorized-products-' . date('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
  }

  /** CSV audit of every main / sub / child category: counts, odd names, empty slugs, and (for odd main categories) their products. Read-only. */
  public function catAudit() {
    $sent = function (string $n) { $t = trim(preg_replace('/\s+/u', ' ', $n)); if ($t === '') return $t; if ($t !== mb_strtoupper($t) && $t !== mb_strtolower($t)) return $t; $w = explode(' ', mb_strtolower($t)); foreach ($w as $i => $x) { if (mb_strlen($x) <= 3 && preg_match('/^[a-z0-9&]+$/u', $x) && mb_strtoupper($x) === mb_strtoupper($x) && in_array($x, ['lm', 'xso', 'uk', 'usa', 'edt', 'edp'], true)) $w[$i] = mb_strtoupper($x); } $o = implode(' ', $w); return mb_strtoupper(mb_substr($o, 0, 1)) . mb_substr($o, 1); };
    $mains = DB::table('categories')->whereNull('parent_id')->orderBy('position')->get();
    $nodes = DB::table('category_nodes')->orderBy('position')->get();
    $cnt = []; foreach (DB::table('products')->select('category_id', 'sub', 'sub_sub', DB::raw('count(*) n'))->groupBy('category_id', 'sub', 'sub_sub')->get() as $x) { $cnt['m' . $x->category_id] = ($cnt['m' . $x->category_id] ?? 0) + $x->n; if ($x->sub) $cnt['s' . $x->category_id . '|' . $x->sub] = ($cnt['s' . $x->category_id . '|' . $x->sub] ?? 0) + $x->n; if ($x->sub && $x->sub_sub) $cnt['t' . $x->category_id . '|' . $x->sub . '|' . $x->sub_sub] = $x->n; }
    $norm = fn($n) => preg_replace('/[^a-z0-9]+/', '', str_replace('&', 'and', mb_strtolower((string)$n)));
    $dupMain = []; foreach ($mains as $m) $dupMain[$norm($m->name)][] = $m->name;
    $mainNames = $mains->pluck('name', 'id');
    $rows = [];
    $issue = function (string $name, $slug, int $n, array $extra = []) use ($sent) { $i = $extra; if ($name === mb_strtoupper($name) && preg_match('/[A-Za-z]{4,}/', $name)) $i[] = 'ALL CAPS name'; if ($slug === null || $slug === '' || $slug === '/') $i[] = 'Empty slug'; if ($n === 0) $i[] = 'No products'; return implode('; ', $i); };
    foreach ($mains as $m) {
      $n = (int)($cnt['m' . $m->id] ?? 0); $ex = count($dupMain[$norm($m->name)]) > 1 ? ['Similar name exists twice'] : [];
      $rows[] = ['Main', $m->id, $m->name, '', $m->slug, $n, $m->active ? 'Active' : 'Inactive', $sent($m->name), $issue($m->name, $m->slug, $n, $ex)];
      foreach ($nodes->where('category_id', $m->id)->where('level', 2) as $sN) {
        $n2 = (int)($cnt['s' . $m->id . '|' . $sN->name] ?? 0);
        $rows[] = ['Sub', $sN->id, $sN->name, $m->name, $sN->slug, $n2, $sN->active ? 'Active' : 'Inactive', $sent($sN->name), $issue($sN->name, $sN->slug, $n2)];
        foreach ($nodes->where('parent_id', $sN->id)->where('level', 3) as $t) { $n3 = (int)($cnt['t' . $m->id . '|' . $sN->name . '|' . $t->name] ?? 0); $rows[] = ['Child', $t->id, $t->name, $m->name . ' > ' . $sN->name, $t->slug, $n3, $t->active ? 'Active' : 'Inactive', $sent($t->name), $issue($t->name, $t->slug, $n3)]; }
      }
    }
    $odd = $mains->filter(fn($m) => $m->slug === null || $m->slug === '' || $m->slug === '/' || mb_strtoupper($m->name) === $m->name)->pluck('id')->all();
    $prods = $odd ? DB::table('products')->whereIn('category_id', $odd)->orderBy('category_id')->orderBy('name')->get(['id', 'sku', 'name', 'category_id', 'sub', 'sub_sub']) : collect();
    return response()->streamDownload(function () use ($rows, $prods, $mainNames) {
      $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
      fputcsv($o, ['SECTION 1 - ALL CATEGORIES']); fputcsv($o, ['Level', 'ID', 'Current name', 'Parent', 'Slug', 'Products', 'Status', 'Proposed name (sentence case)', 'Issues']);
      foreach ($rows as $r) fputcsv($o, $r);
      fputcsv($o, []); fputcsv($o, ['SECTION 2 - PRODUCTS INSIDE ODD MAIN CATEGORIES (ALL CAPS or empty slug) - fill the right main/sub category']);
      fputcsv($o, ['Product ID', 'SKU', 'Product name', 'Current main', 'Current sub', 'Current child', 'Correct main category (fill in)', 'Correct sub category (fill in)']);
      foreach ($prods as $p) fputcsv($o, [$p->id, $p->sku, $p->name, $mainNames[$p->category_id] ?? '', $p->sub, $p->sub_sub, '', '']);
      fclose($o);
    }, 'category-audit-' . date('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
  }

  /** JSON for the page: level main (with cards, insights, comparison, reconcile) or a lazy drill level. */
  public function data(Request $r) {
    $f = CategoryProfit::filters($r); $level = in_array($r->query('level'), ['main', 'sub', 'child', 'product', 'order'], true) ? $r->query('level') : 'main';
    $p = ['main' => (string)$r->query('pm', ''), 'sub' => (string)$r->query('ps', ''), 'child' => (string)$r->query('pc', '')]; if ($r->query('pp') !== null) $p['product'] = (int)$r->query('pp');
    $out = CategoryProfit::rows($f, $level, $p);
    if ($level === 'main') {
      $tot = CategoryProfit::totals($f); $out['totals'] = $tot; $out['prot'] = ProfitFloor::percent(); $out['insights'] = CategoryProfit::insights($out['rows']);
      $out['recon'] = CategoryProfit::reconcile($f, $tot);
      if ($f['cmp'] === '1') { $pf = CategoryProfit::previous($f); $out['prev'] = CategoryProfit::totals($pf); $out['prev_range'] = [$pf['from'], $pf['to']]; }
      $best = null; foreach ($out['rows'] as $x) if ($x['net'] > 0 && ($best === null || $x['margin'] > $best['margin'])) $best = $x; $out['best_margin'] = $best['name'] ?? null;
    }
    return response()->json($out);
  }

  private function export(Request $r) {
    $f = CategoryProfit::filters($r); $prot = ProfitFloor::percent(); $out = fopen('php://temp', 'w+');
    $applied = collect($f)->except(['from', 'to'])->filter(fn($v) => $v !== '')->map(fn($v, $k) => $k . '=' . $v)->implode('; ');
    fputcsv($out, ['Category Wise Profit']); fputcsv($out, ['Date range', $f['from'] . ' to ' . $f['to']]); fputcsv($out, ['Store', $f['store'] === '' ? 'All stores' : $f['store']]);
    fputcsv($out, ['Applied filters', $applied ?: 'none']); fputcsv($out, ['Generated', now()->format('Y-m-d H:i')]); fputcsv($out, ['Generated by', Auth::user()->name ?? '']); fputcsv($out, []);
    fputcsv($out, ['Level', 'Category', 'Orders', 'Delivered orders', 'Qty sold', 'Gross sales', 'Discount', 'Return qty', 'Return amount', 'Net sales', 'COGS (FIFO)', 'Gross profit', 'Margin %', 'Courier cost', 'Net profit (after courier)', 'Net margin %', 'Avg profit/order', 'Avg profit/unit', 'Return rate %', 'Discount rate %', 'Profit share %', 'Sales share %', 'Status']);
    $row = fn($lvl, $x, $pad) => fputcsv($out, [$lvl, $pad . $x['name'], $x['orders'], $x['delivered'], $x['qty'], $x['gross'], $x['disc'], $x['ret_qty'], $x['ret_amt'], $x['net'], $x['cogs'], $x['profit'], $x['margin'], $x['courier'], $x['net_profit'], $x['net_margin'], $x['avg_order'], $x['avg_unit'], $x['ret_rate'], $x['disc_rate'], $x['profit_share'], $x['sales_share'], $x['status'] . ($x['below'] ? ' · below ' . $prot . '% target' : '')]);
    foreach (CategoryProfit::rows($f, 'main', [])['rows'] as $m) {
      $row('Main', $m, '');
      foreach (CategoryProfit::rows($f, 'sub', ['main' => $m['key']])['rows'] as $s) {
        $row('Sub', $s, '   ');
        foreach (CategoryProfit::rows($f, 'child', ['main' => $m['key'], 'sub' => $s['key']])['rows'] as $c) $row('Child', $c, '      ');
      }
    }
    $t = CategoryProfit::totals($f); $row('Total', ['name' => 'TOTAL'] + $t + ['status' => '', 'below' => false], '');
    try { DB::table('audit_logs')->insert(['user_id' => Auth::id(), 'user_name' => Auth::user()->name ?? '', 'action' => 'export', 'subject' => 'PRF-005 csv', 'meta' => json_encode(['report' => 'PRF-005', 'format' => 'csv', 'filters' => $f], JSON_UNESCAPED_UNICODE), 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="category-profit-' . $f['from'] . '-' . $f['to'] . '.csv"']);
  }
}
