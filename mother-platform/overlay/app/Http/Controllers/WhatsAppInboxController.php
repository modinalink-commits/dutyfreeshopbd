<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as P;
use App\Support\Procure;
use App\Support\WhatsApp as W;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WhatsAppInboxController extends Controller {
  private function chk(string $need = 'view') { abort_unless(P::can(Auth::user(), 'customers', $need), 403); abort_unless(W::ready(), 503, 'WhatsApp inbox is not set up yet'); }
  private function ev(?int $cid, string $act, ?string $meta = null) { DB::table('wa_events')->insert(['conversation_id' => $cid, 'user_name' => Auth::user()->name, 'action' => $act, 'meta' => $meta ? mb_substr($meta, 0, 290) : null, 'created_at' => now(), 'updated_at' => now()]); }

  public function index(Request $r) {
    $this->chk(); if (!$r->query('legacy')) return redirect('/admin/chat?f=whatsapp'); // the live WhatsApp inbox is Messages; this older screen only duplicated it
    $me = Auth::user(); $f = $r->query('f', 'open'); $q = trim((string)$r->query('q', ''));
    $l = DB::table('wa_conversations')->orderByDesc('last_message_at');
    if ($f === 'open') $l->where('status', 'open'); elseif ($f === 'closed') $l->where('status', 'closed'); elseif ($f === 'mine') $l->where('status', 'open')->where('assigned_to', $me->id); elseif ($f === 'unassigned') $l->where('status', 'open')->whereNull('assigned_to'); elseif ($f === 'unread') $l->where('unread', '>', 0);
    if ($q !== '') { $d = preg_replace('/\D/', '', $q); $l->where(fn($w) => $w->where('name', 'ilike', '%' . $q . '%')->orWhere('last_text', 'ilike', '%' . $q . '%')->when($d !== '', fn($x) => $x->orWhere('wa_id', 'like', '%' . $d . '%'))); }
    $convs = $l->limit(120)->get();
    $cid = (int)$r->query('c', 0); $cur = $cid ? DB::table('wa_conversations')->find($cid) : null; $msgs = collect(); $cust = null; $orders = collect(); $users = collect();
    if ($cur) {
      $msgs = DB::table('wa_messages')->where('conversation_id', $cur->id)->orderByDesc('id')->limit(250)->get()->reverse()->values();
      if ($cur->unread > 0) { DB::table('wa_conversations')->where('id', $cur->id)->update(['unread' => 0]); $cur->unread = 0; $lw = DB::table('wa_messages')->where('conversation_id', $cur->id)->where('direction', 'in')->orderByDesc('id')->value('wamid'); if ($lw) W::markRead((string)$lw); $this->ev($cur->id, 'opened'); }
      try {
        $cust = $cur->customer_id ? DB::table('customers')->find($cur->customer_id) : DB::table('customers')->whereIn('phone', W::variants($cur->wa_id))->first();
        $orders = DB::table('orders')->where(fn($w) => $w->whereIn('phone', W::variants($cur->wa_id))->when($cust, fn($x) => $x->orWhere('customer_id', $cust->id)))->orderByDesc('id')->limit(6)->get(['id', 'invoice', 'total', 'status', 'created_at']);
      } catch (\Throwable $e) { report($e); }
      $users = DB::table('users')->orderBy('name')->get(['id', 'name']);
    }
    $stats = ['open' => DB::table('wa_conversations')->where('status', 'open')->count(), 'unread' => DB::table('wa_conversations')->where('unread', '>', 0)->count(), 'unassigned' => DB::table('wa_conversations')->where('status', 'open')->whereNull('assigned_to')->count()];
    $tpls = DB::table('wa_templates')->where('status', 'APPROVED')->orderBy('name')->get();
    return view('wa_inbox', ['convs' => $convs, 'cur' => $cur, 'msgs' => $msgs, 'cust' => $cust, 'orders' => $orders, 'users' => $users, 'f' => $f, 'q' => $q, 'stats' => $stats, 'tpls' => $tpls, 'can' => P::can($me, 'customers', 'edit'), 'on' => W::enabled(), 'win' => $cur ? W::windowOpen($cur) : false, 'me' => $me]);
  }

  public function send(Request $r, $id) {
    $this->chk('edit'); $c = DB::table('wa_conversations')->find($id); abort_unless($c, 404);
    if ($r->filled('template')) {
      [$name, $lang] = array_pad(explode('|', (string)$r->template, 2), 2, 'en'); $t = DB::table('wa_templates')->where('name', $name)->where('language', $lang)->where('status', 'APPROVED')->first(); abort_unless($t, 422, 'Template not found or not approved');
      $vals = array_slice(array_map('strval', (array)$r->input('p', [])), 0, max(0, (int)$t->params)); while (count($vals) < (int)$t->params) $vals[] = '';
      [$ok, $msg] = W::sendTemplate($c->wa_id, $name, $lang, $vals, Auth::user()->name); $this->ev($c->id, 'template', $name);
    } else { [$ok, $msg] = W::sendText($c, (string)$r->input('text', ''), Auth::user()->name); if ($ok) $this->ev($c->id, 'reply'); }
    if ($ok && !$c->assigned_to) DB::table('wa_conversations')->where('id', $c->id)->update(['assigned_to' => Auth::id(), 'assigned_name' => Auth::user()->name]);
    return redirect('/admin/whatsapp?c=' . $c->id . '&f=' . urlencode((string)$r->input('f', 'open')))->with($ok ? 'ok' : 'bad', $ok ? 'Sent.' : $msg);
  }
  public function assign(Request $r, $id) {
    $this->chk('edit'); $c = DB::table('wa_conversations')->find($id); abort_unless($c, 404); $uid = (int)$r->input('user_id', 0); $u = $uid ? DB::table('users')->find($uid) : null;
    DB::table('wa_conversations')->where('id', $c->id)->update(['assigned_to' => $u->id ?? null, 'assigned_name' => $u->name ?? null, 'updated_at' => now()]); $this->ev($c->id, 'assign', $u->name ?? 'unassigned');
    return back()->with('ok', $u ? 'Assigned to ' . $u->name : 'Unassigned');
  }
  public function status(Request $r, $id) {
    $this->chk('edit'); $to = $r->validate(['to' => 'required|in:open,closed'])['to']; abort_unless(DB::table('wa_conversations')->where('id', $id)->exists(), 404);
    DB::table('wa_conversations')->where('id', $id)->update(['status' => $to, 'updated_at' => now()]); $this->ev((int)$id, $to === 'closed' ? 'close' : 'reopen');
    return back()->with('ok', $to === 'closed' ? 'Conversation closed' : 'Reopened');
  }
  public function media($mid) {
    $this->chk(); abort_unless(preg_match('/^[A-Za-z0-9_\-]{5,120}$/', $mid) && DB::table('wa_messages')->where('media_id', $mid)->exists(), 404);
    $m = W::media($mid); abort_unless($m, 404); $mime = preg_match('#^(image|video|audio)/[a-z0-9.+\-]+$#i', (string)$m[1]) || $m[1] === 'application/pdf' ? $m[1] : 'application/octet-stream';
    return response($m[0], 200)->header('Content-Type', $mime)->header('Content-Disposition', 'inline')->header('X-Content-Type-Options', 'nosniff')->header('Content-Security-Policy', "default-src 'none'; sandbox")->header('Cache-Control', 'private, max-age=600');
  }

  // ---------- admin: settings / templates / campaigns / activity ----------
  public function manage(Request $r) {
    $this->chk(); $tab = in_array($r->query('tab'), ['settings', 'templates', 'campaigns', 'activity'], true) ? $r->query('tab') : 'settings';
    $d = ['tab' => $tab, 'can' => P::can(Auth::user(), 'customers', 'edit'), 'on' => W::enabled(), 'cfg' => ['phone_id' => W::cfg('phone_id'), 'waba_id' => W::cfg('waba_id'), 'token' => W::mask(W::cfg('token')), 'secret' => W::mask(W::cfg('app_secret')), 'cap' => Procure::set('wa_daily_cap', '250')], 'verify' => W::verifyToken(), 'hook' => rtrim(\App\Support\Domain::main(), '/') . '/webhooks/whatsapp'];
    $d['tpls'] = DB::table('wa_templates')->orderBy('name')->get();
    $d['camps'] = DB::table('wa_campaigns')->orderByDesc('id')->limit(40)->get(); $d['cs'] = [];
    foreach (DB::table('wa_campaign_recipients')->selectRaw('campaign_id, status, count(*) n')->groupBy('campaign_id', 'status')->get() as $x) $d['cs'][$x->campaign_id][$x->status] = $x->n;
    $d['events'] = DB::table('wa_events as e')->leftJoin('wa_conversations as c', 'c.id', '=', 'e.conversation_id')->orderByDesc('e.id')->limit(120)->get(['e.*', 'c.name as cname', 'c.wa_id']);
    $since = now()->subDays(30)->toDateTimeString();
    $d['perf'] = DB::table('wa_events')->where('created_at', '>=', $since)->whereIn('action', ['reply', 'template', 'assign', 'close'])->selectRaw('user_name, sum(case when action in (\'reply\',\'template\') then 1 else 0 end) replies, sum(case when action = \'close\' then 1 else 0 end) closed')->groupBy('user_name')->orderByDesc('replies')->get();
    $d['optouts'] = DB::table('wa_optouts')->count();
    return view('wa_admin', $d);
  }
  public function saveSettings(Request $r) {
    $this->chk('edit'); abort_unless(Auth::user()->role === 'super' || P::can(Auth::user(), 'settings', 'edit'), 403);
    $v = $r->validate(['phone_id' => 'nullable|string|max:40', 'waba_id' => 'nullable|string|max:40', 'token' => 'nullable|string|max:600', 'app_secret' => 'nullable|string|max:120', 'daily_cap' => 'nullable|integer|min:1|max:100000']);
    foreach (['phone_id', 'waba_id'] as $k) if (array_key_exists($k, $v) && $v[$k] !== null) Procure::put('wa_' . $k, trim($v[$k]));
    foreach (['token', 'app_secret'] as $k) if (!empty($v[$k]) && !str_contains($v[$k], '•')) Procure::put('wa_' . $k, trim($v[$k]));
    if (!empty($v['daily_cap'])) Procure::put('wa_daily_cap', (string)$v['daily_cap']);
    $this->ev(null, 'settings'); return back()->with('ok', 'Settings saved.');
  }
  public function syncTemplates() { $this->chk('edit'); [$ok, $m, $n] = W::syncTemplates(); return back()->with($ok ? 'ok' : 'bad', $ok ? $n . ' template(s) synced.' : $m); }
  public function campaignStore(Request $r) {
    $this->chk('edit');
    $v = $r->validate(['name' => 'required|string|max:160', 'template' => 'required|string', 'audience' => 'required|in:recent,lapsed,numbers', 'min_days' => 'nullable|integer|min:0|max:3650', 'max_days' => 'nullable|integer|min:1|max:3650', 'numbers' => 'nullable|string|max:60000', 'p' => 'nullable|array']);
    [$name, $lang] = array_pad(explode('|', $v['template'], 2), 2, 'en'); $t = DB::table('wa_templates')->where('name', $name)->where('language', $lang)->where('status', 'APPROVED')->first(); if (!$t) return back()->withInput()->with('bad', 'Choose an approved template.');
    $aud = W::audience($v['audience'], $v); if (!$aud) return back()->withInput()->with('bad', 'No recipients found for this audience.');
    $params = array_slice(array_map('strval', (array)($v['p'] ?? [])), 0, (int)$t->params);
    $id = DB::transaction(function () use ($v, $t, $aud, $params) {
      $id = DB::table('wa_campaigns')->insertGetId(['name' => $v['name'], 'template_name' => $t->name, 'language' => $t->language, 'audience' => $v['audience'], 'audience_data' => json_encode(array_intersect_key($v, array_flip(['min_days', 'max_days']))), 'param_map' => json_encode($params), 'status' => 'draft', 'total' => count($aud), 'created_by' => Auth::user()->name, 'created_at' => now(), 'updated_at' => now()]);
      foreach (array_chunk($aud, 500, true) as $ch) { $rows = []; foreach ($ch as $wa => $n) $rows[] = ['campaign_id' => $id, 'wa_id' => (string)$wa, 'name' => $n ? mb_substr((string)$n, 0, 110) : null, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]; DB::table('wa_campaign_recipients')->insertOrIgnore($rows); }
      return $id;
    });
    $this->ev(null, 'campaign', $v['name'] . ' · ' . count($aud) . ' recipients');
    return redirect('/admin/whatsapp/manage?tab=campaigns')->with('ok', 'Campaign saved as draft with ' . count($aud) . ' recipient(s). Review and press Start.');
  }
  public function campaignAct(Request $r, $id, $act) {
    $this->chk('edit'); $c = DB::table('wa_campaigns')->find($id); abort_unless($c, 404);
    if ($act === 'start' && in_array($c->status, ['draft', 'paused'], true)) { if (!W::enabled()) return back()->with('bad', 'Connect WhatsApp first.'); DB::table('wa_campaigns')->where('id', $id)->update(['status' => 'running', 'started_at' => $c->started_at ?: now(), 'updated_at' => now()]); $this->ev(null, 'camp-start', $c->name); return back()->with('ok', 'Started — sending in small batches (daily cap applies).'); }
    if ($act === 'pause' && $c->status === 'running') { DB::table('wa_campaigns')->where('id', $id)->update(['status' => 'paused', 'updated_at' => now()]); return back()->with('ok', 'Paused.'); }
    if ($act === 'cancel' && in_array($c->status, ['draft', 'paused', 'running'], true)) { DB::table('wa_campaigns')->where('id', $id)->update(['status' => 'cancelled', 'finished_at' => now(), 'updated_at' => now()]); DB::table('wa_campaign_recipients')->where('campaign_id', $id)->where('status', 'pending')->update(['status' => 'skipped', 'error' => 'Cancelled']); return back()->with('ok', 'Cancelled.'); }
    return back()->with('bad', 'Nothing to do.');
  }
}
