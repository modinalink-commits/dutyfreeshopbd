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
    $q = W::queue(); $cap = W::dailyCap(); $view = in_array($r->query('view'), ['eligible', 'cooldown', 'sent', 'converted', 'failed'], true) ? $r->query('view') : 'all';
    $d['q'] = ['rows' => array_slice($q['rows'], 0, 300), 'total' => $q['total'], 'cap' => $cap, 'when' => $q['when'], 'start' => W::set('start', ''), 'sentToday' => W::sentToday(), 'cool' => array_slice($q['cool'], 0, 300), 'coolTotal' => count($q['cool'])];
    $d['view'] = $view;
    $conv = (int)(DB::selectOne("select count(distinct e.id) as n from winback_events e join orders o on right(o.phone,11) = e.phone and o.created_at > e.created_at and o.status not in ('Cancel','Returned') where e.cycle_order_id > 0")->n ?? 0);
    $failed = (int)DB::table('sms_logs')->where('event', 'winback')->where('status', 'failed')->where('created_at', '>=', now()->subDays(30))->count();
    $d['dash'] = ['eligible' => $q['total'], 'cooldown' => count($q['cool']), 'today' => W::sentToday(), 'conv' => $conv, 'failed' => $failed];
    $d['slot'] = ['size' => W::slotSize(), 'gap' => W::slotGapMin(), 'qf' => W::set('quiet_from', '22'), 'qt' => W::set('quiet_to', '8'), 'minGap' => W::minGapDays(), 'quiet' => W::quiet()];
    // history rows with SMS status, cooldown and conversion per message
    $hist = DB::table('winback_events as e')->leftJoin('customers as c', 'c.id', '=', 'e.customer_id')->where('e.cycle_order_id', '>', 0)->orderByDesc('e.id')->limit(400)->get(['e.*', 'c.name']);
    $ph = $hist->pluck('phone')->unique()->all(); $ords = []; $smsF = [];
    foreach (array_chunk($ph, 500) as $ch) {
      if (!$ch) continue; $in = implode(',', array_fill(0, count($ch), '?'));
      foreach (DB::select("select right(phone,11) as p, min(created_at) as m, array_agg(created_at) as all_at from orders where status not in ('Cancel','Returned') and right(phone,11) in ($in) group by right(phone,11)", $ch) as $o) $ords[$o->p] = $o;
      foreach (DB::table('sms_logs')->where('event', 'winback')->where('status', 'failed')->get(['to', 'created_at']) as $l) $smsF[substr(preg_replace('/\D/', '', (string)$l->to), -11)][] = strtotime($l->created_at . ' UTC');
    }
    $lastOrd = []; foreach (DB::select("select right(phone,11) as p, max(created_at) as m from orders where status not in ('Cancel','Returned') and phone is not null group by right(phone,11)") as $o) $lastOrd[$o->p] = strtotime($o->m . ' UTC');
    $rows2 = [];
    foreach ($hist as $e) {
      $sent = strtotime($e->created_at . ' UTC'); $nx = $e->next_sms_at ?? null; $next = $nx ? strtotime($nx . ' UTC') : $sent + W::minGapDays() * 86400;
      $failedSms = false; foreach ($smsF[$e->phone] ?? [] as $ft) if ($ft >= $sent - 120 && $ft <= $sent + 1800) $failedSms = true;
      $converted = isset($lastOrd[$e->phone]) && $lastOrd[$e->phone] > $sent;
      $status = $failedSms ? 'Failed' : ($converted ? 'Converted' : (time() < $next ? 'Cooldown' : 'Sent'));
      $e->sent_ts = $sent; $e->next_ts = $next; $e->wb_status = $status; $e->left = max(0, (int)ceil(($next - time()) / 86400)); $rows2[] = $e;
    }
    $d['hist2'] = collect($rows2)->when(in_array($view, ['sent', 'converted', 'failed'], true), fn($c) => $c->filter(fn($e) => $view === 'sent' ? $e->wb_status !== 'Failed' : strtolower($e->wb_status) === $view))->take(150)->values();
    return view('winback', $d);
  }
  /** Who used the Win-Back offers + results by stage; ?csv=1 downloads it. */
  public function report(Request $r) {
    $from = $r->query('from'); $to = $r->query('to');
    $ev = fn() => DB::table('winback_events as e')->when($from, fn($q) => $q->whereDate('e.created_at', '>=', $from))->when($to, fn($q) => $q->whereDate('e.created_at', '<=', $to));
    $stages = $ev()->selectRaw('e.stage_days, max(e.pct) as pct, count(*) as sent, sum(case when e.used_at is not null then 1 else 0 end) as used, coalesce(sum(e.used_total),0) as rev, coalesce(sum(e.used_discount),0) as disc')->groupBy('e.stage_days')->orderBy('e.stage_days')->get();
    $users = $ev()->whereNotNull('e.used_at')->leftJoin('customers as c', 'c.id', '=', 'e.customer_id')->leftJoin('orders as o', 'o.id', '=', 'e.used_order_id')->orderByDesc('e.used_at')->select('e.*', 'o.*', 'c.name as cname', 'e.id as eid', 'e.phone as ephone', 'e.created_at as sent_at', 'e.coupon_code as ecode', 'e.stage_days as estage', 'e.pct as epct', 'e.used_at as eused', 'e.used_discount as edisc', 'e.used_total as etotal')->limit(2000)->get();
    $rows = $users->map(fn($u) => ['name' => $u->cname, 'phone' => $u->ephone, 'stage' => $u->estage, 'pct' => (float)$u->epct, 'code' => $u->ecode ?: 'Auto', 'invoice' => $u->invoice_no ?? $u->invoice ?? ('#' . $u->used_order_id), 'total' => (float)$u->etotal, 'disc' => (float)$u->edisc, 'sent' => $u->sent_at, 'used' => $u->eused]);
    $sentq = fn() => $ev()->leftJoin('customers as c', 'c.id', '=', 'e.customer_id')->where('e.cycle_order_id', '>', 0)->selectRaw("e.created_at as sent_at, c.name as cname, e.phone, e.stage_days, e.pct, e.coupon_code, e.channels, e.used_at")->orderByDesc('e.id');
    $smsMap = []; foreach (DB::table('sms_logs')->where('event', 'winback')->orderBy('id')->get(['to', 'status']) as $l) $smsMap[substr(preg_replace('/\D/', '', (string)$l->to), -11)] = $l->status;
    if ($r->query('csv') === 'sent') {
      $out = fopen('php://temp', 'w+'); fwrite($out, "\xEF\xBB\xBF"); fputcsv($out, ['Sent at (Dhaka)', 'Customer', 'Phone', 'Stage (days)', 'Discount %', 'Coupon', 'Channels', 'SMS status', 'Ordered with offer']);
      foreach ($sentq()->cursor() as $x) fputcsv($out, [\Carbon\Carbon::parse($x->sent_at, 'UTC')->timezone('Asia/Dhaka')->format('Y-m-d H:i'), $x->cname, $x->phone, $x->stage_days, $x->pct, $x->coupon_code, $x->channels, $smsMap[substr($x->phone, -11)] ?? '', $x->used_at ? 'Yes' : 'No']);
      rewind($out); $csv = stream_get_contents($out); fclose($out);
      return response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="winback-sent-' . now('Asia/Dhaka')->format('Ymd') . '.csv"']);
    }
    $sentTotal = $sentq()->reorder()->count(); $sentRows = $sentq()->limit(300)->get();
    if ($r->boolean('csv')) {
      $out = fopen('php://temp', 'w+'); fwrite($out, "\xEF\xBB\xBF"); fputcsv($out, ['Customer', 'Phone', 'Stage (days)', 'Discount %', 'Coupon', 'Invoice', 'Order total', 'Discount given', 'Offer sent', 'Used at']);
      foreach ($rows as $x) fputcsv($out, [$x['name'], $x['phone'], $x['stage'], $x['pct'], $x['code'], $x['invoice'], $x['total'], $x['disc'], $x['sent'], $x['used']]);
      rewind($out); $csv = stream_get_contents($out); fclose($out);
      return response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="winback-usage-' . now('Asia/Dhaka')->format('Ymd') . '.csv"']);
    }
    return view('winback_report', ['stages' => $stages, 'rows' => $rows, 'from' => $from, 'to' => $to, 'sentTotal' => $sentTotal, 'sentRows' => $sentRows, 'smsMap' => $smsMap]);
  }
  public function save(Request $r) {
    $days = (array)$r->input('days', []); $pcts = (array)$r->input('pct', []); $st = [];
    $cds = (array)$r->input('code', []); $codes = [];
    foreach ($days as $i => $dv) { $dd = (int)$dv; $pp = (float)($pcts[$i] ?? 0); if ($dd > 0 && $pp > 0) { $st[] = [$dd, $pp]; $cc = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($cds[$i] ?? ''))); if ($cc !== '') $codes[$dd] = mb_substr($cc, 0, 30); } }
    W::put('codes', json_encode((object)$codes));
    usort($st, fn($a, $b) => $a[0] <=> $b[0]); W::put('stages', json_encode($st));
    W::put('daily_cap', (string)max(1, (int)$r->input('daily_cap', 250)));
    W::put('slot_size', (string)max(5, min(10, (int)$r->input('slot_size', 10)))); W::put('slot_gap', (string)max(1, min(240, (int)$r->input('slot_gap', 10)))); W::put('min_gap', (string)max(1, min(90, (int)$r->input('min_gap', 15))));
    W::put('quiet_from', (string)max(0, min(23, (int)$r->input('quiet_from', 22)))); W::put('quiet_to', (string)max(0, min(23, (int)$r->input('quiet_to', 8))));
    foreach (['max_pct', 'max_discount', 'min_order', 'expiry'] as $k) W::put($k, (string)max(0, (float)$r->input($k, 0)));
    W::put('mode', in_array($r->input('mode'), ['coupon', 'fixed'], true) ? $r->input('mode') : 'auto'); W::put('on', $r->boolean('on') ? '1' : '0');
    foreach (['sms', 'push', 'notif'] as $k) W::put($k, $r->boolean($k) ? '1' : '0');
    foreach (['sms_text', 'sms_final'] as $k) W::put($k, mb_substr((string)$r->input($k), 0, 500));
    \App\Support\Procure::audit('winback.settings', 'Win-back settings', null, $r->except(['_token']));
    return redirect('/admin/winback')->with('ok', 'Saved');
  }
  public function toggle(Request $r) { $on = !W::on(); W::put('on', $on ? '1' : '0'); \App\Support\Procure::audit('winback.toggle', 'Win-back automation ' . ($on ? 'ON' : 'OFF'), null, []); return redirect('/admin/winback')->with('ok', 'Win-Back automation is now ' . ($on ? 'ON' : 'OFF') . '. Existing queue and history are kept.'); }
  public function test(Request $r) { $m = W::testSend((string)$r->input('phone'), (float)$r->input('pct', 0)); return redirect('/admin/winback')->with('ok', $m); }
  public function run(Request $r) { W::put('blast_limit', (string)max(0, (int)$r->input('limit', 0))); W::put('blast', '1'); W::put('blast_at', now()->toIso8601String()); \App\Support\Procure::audit('winback.run_all', 'Win-back: send to everyone now', null, []); return redirect('/admin/winback')->with('ok', 'Sending started: offers go out in batches of 200 every minute until everyone eligible has received theirs. See the report for the sent list.'); }
}
