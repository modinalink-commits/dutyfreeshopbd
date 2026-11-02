<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Income and expense (daily cash) in grid layout: one group per account with dated rows, subtotal and grand total. */
class CashbookReportController extends Controller {
  public function index(Request $r) {
    $from = $r->query('from') ?: now()->startOfMonth()->toDateString(); $to = $r->query('to') ?: now()->toDateString();
    $acc = (int)$r->query('account', 0); $dir = (string)$r->query('dir', ''); $q = trim((string)$r->query('q', '')); $per = max(50, min(2000, (int)($r->query('per') ?: 500)));
    $eq = DB::table('account_entries as e')->leftJoin('money_accounts as a', 'a.id', '=', 'e.account_id')->whereDate('e.entry_date', '>=', $from)->whereDate('e.entry_date', '<=', $to);
    if ($acc) $eq->where('e.account_id', $acc); if (in_array($dir, ['income', 'expense'], true)) $eq->where('e.direction', $dir);
    if ($q !== '') { $like = '%' . $q . '%'; $eq->where(fn($w) => $w->where('e.reference', 'ilike', $like)->orWhere('e.comment', 'ilike', $like)->orWhere('a.name', 'ilike', $like)); }
    $total = (clone $eq)->count();
    $rows = $eq->orderBy('a.name')->orderBy('e.entry_date')->orderBy('e.id')->limit($per)->get(['e.entry_date as d', 'e.reference', 'e.direction', 'e.amount', 'e.comment', 'a.name as acc']);
    $groups = []; $g = ['in' => 0, 'out' => 0, 'n' => 0];
    foreach ($rows->groupBy('acc') as $name => $list) {
      $gr = ['name' => $name ?: 'No account', 'rows' => [], 'in' => 0, 'out' => 0];
      foreach ($list as $x) { $in = $x->direction === 'income' ? (float)$x->amount : 0; $out = $x->direction === 'expense' ? (float)$x->amount : 0; $gr['in'] += $in; $gr['out'] += $out;
        $gr['rows'][] = ['d' => substr((string)$x->d, 0, 10), 'ref' => $x->reference, 'c' => $x->comment, 'in' => $in, 'out' => $out]; }
      $groups[] = $gr; $g['in'] += $gr['in']; $g['out'] += $gr['out']; $g['n'] += count($gr['rows']);
    }
    $accounts = DB::table('money_accounts')->orderBy('name')->get(['id', 'name']);
    $data = ['groups' => $groups, 'g' => $g, 'from' => $from, 'to' => $to, 'acc' => $acc, 'dir' => $dir, 'q' => $q, 'per' => $per, 'total' => $total, 'accounts' => $accounts];
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Account', 'Date', 'Reference', 'Note', 'Income', 'Expense']);
      foreach ($groups as $gr) foreach ($gr['rows'] as $x) fputcsv($out, [$gr['name'], $x['d'], $x['ref'], $x['c'], $x['in'], $x['out']]);
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="cashbook-' . $from . '-' . $to . '.csv"']);
    }
    if ($r->query('export') === 'doc') { $html = view('cashbook_report', $data)->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="cashbook.doc"']); }
    return view('cashbook_report', $data);
  }
}
