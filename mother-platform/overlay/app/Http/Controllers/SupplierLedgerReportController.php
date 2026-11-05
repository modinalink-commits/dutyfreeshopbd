<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Supplier ledger in grid layout: one group per supplier with opening balance, dated purchase/payment rows and running balance. */
class SupplierLedgerReportController extends Controller {
  public function index(Request $r) {
    $from = $r->query('from') ?: now()->subDays(29)->toDateString(); $to = $r->query('to') ?: now()->toDateString();
    $sup = (int)$r->query('supplier', 0); $q = trim((string)$r->query('q', ''));
    $sq = DB::table('suppliers')->orderBy('name'); if ($sup) $sq->where('id', $sup); if ($q !== '') $sq->where('name', 'ilike', '%' . $q . '%');
    $suppliers = $sq->limit(60)->get(['id', 'name', 'phone']); $ids = $suppliers->pluck('id')->all();
    // Same calculation as the full Supplier Ledger (Purchase module): opening balance, purchases, payments, returns, damage claims and debit/credit notes.
    $groups = []; $g = ['open' => 0, 'debit' => 0, 'credit' => 0, 'close' => 0];
    foreach ($suppliers as $s) {
      $L = \App\Support\Procure::events((int)$s->id, $from, $to);
      $open = (float)$L['opening']; $bal = (float)$L['closing']; $rows = []; $dr = 0.0; $cr = 0.0;
      foreach ($L['rows'] as $e) { $rows[] = ['d' => substr((string)$e['d'], 0, 10), 't' => $e['t'], 'ref' => $e['ref'], 'dr' => (float)$e['credit'], 'cr' => (float)$e['debit'], 'bal' => (float)$e['bal']]; $dr += (float)$e['credit']; $cr += (float)$e['debit']; }
      if (!$rows && abs($open) < 0.005) continue;
      $groups[] = ['s' => $s, 'open' => $open, 'rows' => $rows, 'dr' => $dr, 'cr' => $cr, 'close' => $bal];
      $g['open'] += $open; $g['debit'] += $dr; $g['credit'] += $cr; $g['close'] += $bal;
    }
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Supplier', 'Date', 'Type', 'Reference', 'Increase (purchase, credit note)', 'Reduction (payment, return, claim, debit note)', 'Balance']);
      foreach ($groups as $gr) { fputcsv($out, [$gr['s']->name, $from, 'Opening', '', '', '', $gr['open']]); foreach ($gr['rows'] as $x) fputcsv($out, [$gr['s']->name, $x['d'], $x['t'], $x['ref'], $x['dr'], $x['cr'], $x['bal']]); }
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="supplier-ledger-' . $from . '-' . $to . '.csv"']);
    }
    $all = DB::table('suppliers')->orderBy('name')->get(['id', 'name']);
    if ($r->query('export') === 'doc') { $html = view('supplier_ledger_report', ['groups' => $groups, 'g' => $g, 'from' => $from, 'to' => $to, 'sup' => $sup, 'q' => $q, 'all' => $all])->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="supplier-ledger.doc"']); }
    return view('supplier_ledger_report', ['groups' => $groups, 'g' => $g, 'from' => $from, 'to' => $to, 'sup' => $sup, 'q' => $q, 'all' => $all]);
  }
}
