<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Cancelled orders by the reason staff chose (orders.cancel_reason), with how many were repeats of another order from the same phone. Read only. */
class CancelReportController extends Controller {
  public function index(Request $r) {
    $tz = 'Asia/Dhaka';
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r->query('from')) ? (string)$r->query('from') : now($tz)->subDays(6)->toDateString();
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r->query('to')) ? (string)$r->query('to') : now($tz)->toDateString();
    $a = \Carbon\Carbon::parse($from, $tz)->startOfDay()->utc(); $b = \Carbon\Carbon::parse($to, $tz)->endOfDay()->utc();
    $all = DB::table('orders')->whereBetween('created_at', [$a, $b])->count();
    $labels = \App\Support\OrderCancelNotice::labels();
    // repeat = the same phone has another order that was not cancelled, placed within 48 hours of this one
    $rows = DB::select("select coalesce(nullif(o.cancel_reason, ''), '-') as reason, count(*) as n, coalesce(sum(o.total), 0) as total,
        sum(case when exists (select 1 from orders p where p.phone = o.phone and p.id <> o.id and p.status <> 'Cancel' and p.created_at between o.created_at - interval '48 hours' and o.created_at + interval '48 hours') then 1 else 0 end) as repeats,
        sum(case when o.source = 'Web Site' then 1 else 0 end) as web
      from orders o where o.status = 'Cancel' and o.created_at between ? and ? group by 1 order by n desc", [$a, $b]);
    $g = ['n' => 0, 'total' => 0.0, 'repeats' => 0, 'web' => 0];
    foreach ($rows as $x) { $x->label = $x->reason === '-' ? 'No reason saved (automatic / older order)' : ($labels[$x->reason] ?? $x->reason); $g['n'] += (int)$x->n; $g['total'] += (float)$x->total; $g['repeats'] += (int)$x->repeats; $g['web'] += (int)$x->web; }
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Reason', 'Cancelled orders', 'Repeat of another order', 'From website', 'Order value']);
      foreach ($rows as $x) fputcsv($out, [$x->label, $x->n, $x->repeats, $x->web, $x->total]);
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="cancel-reasons-' . $from . '-' . $to . '.csv"']);
    }
    return view('cancel_report', compact('rows', 'g', 'all', 'from', 'to'));
  }
}
