<?php
namespace App\Http\Controllers;
use App\Support\ChatOwner;
use App\Support\Inv;
use App\Support\OrderWatch as W;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Order Watch: each staff member's own follow-up list, the supervisor's overview, settings, handover of responsibility and the report history. */
class OrderWatchController extends Controller {
  /** Supervisor page. */
  public function index(Request $r) {
    abort_unless(W::supervisor(auth()->user()), 403);
    if (!W::ready()) return view('order_watch', ['off' => true]);
    $c = W::cfg(); $all = W::issues(); $staff = W::staff(); $users = DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name', 'role']);
    $per = []; foreach ($staff as $s) { $d = W::forUser($s->user_id, $all); $per[$s->user_id] = ['d' => $d, 'msg' => W::compose($s, $d)]; }
    $un = W::forUser(null, $all); $names = $users->pluck('name', 'id');
    // people who are responsible for something but are not on the report list
    $others = []; foreach ($all as $i) if ($i->owner !== null && !isset($staff[$i->owner])) $others[$i->owner] = ($others[$i->owner] ?? 0) + 1;
    $tot = []; foreach ($all as $i) $tot[$i->cat] = ($tot[$i->cat] ?? 0) + 1;
    $reports = DB::table('order_watch_reports as x')->leftJoin('users as u', 'u.id', '=', 'x.user_id')->orderByDesc('x.id')->limit(80)->get(['x.*', 'u.name']);
    $inc = 0; try { $inc = (int)DB::table('incomplete_orders')->where('status', 'open')->where('created_at', '<=', now()->subMinutes((int)round($c['inc'] * 60)))->where('created_at', '>=', now()->subDays(7))->count(); } catch (\Throwable $e) {}
    return view('order_watch', ['off' => false, 'c' => $c, 's' => fn($k, $d = '') => W::set($k, $d), 'staff' => $staff, 'users' => $users, 'names' => $names, 'per' => $per, 'un' => $un, 'others' => $others, 'tot' => $tot, 'inc' => $inc,
      'reports' => $reports, 'rcpt' => W::receipts($reports->pluck('wamid')->all()), 'tpl' => W::tplStatus(),
      'olog' => DB::table('order_owner_log as l')->join('orders as o', 'o.id', '=', 'l.order_id')->orderByDesc('l.id')->limit(40)->get(['l.*', 'o.invoice'])]);
  }
  public function save(Request $r) {
    abort_unless(W::supervisor(auth()->user()), 403);
    $rules = ['from' => ['required', 'regex:/^\d{1,2}:\d{2}$/'], 'to' => ['required', 'regex:/^\d{1,2}:\d{2}$/'], 'channel' => 'required|in:whatsapp,sms', 'retries' => 'required|integer|min:0|max:3', 'lim_incomplete' => 'required|numeric|min:0|max:720', 'ai_owner' => 'nullable|integer'];
    foreach (array_keys(W::CATS) as $k) $rules['lim_' . $k] = 'required|numeric|min:0|max:720';
    $d = $r->validate($rules);
    if (!empty($d['ai_owner']) && !DB::table('users')->where('id', (int)$d['ai_owner'])->where('active', true)->exists()) return back()->with('err', 'AI-অনুমোদিত অর্ডারের জন্য বাছাই করা স্টাফ সক্রিয় নন। সেভ হয়নি।');
    $d['ai_owner'] = (string)(int)($d['ai_owner'] ?? 0);
    foreach ($d as $k => $v) W::put($k, trim((string)$v));
    W::put('on', $r->boolean('on') ? '1' : '0'); W::put('sms_fallback', $r->boolean('sms_fallback') ? '1' : '0');
    try { \App\Support\Procure::audit('order_watch.settings', 'Order Watch', null, ['on' => $r->boolean('on'), 'from' => $d['from'], 'to' => $d['to'], 'channel' => $d['channel']]); } catch (\Throwable $e) {}
    return back()->with('ok', 'সেভ হয়েছে।');
  }
  /** Who gets a report and on which number. */
  public function staff(Request $r) {
    abort_unless(W::supervisor(auth()->user()), 403);
    $d = $r->validate(['user_id' => 'required|integer|exists:users,id', 'phone' => 'nullable|string|max:20', 'do' => 'nullable|in:save,remove']);
    if (($d['do'] ?? 'save') === 'remove') { DB::table('order_watch_staff')->where('user_id', $d['user_id'])->delete(); return back()->with('ok', 'তালিকা থেকে সরানো হয়েছে।'); }
    $wa = WhatsApp::norm((string)($d['phone'] ?? '')); if (!W::validWa($wa)) return back()->with('err', 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)। সেভ হয়নি।');
    $clash = DB::table('order_watch_staff')->where('phone', $wa)->where('user_id', '<>', $d['user_id'])->exists(); if ($clash) return back()->with('err', 'এই নম্বরটি আরেকজন স্টাফের নামে আছে। সেভ হয়নি।');
    DB::table('order_watch_staff')->updateOrInsert(['user_id' => $d['user_id']], ['phone' => $wa, 'active' => $r->boolean('active', true), 'updated_at' => now(), 'created_at' => now()]);
    try { \App\Support\Procure::audit('order_watch.staff', ChatOwner::name((int)$d['user_id']), null, ['phone' => '…' . substr($wa, -4), 'active' => $r->boolean('active', true)]); } catch (\Throwable $e) {}
    return back()->with('ok', 'সেভ হয়েছে: ' . ChatOwner::name((int)$d['user_id']));
  }
  public function act(Request $r, string $a) {
    abort_unless(W::supervisor(auth()->user()), 403);
    if ($a === 'submit') { [$ok, $m] = W::submitTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template: ' . $m); }
    if ($a === 'sync') { [$ok, $m] = W::syncTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template status: ' . $m); }
    if ($a === 'test') { // one person's report, sent to a test number instead of the person
      $d = $r->validate(['user_id' => 'required|integer', 'phone' => 'required|string|max:20']); $wa = WhatsApp::norm($d['phone']); if (!W::validWa($wa)) return back()->with('err', 'সঠিক টেস্ট নম্বর দিন।');
      $s = W::staff()[(int)$d['user_id']] ?? null; if (!$s) return back()->with('err', 'এই স্টাফ তালিকায় নেই।');
      $msg = W::compose($s, W::forUser($s->user_id)); if (!$msg) return back()->with('ok', $s->name . ': এই মুহূর্তে রিপোর্ট করার মতো কিছু নেই, তাই কিছু পাঠানো হয়নি।');
      [$ok, $ch, $id, $err] = W::deliver($wa, $s->name, $msg); return back()->with($ok ? 'ok' : 'err', 'Test of ' . $s->name . "'s report to …" . substr($wa, -4) . ': ' . ($ok ? 'sent by ' . $ch : 'not sent: ' . $err));
    }
    abort(404);
  }
  /** Hand an order to a person, or back to the unassigned list. */
  public function assign(Request $r) {
    abort_unless(W::supervisor(auth()->user()), 403);
    $d = $r->validate(['order' => 'required|string|max:40', 'user_id' => 'nullable|integer', 'reason' => 'required|string|min:3|max:300']);
    $o = ctype_digit($d['order']) ? DB::table('orders')->where('id', (int)$d['order'])->first(['id']) : null;
    if (!$o) { $q = DB::table('orders'); \App\Support\OrderSearch::apply($q, trim($d['order'])); $found = $q->limit(2)->get(['id']); if ($found->count() !== 1) return back()->with('err', 'অর্ডারটি খুঁজে পাওয়া যায়নি (অথবা একাধিক মিলেছে)। ইনভয়েস নম্বর পুরোটা দিন।'); $o = $found[0]; }
    [$ok, $m] = W::assign((int)$o->id, !empty($d['user_id']) ? (int)$d['user_id'] : null, $d['reason'], auth()->user());
    return back()->with($ok ? 'ok' : 'err', $m);
  }
  /** A staff member's own list. A supervisor may open anyone's (?user=) or the unassigned list (?user=0). */
  public function mine(Request $r) {
    if (!W::ready()) return view('my_orders', ['off' => true]);
    $me = auth()->user(); $sup = W::supervisor($me); $uid = (int)$me->id; $unassigned = false;
    if ($sup && $r->filled('user')) { if ((int)$r->query('user') === 0) $unassigned = true; else $uid = (int)$r->query('user'); }
    $d = W::forUser($unassigned ? null : $uid);
    $ids = array_map(fn($i) => $i->id, $d['items']); $hist = [];
    if ($ids) foreach (DB::table('order_watch_notes')->whereIn('order_id', $ids)->orderByDesc('id')->limit(600)->get() as $n) { if (count($hist[$n->order_id] ?? []) < 3) $hist[$n->order_id][] = $n; }
    return view('my_orders', ['off' => false, 'd' => $d, 'hist' => $hist, 'sup' => $sup, 'who' => $unassigned ? 'Nobody (unassigned)' : ChatOwner::name($uid), 'uid' => $unassigned ? 0 : $uid, 'own' => !$unassigned && $uid === (int)$me->id,
      'users' => $sup ? DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name']) : collect()]);
  }
  /** Progress on one order: what was found, what was done, when to look again. Never closes the item. */
  public function note(Request $r, int $id) {
    abort_unless(W::ready(), 404); $me = auth()->user();
    $o = DB::table('orders')->where('id', $id)->first(['id', 'invoice', 'owner_id', 'approval_source', 'approved_by']); abort_unless($o, 404);
    if (!W::supervisor($me) && W::ownerOf($o) !== (int)$me->id) return back()->with('err', 'এই অর্ডারটি আপনার দায়িত্বে নেই, তাই আপডেট সেভ হয়নি।');
    $d = $r->validate(['note' => 'required|string|min:3|max:1000', 'action' => 'nullable|string|max:300', 'date' => 'nullable|date_format:Y-m-d', 'time' => ['nullable', 'regex:/^\d{2}:\d{2}$/'], 'issue' => 'nullable|string|max:20']);
    $next = null;
    if (!empty($d['date'])) { $next = \Carbon\Carbon::parse($d['date'] . ' ' . (($d['time'] ?? '') ?: '10:00'), 'Asia/Dhaka')->utc(); if ($next->getTimestamp() <= time()) return back()->with('err', 'পরবর্তী ফলোআপের সময় ভবিষ্যতের হতে হবে। সেভ হয়নি।'); if ($next->getTimestamp() > time() + 30 * 86400) return back()->with('err', 'ফলোআপের সময় ৩০ দিনের বেশি দূরে দেওয়া যাবে না। সেভ হয়নি।'); }
    DB::table('order_watch_notes')->insert(['order_id' => $id, 'user_id' => $me->id, 'user_name' => $me->name, 'issue' => $d['issue'] ?? null, 'note' => trim($d['note']), 'action' => trim((string)($d['action'] ?? '')) ?: null, 'next_followup_at' => $next, 'created_at' => now(), 'updated_at' => now()]);
    try { \App\Support\OrderActivity::log($id, 'followup_note', null, null, 'Follow-up: ' . trim($d['note']) . (!empty($d['action']) ? ' · Action: ' . trim($d['action']) : '') . ($next ? ' · Next: ' . $next->copy()->timezone('Asia/Dhaka')->format('d M h:i A') : '')); } catch (\Throwable $e) {}
    return back()->with('ok', 'অর্ডার ' . Inv::show($o->invoice) . ': আপডেট সেভ হয়েছে।' . ($next ? ' ' . $next->copy()->timezone('Asia/Dhaka')->format('d M h:i A') . ' পর্যন্ত এটি রিপোর্টে আসবে না।' : ' সমস্যা মিটে না যাওয়া পর্যন্ত এটি রিপোর্টে থাকবে।'));
  }
}
