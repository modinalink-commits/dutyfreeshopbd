<?php
namespace App\Http\Controllers;
use App\Support\Sms;
use App\Support\SmsMarketing as M;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SmsMarketingController extends Controller {
  private function tz($d) { return $d ? \Carbon\Carbon::parse($d, 'UTC')->timezone('Asia/Dhaka') : null; }
  private function audit(string $a, string $s): void { try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(),'user_name'=>auth()->user()->name,'action'=>$a,'subject'=>$s,'ip'=>request()->ip(),'created_at'=>now(),'updated_at'=>now()]); } catch (\Throwable $e) {} }

  public function dashboard() {
    $st = DB::table('sms_logs')->select('status', DB::raw('count(*) n'))->groupBy('status')->pluck('n', 'status');
    $tot = (int)$st->sum(); $sent = (int)($st['sent'] ?? 0); $fail = (int)($st['failed'] ?? 0);
    $today = now('Asia/Dhaka')->startOfDay()->utc(); $month = now('Asia/Dhaka')->startOfMonth()->utc();
    $bal = M::balance(); $low = (float)M::set('sms_low_balance', '100');
    return view('smsmk.dashboard', ['k'=>[
      'balance'=>$bal, 'low'=>$bal !== null && $bal <= $low, 'total'=>$tot, 'sent'=>$sent, 'failed'=>$fail, 'queued'=>(int)(($st['queued'] ?? 0) + ($st['sending'] ?? 0)),
      'scheduled'=>DB::table('sms_campaigns')->where('status', 'scheduled')->count(), 'active'=>DB::table('sms_campaigns')->whereIn('status', ['scheduled', 'sending'])->count(),
      'today'=>DB::table('sms_logs')->where('created_at', '>=', $today)->count(), 'month'=>DB::table('sms_logs')->where('created_at', '>=', $month)->count(),
      'rate'=>($sent + $fail) ? round($sent * 100 / ($sent + $fail), 1) : 0, 'spent'=>(float)DB::table('sms_logs')->whereIn('status', ['sent'])->sum('cost'), 'unit'=>M::unit(),
      'recharge'=>M::set('sms_last_recharge', '—'), 'ready'=>Sms::configured()],
      'camps'=>DB::table('sms_campaigns')->orderByDesc('id')->limit(6)->get()]);
  }

  private function parse(Request $r): array {
    $segs = array_values(array_intersect((array)$r->input('segments', []), array_keys(M::SEGMENTS)));
    $f = array_filter(['min_orders'=>$r->input('min_orders'), 'max_orders'=>$r->input('max_orders'), 'min_spend'=>$r->input('min_spend'), 'no_order_days'=>$r->input('no_order_days'), 'keyword'=>trim((string)$r->input('keyword'))], fn($v) => $v !== null && $v !== '');
    $ids = array_values(array_filter(array_map('intval', (array)$r->input('ids', []))));
    return [$segs, $f, $ids];
  }
  private function vars(Request $r): array { return array_filter(['coupon_code'=>trim((string)$r->input('coupon_code')), 'discount'=>trim((string)$r->input('discount')), 'expiry_date'=>trim((string)$r->input('expiry_date'))], fn($v) => $v !== ''); }

  public function compose(Request $r) {
    $ids = array_values(array_filter(array_map('intval', explode(',', (string)$r->query('ids')))));
    return view('smsmk.compose', ['segments'=>M::SEGMENTS, 'templates'=>DB::table('sms_templates')->where('active', true)->orderBy('category')->orderBy('name')->get(), 'sender'=>Sms::secret('sms_sender_id'),
      'ids'=>$ids, 'idRows'=>$ids ? DB::table('customers')->whereIn('id', $ids)->limit(50)->get(['id', 'name', 'phone']) : collect(), 'offer'=>$r->query('mode') === 'offer', 'coupons'=>DB::table('coupons')->where('active', true)->orderByDesc('id')->limit(50)->get(),
      'camp'=>$r->query('edit') ? DB::table('sms_campaigns')->find((int)$r->query('edit')) : null, 'ready'=>Sms::configured(), 'unit'=>M::unit()]);
  }
  public function estimate(Request $r) {
    [$segs, $f, $ids] = $this->parse($r);
    $rec = M::audience($segs, $f, $ids, true); $body = M::render((string)$r->input('body', ''), ['customer_name'=>'Customer Name', 'total'=>'0'] + $this->vars($r));
    $e = M::estimate($rec, $body);
    $s = $rec[0] ?? null;
    return response()->json($e + ['chars'=>M::parts($body)[1], 'unicode'=>M::parts($body)[2], 'preview'=>$s ? M::render((string)$r->input('body', ''), M::vars($s, $this->vars($r))) : $body,
      'sample'=>array_map(fn($x) => ['name'=>$x->name, 'phone'=>$x->num], array_slice($rec, 0, 5)), 'enough'=>$e['balance'] === null || $e['balance'] >= $e['cost']]);
  }
  public function send(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:160', 'body'=>'required|string|max:1000', 'subject'=>'nullable|string|max:160', 'when'=>'required|in:now,later,draft', 'schedule_date'=>'nullable|date', 'schedule_time'=>'nullable|string|max:5', 'repeat'=>'nullable|in:none,daily,weekly,monthly', 'kind'=>'nullable|in:bulk,offer,selected']);
    [$segs, $f, $ids] = $this->parse($r);
    if (!$segs && !$f && !$ids) return back()->withInput()->withErrors(['name'=>'Choose an audience: a segment, a filter, or selected customers.']);
    $rec = M::audience($segs, $f, $ids, true); if (!$rec) return back()->withInput()->withErrors(['name'=>'No recipients match this audience (opted-out and blocked customers are skipped).']);
    $at = null; $status = 'draft';
    if ($d['when'] === 'later') {
      if (empty($d['schedule_date'])) return back()->withInput()->withErrors(['name'=>'Pick a send date.']);
      $at = \Carbon\Carbon::parse($d['schedule_date'].' '.($d['schedule_time'] ?: '09:00'), 'Asia/Dhaka')->utc();
      if ($at->lt(now())) return back()->withInput()->withErrors(['name'=>'The schedule is in the past.']);
      $status = 'scheduled';
    } elseif ($d['when'] === 'now') { $status = 'scheduled'; $at = now(); }
    $row = ['name'=>$d['name'], 'subject'=>$d['subject'] ?? null, 'kind'=>$d['kind'] ?? 'bulk', 'segments'=>json_encode($segs), 'filters'=>json_encode($f), 'customer_ids'=>json_encode($ids), 'vars'=>json_encode($this->vars($r)),
      'template_id'=>$r->input('template_id') ?: null, 'body'=>$d['body'], 'sender_id'=>Sms::secret('sms_sender_id'), 'status'=>$status, 'schedule_at'=>$at, 'repeat'=>$d['repeat'] ?? 'none', 'allow_dup'=>$r->boolean('allow_dup'), 'updated_at'=>now()];
    if ($r->filled('campaign_id') && ($c = DB::table('sms_campaigns')->find((int)$r->input('campaign_id'))) && in_array($c->status, ['draft', 'scheduled', 'paused'], true)) { DB::table('sms_campaigns')->where('id', $c->id)->update($row); $id = $c->id; }
    else $id = DB::table('sms_campaigns')->insertGetId($row + ['created_by'=>auth()->user()->name, 'created_at'=>now()]);
    $this->audit('sms.campaign', ucfirst($d['when']).': '.$d['name'].' ('.count($rec).' recipients)');
    if ($d['when'] === 'now' && count($rec) <= 300) { DB::table('sms_campaigns')->where('id', $id)->update(['status'=>'sending']); M::launch($id); if (Sms::configured()) M::pump(20); }
    return redirect('/admin/sms-marketing/campaigns/'.$id)->with('ok', $d['when'] === 'now' ? count($rec).' SMS queued for sending.' : ($d['when'] === 'later' ? 'Campaign scheduled for '.$this->tz($at)->format('d M Y · h:i A').' (Asia/Dhaka).' : 'Draft saved.'));
  }

  public function customers(Request $r) {
    $q = trim((string)$r->query('q')); $seg = (string)$r->query('seg'); $min = $r->query('min_orders'); $ms = $r->query('min_spend'); $cut = $r->query('inactive_days');
    $rows = collect(M::audience($seg ? [$seg] : ['all'], [], [], false))->filter(function ($c) use ($q, $min, $ms, $cut) {
      if ($q !== '' && !str_contains(mb_strtolower($c->name.' '.$c->phone), mb_strtolower($q))) return false;
      if ($min !== null && $min !== '' && $c->orders < (int)$min) return false;
      if ($ms !== null && $ms !== '' && $c->spend < (float)$ms) return false;
      if ($cut !== null && $cut !== '' && $c->last && $c->last->gte(now()->subDays((int)$cut))) return false;
      return true; })->sortByDesc('spend')->values();
    $opt = array_flip(M::opted());
    return view('smsmk.customers', ['rows'=>$rows->take(500), 'total'=>$rows->count(), 'segments'=>M::SEGMENTS, 'opt'=>$opt, 'ids'=>$rows->pluck('id')->take(5000)->all()]);
  }

  public function templates() { return view('smsmk.templates', ['rows'=>DB::table('sms_templates')->orderBy('category')->orderBy('name')->get(), 'cats'=>M::CATS, 'vars'=>M::VARS, 'edit'=>request('edit') ? DB::table('sms_templates')->find((int)request('edit')) : null]); }
  public function templateSave(Request $r) {
    $d = $r->validate(['name'=>'required|string|max:120', 'category'=>'required|in:'.implode(',', M::CATS), 'body'=>'required|string|max:1000']);
    $row = $d + ['active'=>$r->boolean('active'), 'updated_at'=>now()];
    if ($r->filled('id')) DB::table('sms_templates')->where('id', (int)$r->input('id'))->update($row); else DB::table('sms_templates')->insert($row + ['created_at'=>now()]);
    return redirect('/admin/sms-marketing/templates')->with('ok', 'Template saved.');
  }
  public function templateDelete(Request $r) { DB::table('sms_templates')->where('id', (int)$r->input('id'))->delete(); return back()->with('ok', 'Template deleted.'); }

  public function campaigns(Request $r) {
    $q = DB::table('sms_campaigns'); if ($s = $r->query('status')) $q->where('status', $s); if ($r->query('scheduled')) $q->whereIn('status', ['scheduled', 'paused']);
    $rows = $q->orderByDesc('id')->limit(200)->get(); $st = DB::table('sms_logs')->whereIn('campaign_id', $rows->pluck('id'))->select('campaign_id', 'status', DB::raw('count(*) n'))->groupBy('campaign_id', 'status')->get()->groupBy('campaign_id');
    return view('smsmk.campaigns', ['rows'=>$rows, 'st'=>$st, 'only'=>$r->query('scheduled') ? 'scheduled' : '', 'tz'=>fn($d) => $this->tz($d)]);
  }
  public function campaign(int $id) {
    $c = DB::table('sms_campaigns')->find($id); abort_unless($c, 404);
    $st = DB::table('sms_logs')->where('campaign_id', $id)->select('status', DB::raw('count(*) n'), DB::raw('coalesce(sum(cost),0) cost'))->groupBy('status')->get()->keyBy('status');
    return view('smsmk.campaign', ['c'=>$c, 'st'=>$st, 'logs'=>DB::table('sms_logs')->where('campaign_id', $id)->orderByDesc('id')->limit(100)->get(), 'at'=>$this->tz($c->schedule_at), 'segs'=>json_decode((string)$c->segments, true) ?: [], 'nids'=>count(json_decode((string)$c->customer_ids, true) ?: [])]);
  }
  public function campaignAction(Request $r, int $id) {
    $c = DB::table('sms_campaigns')->find($id); abort_unless($c, 404); $a = $r->input('do');
    $to = match ($a) { 'pause' => in_array($c->status, ['scheduled', 'sending'], true) ? 'paused' : null, 'resume' => $c->status === 'paused' ? ($c->started_at && DB::table('sms_logs')->where('campaign_id', $id)->where('status', 'queued')->exists() ? 'sending' : 'scheduled') : null, 'cancel' => in_array($c->status, ['scheduled', 'sending', 'paused', 'draft'], true) ? 'cancelled' : null, default => null };
    if (!$to) return back()->with('bad', 'That action is not available for this campaign.');
    if ($to === 'scheduled' && (!$c->schedule_at || \Carbon\Carbon::parse($c->schedule_at, 'UTC')->lt(now()))) DB::table('sms_campaigns')->where('id', $id)->update(['schedule_at'=>now()]);
    if ($to === 'cancelled') DB::table('sms_logs')->where('campaign_id', $id)->where('status', 'queued')->update(['status'=>'cancelled', 'updated_at'=>now()]);
    DB::table('sms_campaigns')->where('id', $id)->update(['status'=>$to, 'updated_at'=>now()]); $this->audit('sms.campaign', ucfirst($a).': '.$c->name);
    return back()->with('ok', 'Campaign '.$to.'.');
  }

  public function automation() { return view('smsmk.automation', ['rows'=>DB::table('sms_automations')->orderBy('id')->get(), 'vars'=>M::VARS]); }
  public function automationSave(Request $r) {
    foreach (DB::table('sms_automations')->get() as $a) {
      $k = 'a'.$a->id; $b = trim((string)$r->input($k.'_body', $a->body));
      DB::table('sms_automations')->where('id', $a->id)->update(['body'=>$b !== '' ? $b : $a->body, 'active'=>$r->boolean($k.'_on'), 'param'=>$r->filled($k.'_param') ? (int)$r->input($k.'_param') : $a->param, 'updated_at'=>now()]);
    }
    $this->audit('sms.automation', 'SMS automations updated');
    return back()->with('ok', 'Automations saved. They apply to new events from now on.');
  }

  private function reportQuery(Request $r) {
    \App\Support\DateDefault::month($r);
    $q = DB::table('sms_logs as l')->leftJoin('sms_campaigns as c', 'c.id', '=', 'l.campaign_id')->select('l.*', 'c.name as campaign');
    if ($v = $r->query('from')) $q->where('l.created_at', '>=', \Carbon\Carbon::parse($v, 'Asia/Dhaka')->startOfDay()->utc());
    if ($v = $r->query('to')) $q->where('l.created_at', '<=', \Carbon\Carbon::parse($v, 'Asia/Dhaka')->endOfDay()->utc());
    if ($v = $r->query('campaign')) $q->where('l.campaign_id', (int)$v);
    if ($v = $r->query('status')) $q->where('l.status', $v);
    if ($v = $r->query('type')) $q->where('l.type', $v);
    if ($v = $r->query('sender')) $q->where('l.sender_id', $v);
    if ($v = trim((string)$r->query('q'))) $q->where(fn($w) => $w->where('l.to', 'like', "%$v%")->orWhere('l.customer_name', 'ilike', "%$v%"));
    return $q->orderByDesc('l.id');
  }
  public function reports(Request $r) {
    return view('smsmk.reports', ['rows'=>$this->reportQuery($r)->paginate(50)->withQueryString(), 'camps'=>DB::table('sms_campaigns')->orderByDesc('id')->limit(100)->get(['id', 'name']), 'senders'=>DB::table('sms_logs')->whereNotNull('sender_id')->distinct()->pluck('sender_id')]);
  }
  public function export(Request $r) {
    $rows = $this->reportQuery($r)->limit(20000)->get();
    return response()->streamDownload(function () use ($rows) {
      $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, ['Date (Asia/Dhaka)', 'Campaign', 'Customer', 'Mobile', 'Type', 'Event', 'Sender ID', 'Status', 'Parts', 'Cost', 'Provider response', 'Message']);
      foreach ($rows as $x) fputcsv($o, [$this->tz($x->created_at)?->format('Y-m-d H:i'), $x->campaign, $x->customer_name, $x->to, $x->type, $x->event, $x->sender_id, $x->status, $x->parts, $x->cost, $x->provider_response, $x->body]); fclose($o);
    }, 'sms-report-'.now('Asia/Dhaka')->format('Ymd-Hi').'.csv', ['Content-Type'=>'text/csv; charset=UTF-8']);
  }

  public function settings() {
    return view('smsmk.settings', ['unit'=>M::unit(), 'vip'=>M::set('sms_vip_spend', '50000'), 'high'=>M::set('sms_high_spend', '20000'), 'low'=>M::set('sms_low_balance', '100'), 'recharge'=>M::set('sms_last_recharge', ''), 'opt'=>DB::table('sms_optouts')->orderByDesc('id')->limit(200)->get(), 'ready'=>Sms::configured(), 'sender'=>Sms::secret('sms_sender_id'), 'bal'=>M::balance(true)]);
  }
  public function settingsSave(Request $r) {
    $d = $r->validate(['unit'=>'required|numeric|min:0|max:50', 'vip'=>'required|numeric|min:0', 'high'=>'required|numeric|min:0', 'low'=>'required|numeric|min:0', 'recharge'=>'nullable|string|max:80']);
    foreach (['sms_unit_cost'=>$d['unit'], 'sms_vip_spend'=>$d['vip'], 'sms_high_spend'=>$d['high'], 'sms_low_balance'=>$d['low'], 'sms_last_recharge'=>$d['recharge'] ?? ''] as $k => $v)
      DB::table('settings')->updateOrInsert(['key'=>$k], ['value'=>(string)$v, 'updated_at'=>now(), 'created_at'=>now()]);
    return back()->with('ok', 'Settings saved.');
  }
  public function optout(Request $r) {
    if ($r->input('do') === 'remove') DB::table('sms_optouts')->where('id', (int)$r->input('id'))->delete();
    else foreach (preg_split('/[\s,;]+/', (string)$r->input('phones'), -1, PREG_SPLIT_NO_EMPTY) as $p) if ($n = Sms::number($p)) DB::table('sms_optouts')->updateOrInsert(['phone'=>$n], ['reason'=>mb_substr((string)$r->input('reason'), 0, 160), 'updated_at'=>now(), 'created_at'=>now()]);
    return back()->with('ok', 'Opt-out list updated.');
  }

  /** Quick single SMS from a customer/order row. */
  public function quick(Request $r) {
    $d = $r->validate(['phone'=>'required|string|max:30', 'body'=>'required|string|max:1000']);
    $n = Sms::number($d['phone']); if (!$n) return response()->json(['ok'=>false, 'msg'=>'Invalid mobile number'], 422);
    $c = DB::table('customers')->where('phone', 'like', '%'.substr($n, -10))->first();
    $id = M::enqueue($n, M::render($d['body'], ['customer_name'=>$r->input('name', $c->name ?? 'Customer')]), 'manual', 'transactional', ['customer_id'=>$c->id ?? null, 'customer_name'=>mb_substr((string)($r->input('name') ?: ($c->name ?? '')), 0, 120)]);
    if (Sms::configured()) M::pump(5);
    return response()->json(['ok'=>true, 'status'=>DB::table('sms_logs')->where('id', $id)->value('status')]);
  }
  public function history(Request $r) {
    $n = Sms::number((string)$r->query('phone')); if (!$n) return response()->json([]);
    return response()->json(DB::table('sms_logs')->where('to', $n)->orderByDesc('id')->limit(30)->get(['created_at', 'body', 'event', 'status'])->map(fn($x) => ['at'=>$this->tz($x->created_at)->format('d M Y h:i A'), 'body'=>$x->body, 'event'=>$x->event, 'status'=>$x->status]));
  }
}
