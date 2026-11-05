<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Order history (delivered / returned / cancelled / all) grouped by status, one compact row per order. */
class OrderHistoryReportController extends Controller {
  public function index(Request $r) {
    $from = $r->query('from') ?: now()->subDays(29)->toDateString(); $to = $r->query('to') ?: now()->toDateString();
    $status = (string)$r->query('status', 'all'); $q = trim((string)$r->query('q', '')); $per = max(100, min(3000, (int)($r->query('per') ?: 1000)));
    $oq = DB::table('orders')->whereDate('created_at', '>=', $from)->whereDate('created_at', '<=', $to);
    if ($status !== '' && $status !== 'all') $oq->where('status', $status);
    if ($q !== '') { $like = '%' . $q . '%'; $oq->where(fn($w) => $w->where('invoice', 'ilike', $like)->orWhere('customer_name', 'ilike', $like)->orWhere('phone', 'like', $like)); }
    $total = (clone $oq)->count();
    $rows = $oq->orderBy('status')->orderByDesc('created_at')->orderByDesc('id')->limit($per)->get(['id', 'invoice', 'customer_name', 'phone', 'status', 'discount', 'shipping', 'total', 'payment_method', 'created_at']);
    $groups = []; $g = ['n' => 0, 'total' => 0, 'disc' => 0, 'ship' => 0];
    foreach ($rows->groupBy('status') as $st => $list) {
      $gr = ['name' => $st ?: '—', 'rows' => $list, 'n' => $list->count(), 'total' => $list->sum(fn($x) => (float)$x->total), 'disc' => $list->sum(fn($x) => (float)$x->discount), 'ship' => $list->sum(fn($x) => (float)$x->shipping)];
      $groups[] = $gr; foreach (['n', 'total', 'disc', 'ship'] as $k) $g[$k] += $gr[$k];
    }
    $statuses = DB::table('orders')->select('status')->distinct()->orderBy('status')->pluck('status');
    $data = compact('groups', 'g', 'from', 'to', 'status', 'q', 'per', 'total', 'statuses');
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Status', 'Invoice', 'Date', 'Customer', 'Phone', 'Payment', 'Discount', 'Delivery', 'Total']);
      foreach ($groups as $gr) foreach ($gr['rows'] as $x) fputcsv($out, [$gr['name'], \App\Support\Inv::show($x->invoice), substr((string)$x->created_at, 0, 10), $x->customer_name, $x->phone, $x->payment_method, $x->discount, $x->shipping, $x->total]);
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="order-history-' . $from . '-' . $to . '.csv"']);
    }
    if ($r->query('export') === 'doc') { $html = view('order_history_report', $data)->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="order-history.doc"']); }
    return view('order_history_report', $data);
  }
}
