<?php
namespace App\Http\Controllers;
use App\Support\ProfitFloor;
use App\Support\Restock as R;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Restock Follow-up: stock watch for general products, the purchase manager's WhatsApp follow-up, settings and the full history. */
class RestockController extends Controller {
  public function index(Request $r) {
    if (!R::ready()) return view('restock', ['off' => true]);
    $c = R::cfg(); $blocked = R::blocked($c); $fig = R::figures();
    $open = R::openTasks(); $openBy = $open->keyBy('product_id');
    $closed = DB::table('restock_tasks as t')->join('products as p', 'p.id', '=', 't.product_id')->whereNotNull('t.closed_at')->orderByDesc('t.closed_at')->limit(30)->get(['t.*', 'p.name']);
    $ev = DB::table('restock_events as e')->leftJoin('restock_tasks as t', 't.id', '=', 'e.task_id')->leftJoin('products as p', 'p.id', '=', 't.product_id')->when($r->boolean('conv'), fn($q) => $q->whereIn('e.kind', ['msg_in', 'msg_out', 'msg_cc', 'msg_fail', 'escalate', 'pause', 'notice', 'price_talk']))->orderByDesc('e.id')->limit($r->boolean('conv') ? 400 : 120)->get(['e.*', 'p.name']);
    $cats = DB::table('categories')->whereNull('parent_id')->orderBy('name')->get(['id', 'name']);
    $ov = DB::table('products')->where(fn($q) => $q->whereNotNull('restock_lead_days')->orWhere('restock_off', true))->orderBy('name')->limit(200)->get(['id', 'name', 'low_stock_alert', 'reorder_target', 'restock_lead_days', 'restock_off']);
    return view('restock', ['off' => false, 'c' => $c, 's' => fn($k, $d = '') => R::set($k, $d), 'blocked' => $blocked, 'fig' => $fig, 'open' => $open, 'openBy' => $openBy, 'closed' => $closed, 'ev' => $ev, 'cats' => $cats, 'ov' => $ov,
      'tpl' => R::tplStatus(), 'admin' => ProfitFloor::admin(), 'verified' => R::isVerified($c), 'window' => R::validWa($c['wa']) && R::windowOpen($c['wa']), 'pause' => (int)R::set('pause_until', '0'), 'conv' => $r->boolean('conv'), 'waiting' => R::notices()->count()]);
  }
  public function save(Request $r) {
    abort_unless(ProfitFloor::admin(), 403);
    $d = $r->validate(['name' => 'required|string|max:60', 'phone' => 'required|string|max:20', 'from' => ['required', 'regex:/^\d{1,2}:\d{2}$/'], 'to' => ['required', 'regex:/^\d{1,2}:\d{2}$/'], 'every' => 'required|integer|min:30|max:720',
      'lead' => 'required|integer|min:0|max:60', 'safety' => 'required|integer|min:0|max:30', 'cover' => 'required|integer|min:3|max:90', 'min_sold' => 'required|integer|min:1|max:1000', 'cards' => 'required|integer|min:0|max:10', 'scope' => 'required|in:smoke,general,all', 'cc' => 'nullable|string|max:400', 'exclude' => 'nullable|array', 'exclude.*' => 'integer']);
    $wa = WhatsApp::norm($d['phone']); if (!R::validWa($wa)) return back()->with('err', 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)।');
    $old = R::cfg();
    foreach (['name', 'from', 'to', 'every', 'lead', 'safety', 'cover', 'min_sold', 'cards', 'scope'] as $k) R::put($k, trim((string)$d[$k]));
    if ($d['scope'] !== $old['scope']) { // other products now: the open tasks of the old scope are closed, never carried over
      foreach (DB::table('restock_tasks')->whereNull('closed_at')->pluck('id') as $tid) { DB::table('restock_tasks')->where('id', $tid)->update(['status' => 'cancelled', 'closed_at' => now(), 'closed_why' => 'scope changed', 'updated_at' => now()]); R::log((int)$tid, 'status', 'Cancelled: scope changed to ' . $d['scope']); }
      R::put('pause_until', '0'); R::put('rounds', '0');
    }
    R::put('cc', trim((string)($d['cc'] ?? '')));
    R::put('phone', $wa); R::put('exclude', implode(',', array_map('intval', $d['exclude'] ?? []))); R::put('on', $r->boolean('on') ? '1' : '0');
    if ($wa !== $old['wa']) DB::table('restock_tasks')->whereNull('closed_at')->update(['assignee_name' => trim($d['name']), 'assignee_wa' => $wa, 'updated_at' => now()]);
    R::log(null, 'setting', 'Settings saved: ' . ($r->boolean('on') ? 'ON' : 'OFF') . ', ' . trim($d['name']) . ' …' . substr($wa, -4) . ', ' . $d['from'] . '-' . $d['to'] . ', every ' . $d['every'] . ' min, lead ' . $d['lead'] . ' d, safety ' . $d['safety'] . ' d, cover ' . $d['cover'] . ' d');
    return back()->with('ok', 'সেভ হয়েছে।' . ($wa !== $old['wa'] ? ' নম্বর বদলেছে: নতুন নম্বর থেকে একটি উত্তর না আসা পর্যন্ত এটি "যাচাই হয়নি" থাকবে।' : ''));
  }
  public function act(Request $r, string $a) {
    abort_unless(ProfitFloor::admin(), 403); $c = R::cfg();
    if ($a === 'submit') { [$ok, $m] = R::submitTemplate(); R::log(null, 'setting', 'Template submit: ' . $m); return back()->with($ok ? 'ok' : 'err', 'Template: ' . $m); }
    if ($a === 'sync') { [$ok, $m] = R::syncTemplate(); return back()->with($ok ? 'ok' : 'err', 'Template status: ' . $m); }
    if ($a === 'scan') { [$o, $cl] = R::scan(); R::log(null, 'round', 'Scan run by hand: ' . $o . ' opened, ' . $cl . ' closed'); return back()->with('ok', 'Scan: ' . $o . ' opened, ' . $cl . ' closed.'); }
    if ($a === 'verify') { if (!R::validWa($c['wa'])) return back()->with('err', 'আগে নম্বর সেভ করুন।'); if (R::tplStatus() !== 'APPROVED') return back()->with('err', 'টেমপ্লেট এখনো অনুমোদিত নয় (' . R::tplStatus() . ')।'); [$ok, $m] = R::sendTemplate($c['wa'], $c['name'], R::pending()->count()); return back()->with($ok ? 'ok' : 'err', 'Verification message: ' . $m . '. The number is verified when a reply comes from it.'); }
    if ($a === 'send') { if (!R::validWa($c['wa'])) return back()->with('err', 'আগে নম্বর সেভ করুন।'); R::put('last_round', (string)time()); $n = R::flush($c['wa']); $m = ($n > 0 ? $n . ' update(s), ' : '') . R::digest($c['wa']); R::copy(); R::log(null, 'round', 'Reminder sent by hand: ' . $m); return back()->with('ok', 'Reminder: ' . $m); }
    if ($a === 'watch') { R::put('margin_day', ''); R::rateWatch(); R::marginWatch(); $n = R::notices()->count(); R::log(null, 'round', 'Rate / margin check run by hand: ' . $n . ' update(s) waiting'); return back()->with('ok', 'Rate / margin check done: ' . $n . ' update(s) waiting to be sent.'); }
    if ($a === 'drop-old') { [$ok, $m] = R::dropOldTemplate(); R::log(null, 'setting', 'Old template: ' . $m); return back()->with($ok ? 'ok' : 'err', 'Old template: ' . $m); }
    if ($a === 'resume') { R::put('pause_until', '0'); R::log(null, 'pause', 'Pause removed'); return back()->with('ok', 'বিরতি তুলে দেওয়া হয়েছে।'); }
    abort(404);
  }
  /** Close or cancel one task by hand, or put a staff note on it. */
  public function task(Request $r, int $id) {
    abort_unless(ProfitFloor::admin(), 403); $d = $r->validate(['do' => 'required|in:cancel,note', 'note' => 'nullable|string|max:500']);
    $t = DB::table('restock_tasks')->where('id', $id)->whereNull('closed_at')->first(); if (!$t) return back()->with('err', 'কাজটি আর খোলা নেই।');
    if ($d['do'] === 'cancel') { DB::table('restock_tasks')->where('id', $id)->update(['status' => 'cancelled', 'closed_at' => now(), 'closed_why' => 'cancelled by admin', 'updated_at' => now()]); R::log($id, 'status', 'Cancelled by admin' . (!empty($d['note']) ? ' · ' . $d['note'] : '')); }
    else { if (trim((string)($d['note'] ?? '')) === '') return back()->with('err', 'নোট লিখুন।'); R::apply($id, 'note', 'Admin: ' . trim($d['note']), auth()->user()->name ?? 'Admin'); }
    return back()->with('ok', 'সেভ হয়েছে।');
  }
  /** Product-level control: stock limit, lead time, target stock, or leave this product out. */
  public function product(Request $r) {
    abort_unless(ProfitFloor::admin(), 403);
    $d = $r->validate(['product_id' => 'required|integer|exists:products,id', 'limit' => 'nullable|integer|min:0|max:100000', 'lead' => 'nullable|integer|min:0|max:60', 'target' => 'nullable|integer|min:0|max:100000']);
    $p = DB::table('products')->find($d['product_id']);
    $u = ['low_stock_alert' => ($d['limit'] ?? null) ?: null, 'restock_lead_days' => isset($d['lead']) && $d['lead'] !== null ? (int)$d['lead'] : null, 'reorder_target' => ($d['target'] ?? null) ?: null, 'restock_off' => $r->boolean('off'), 'updated_at' => now()];
    DB::table('products')->where('id', $p->id)->update($u);
    R::log(null, 'setting', 'Product "' . $p->name . '": limit ' . ($u['low_stock_alert'] ?? 'default') . ', lead ' . ($u['restock_lead_days'] ?? 'default') . ' d, target ' . ($u['reorder_target'] ?? 'auto') . ($u['restock_off'] ? ', left out' : ''));
    return back()->with('ok', 'সেভ হয়েছে: ' . $p->name);
  }
  /** Admin test: handle a text exactly as if it had arrived on WhatsApp from the purchase manager's number. The answer goes to that number. */
  public function simulate(Request $r) {
    abort_unless(ProfitFloor::admin(), 403); $d = $r->validate(['text' => 'nullable|string|max:500', 'payload' => 'nullable|string|max:60']); $c = R::cfg();
    $ok = R::inbound($c['wa'], !empty($d['payload']) ? 'interactive' : 'text', (string)($d['text'] ?? ''), ($d['payload'] ?? null) ?: null, null);
    return back()->with('ok', 'Test reply handled: ' . ($ok ? 'taken as a restock answer' : 'not a restock answer') . '. See History below.');
  }
  /** Product photo as JPEG for the WhatsApp card (signed link, our own image files only). */
  public function img(int $id, string $sig) {
    abort_unless(R::imgOk($id, $sig), 404);
    $path = strtok((string)DB::table('products')->where('id', $id)->value('image'), '?'); abort_unless($path && str_starts_with($path, '/uploads/') && !str_contains($path, '..'), 404);
    $abs = public_path(ltrim($path, '/')); abort_unless(is_file($abs) && filesize($abs) < 12 * 1048576, 404);
    $src = @imagecreatefromstring((string)file_get_contents($abs)); abort_unless($src, 404);
    $w = imagesx($src); $h = imagesy($src); $sc = min(1, 800 / max($w, $h)); $tw = max(1, (int)round($w * $sc)); $th = max(1, (int)round($h * $sc));
    $dst = imagecreatetruecolor($tw, $th); imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
    ob_start(); imagejpeg($dst, null, 85); $b = (string)ob_get_clean();
    return response($b, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'public, max-age=86400', 'X-Robots-Tag' => 'noindex']);
  }
}
