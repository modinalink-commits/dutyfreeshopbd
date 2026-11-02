<?php
namespace App\Http\Controllers;

use App\Support\Winback as W;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Marketing & Offers -> Win-Back Offers (Customer Reactivation). */
class WinbackController extends Controller
{
  public function index(Request $r) {
    $st = W::stages(); $buckets = []; foreach ($st as $s) $buckets[$s[0]] = 0; $over = 0; $rows = []; $flt = (int)$r->query('stage', 0);
    foreach (W::lastDelivered() as $p => $x) {
      $d = W::days($x['at']); $s = W::stageFor($d); if (!$s) continue; $buckets[$s[0]]++;
      if ($flt && $s[0] !== $flt) continue;
      if (count($rows) < 150) $rows[] = ['p' => $p, 'd' => $d, 's' => $s, 'at' => $x['at']];
    }
    $names = []; foreach (DB::table('customers')->whereRaw('right(phone,11) in (' . implode(',', array_fill(0, max(1, count($rows)), '?')) . ')', $rows ? array_column($rows, 'p') : ['0'])->selectRaw('right(phone,11) as k, name')->get() as $cu) $names[$cu->k] = $cu->name;
    $ev = DB::table('winback_events');
    $d = ['st' => $st, 'b' => $buckets, 'rows' => $rows, 'names' => $names, 'flt' => $flt,
      'k' => ['sent' => (clone $ev)->count(), 'used' => (clone $ev)->whereNotNull('used_at')->count(), 'disc' => (float)(clone $ev)->sum('used_discount'), 'rev' => (float)(clone $ev)->sum('used_total')],
      'hist' => DB::table('winback_events as e')->leftJoin('customers as c', 'c.id', '=', 'e.customer_id')->orderByDesc('e.id')->limit(60)->get(['e.*', 'c.name']),
      's' => ['on' => W::on(), 'auto' => W::set('mode', 'auto'), 'max_pct' => W::set('max_pct', '100'), 'max_discount' => W::set('max_discount', '0'), 'min_order' => W::set('min_order', '0'), 'expiry' => W::set('expiry', '30'),
        'sms' => W::set('sms', '1'), 'push' => W::set('push', '1'), 'notif' => W::set('notif', '1'), 'sms_text' => W::set('sms_text', W::SMS), 'sms_final' => W::set('sms_final', W::SMS_FINAL), 'last_run' => W::set('last_run'), 'daily_cap' => W::dailyCap()]];
    $q = W::queue(); $cap = W::dailyCap(); $d['q'] = ['rows' => array_slice($q['rows'], 0, $cap), 'total' => $q['total'], 'cap' => $cap, 'when' => $q['when'], 'start' => W::set('start', ''), 'sentToday' => W::sentToday()];
    return view('winback', $d);
  }
  /** Who used the Win-Back offers + results by stage; ?csv=1 downloads it. */
  public function report(Request $r) {
    $from = $r->query('from'); $to = $r->query('to');
    $ev = fn() => DB::table('winback_events as e')->when($from, fn($q) => $q->whereDate('e.created_at', '>=', $from))->when($to, fn($q) => $q->whereDate('e.created_at', '<=', $to));
    $stages = $ev()->selectRaw('e.stage_days, max(e.pct) as pct, count(*) as sent, sum(case when e.used_at is not null then 1 else 0 end) as used, coalesce(sum(e.used_total),0) as rev, coalesce(sum(e.used_discount),0) as disc')->groupBy('e.stage_days')->orderBy('e.stage_days')->get();
    $users = $ev()->whereNotNull('e.used_at')->leftJoin('customers as c', 'c.id', '=', 'e.customer_id')->leftJoin('orders as o', 'o.id', '=', 'e.used_order_id')->orderByDesc('e.used_at')->select('e.*', 'o.*', 'c.name as cname', 'e.id as eid', 'e.phone as ephone', 'e.created_at as sent_at', 'e.coupon_code as ecode', 'e.stage_days as estage', 'e.pct as epct', 'e.used_at as eused', 'e.used_discount as edisc', 'e.used_total as etotal')->limit(2000)->get();
    $rows = $users->map(fn($u) => ['name' => $u->cname, 'phone' => $u->ephone, 'stage' => $u->estage, 'pct' => (float)$u->epct, 'code' => $u->ecode ?: 'Auto', 'invoice' => $u->invoice_no ?? $u->invoice ?? ('#' . $u->used_order_id), 'total' => (float)$u->etotal, 'disc' => (float)$u->edisc, 'sent' => $u->sent_at, 'used' => $u->eused]);
    if ($r->boolean('csv')) {
      $out = fopen('php://temp', 'w+'); fwrite($out, "\xEF\xBB\xBF"); fputcsv($out, ['Customer', 'Phone', 'Stage (days)', 'Discount %', 'Coupon', 'Invoice', 'Order total', 'Discount given', 'Offer sent', 'Used at']);
      foreach ($rows as $x) fputcsv($out, [$x['name'], $x['phone'], $x['stage'], $x['pct'], $x['code'], $x['invoice'], $x['total'], $x['disc'], $x['sent'], $x['used']]);
      rewind($out); $csv = stream_get_contents($out); fclose($out);
      return response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="winback-usage-' . now('Asia/Dhaka')->format('Ymd') . '.csv"']);
    }
    return view('winback_report', ['stages' => $stages, 'rows' => $rows, 'from' => $from, 'to' => $to]);
  }
  public function save(Request $r) {
    $days = (array)$r->input('days', []); $pcts = (array)$r->input('pct', []); $st = [];
    $cds = (array)$r->input('code', []); $codes = [];
    foreach ($days as $i => $dv) { $dd = (int)$dv; $pp = (float)($pcts[$i] ?? 0); if ($dd > 0 && $pp > 0) { $st[] = [$dd, $pp]; $cc = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($cds[$i] ?? ''))); if ($cc !== '') $codes[$dd] = mb_substr($cc, 0, 30); } }
    W::put('codes', json_encode((object)$codes));
    usort($st, fn($a, $b) => $a[0] <=> $b[0]); W::put('stages', json_encode($st));
    W::put('daily_cap', (string)max(1, (int)$r->input('daily_cap', 250)));
    foreach (['max_pct', 'max_discount', 'min_order', 'expiry'] as $k) W::put($k, (string)max(0, (float)$r->input($k, 0)));
    W::put('mode', in_array($r->input('mode'), ['coupon', 'fixed'], true) ? $r->input('mode') : 'auto'); W::put('on', $r->boolean('on') ? '1' : '0');
    foreach (['sms', 'push', 'notif'] as $k) W::put($k, $r->boolean($k) ? '1' : '0');
    foreach (['sms_text', 'sms_final'] as $k) W::put($k, mb_substr((string)$r->input($k), 0, 500));
    \App\Support\Procure::audit('winback.settings', 'Win-back settings', null, $r->except(['_token']));
    return redirect('/admin/winback')->with('ok', 'Saved');
  }
  public function test(Request $r) { $m = W::testSend((string)$r->input('phone'), (float)$r->input('pct', 0)); return redirect('/admin/winback')->with('ok', $m); }
  public function run() { W::tick(true); return redirect('/admin/winback')->with('ok', 'Reactivation run finished'); }
}
