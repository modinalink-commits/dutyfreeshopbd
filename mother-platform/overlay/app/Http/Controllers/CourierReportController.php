<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Courier-wise delivery and COD report in grid layout: one group per courier, parcel rows, subtotal, grand total. */
class CourierReportController extends Controller {
  public function index(Request $r) {
    $from = $r->query('from') ?: now()->subDays(29)->toDateString(); $to = $r->query('to') ?: now()->toDateString();
    $courier = trim((string)$r->query('courier', '')); $q = trim((string)$r->query('q', ''));
    $oq = DB::table('orders')->whereNotNull('courier')->where('courier', '<>', '')->whereDate(DB::raw('coalesce(courier_sent_at, created_at)'), '>=', $from)->whereDate(DB::raw('coalesce(courier_sent_at, created_at)'), '<=', $to);
    if ($courier !== '') $oq->where('courier', $courier);
    if ($q !== '') { $like = '%' . $q . '%'; $oq->where(fn($w) => $w->where('invoice', 'ilike', $like)->orWhere('customer_name', 'ilike', $like)->orWhere('phone', 'like', $like)->orWhere('consignment_id', 'ilike', $like)); }
    $rows = $oq->orderByDesc('id')->limit(3000)->get(['id', 'invoice', 'customer_name', 'phone', 'courier', 'consignment_id', 'status', 'courier_status', 'courier_fee', 'courier_cod', 'total', 'courier_sent_at', 'created_at']);
    $groups = []; $g = ['n' => 0, 'cod' => 0, 'fee' => 0, 'del' => 0, 'delcod' => 0, 'ret' => 0, 'pend' => 0];
    foreach ($rows->groupBy('courier') as $name => $list) {
      $gr = ['name' => $name, 'rows' => [], 'n' => 0, 'cod' => 0, 'fee' => 0, 'del' => 0, 'delcod' => 0, 'ret' => 0, 'pend' => 0];
      foreach ($list as $o) {
        $cod = (float)($o->courier_cod ?? $o->total); $fee = (float)($o->courier_fee ?? 0); $st = (string)$o->status;
        $isDel = stripos($st, 'deliver') !== false && stripos($st, 'partial') === false; $isRet = stripos($st, 'return') !== false || stripos($st, 'cancel') !== false;
        $gr['rows'][] = ['inv' => \App\Support\Inv::show($o->invoice), 'd' => substr((string)($o->courier_sent_at ?: $o->created_at), 0, 10), 'c' => $o->customer_name, 'ph' => $o->phone, 'cid' => $o->consignment_id, 'st' => $st, 'cs' => $o->courier_status, 'cod' => $cod, 'fee' => $fee];
        $gr['n']++; $gr['cod'] += $cod; $gr['fee'] += $fee; if ($isDel) { $gr['del']++; $gr['delcod'] += $cod; } elseif ($isRet) $gr['ret']++; else $gr['pend']++;
      }
      $groups[] = $gr; foreach (['n', 'cod', 'fee', 'del', 'delcod', 'ret', 'pend'] as $k) $g[$k] += $gr[$k];
    }
    if ($r->query('export') === 'csv') {
      $out = fopen('php://temp', 'w+'); fputcsv($out, ['Courier', 'Invoice', 'Sent date', 'Customer', 'Phone', 'Consignment', 'Order status', 'Courier status', 'COD amount', 'Courier fee']);
      foreach ($groups as $gr) foreach ($gr['rows'] as $x) fputcsv($out, [$gr['name'], $x['inv'], $x['d'], $x['c'], $x['ph'], $x['cid'], $x['st'], $x['cs'], $x['cod'], $x['fee']]);
      rewind($out); return response("\xEF\xBB\xBF" . stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="courier-report-' . $from . '-' . $to . '.csv"']);
    }
    $couriers = DB::table('orders')->whereNotNull('courier')->where('courier', '<>', '')->distinct()->orderBy('courier')->pluck('courier');
    $data = ['groups' => $groups, 'g' => $g, 'from' => $from, 'to' => $to, 'courier' => $courier, 'q' => $q, 'couriers' => $couriers, 'capped' => $rows->count() >= 3000];
    if ($r->query('export') === 'doc') { $html = view('courier_report', $data)->render(); return response('<html><head><meta charset="utf-8"></head><body>' . (preg_match('#<table class="pl".*?</table>#s', $html, $m) ? $m[0] : '') . '</body></html>', 200, ['Content-Type' => 'application/msword; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="courier-report.doc"']); }
    return view('courier_report', $data);
  }
}
