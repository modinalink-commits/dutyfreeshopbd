<?php
namespace App\Http\Controllers;

use App\Support\Push;
use App\Support\WebPush;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushAdminController extends Controller
{
  const AUD = ['all' => 'All customers (every subscribed device)', 'customer' => 'Specific customer (phone)', 'vip' => 'VIP customers', 'regular' => 'Regular customers (3+ orders)', 'new' => 'New customers (0-1 order)', 'returning' => 'Returning customers (2+ orders)', 'membership' => 'Membership customers', 'store' => 'Store-specific customers', 'product' => 'Customers who ordered a product', 'category' => 'Customers who ordered from a category'];

  public function index() {
    $tz = 'Asia/Dhaka';
    $camps = DB::table('push_campaigns')->orderByDesc('id')->limit(40)->get()->map(function ($c) use ($tz) {
      $c->when = $c->sent_at ? Carbon::parse($c->sent_at)->timezone($tz)->format('d M, h:i A') : ($c->schedule_at ? Carbon::parse($c->schedule_at)->timezone($tz)->format('d M, h:i A') : '-'); return $c; });
    return view('push_admin', [
      'camps' => $camps, 'aud' => self::AUD, 'types' => Push::TYPES, 'typeNames' => collect(Push::TYPES)->map(fn($g) => collect($g)->map(fn($v) => $v[0]))->all(),
      'stats' => ['subs' => DB::table('push_subscriptions')->where('active', true)->whereNull('label')->count(), 'linked' => DB::table('push_subscriptions')->where('active', true)->whereNull('label')->whereNotNull('customer_id')->count(), 'sent' => (int)DB::table('push_campaigns')->sum('sent'), 'clicks' => (int)DB::table('push_campaigns')->sum('clicks')],
      'stores' => DB::table('stores')->orderBy('name')->get(['id', 'name']), 'cats' => DB::table('categories')->orderBy('name')->get(['id', 'name']),
    ]);
  }

  public function store(Request $r) {
    $d = $r->validate(['kind' => 'required|in:transactional,marketing', 'type' => 'required|string|max:40', 'title' => 'required|string|max:120', 'body' => 'required|string|max:400', 'image' => 'nullable|url|max:500', 'cta' => 'nullable|string|max:40', 'url' => 'nullable|string|max:500', 'audience_type' => 'required|in:' . implode(',', array_keys(self::AUD)), 'audience_value' => 'nullable|string|max:120', 'schedule_at' => 'nullable|string|max:30', 'action' => 'required|in:send,schedule,draft']);
    if (!isset(Push::TYPES[$d['kind']][$d['type']])) return back()->withErrors(['type' => 'Choose a valid notification type'])->withInput();
    $url = trim((string)($d['url'] ?? '')) ?: '/'; if (!preg_match('#^(/|https://)#', $url)) $url = '/' . $url;
    $at = null;
    if ($d['action'] === 'schedule') { if (empty($d['schedule_at'])) return back()->withErrors(['schedule_at' => 'Pick a date and time'])->withInput(); $at = Carbon::parse($d['schedule_at'], 'Asia/Dhaka')->utc(); }
    if ($d['action'] === 'send') $at = now();
    $id = DB::table('push_campaigns')->insertGetId(['kind' => $d['kind'], 'type' => $d['type'], 'title' => $d['title'], 'body' => $d['body'], 'image' => $d['image'] ?: null, 'cta' => $d['cta'] ?: null, 'url' => $url, 'audience_type' => $d['audience_type'], 'audience_value' => $d['audience_value'] ?? null, 'schedule_at' => $at, 'status' => $d['action'] === 'draft' ? 'draft' : 'scheduled', 'created_by' => optional(auth()->user())->name, 'created_at' => now(), 'updated_at' => now()]);
    if ($d['action'] === 'send') { try { Push::runCampaign($id, 100); } catch (\Throwable $e) {} }
    return redirect('/admin/push')->with('ok', $d['action'] === 'draft' ? 'Draft saved.' : ($d['action'] === 'send' ? 'Sending started. It continues in the background.' : 'Scheduled for ' . $at->copy()->timezone('Asia/Dhaka')->format('d M Y, h:i A') . '.'));
  }

  public function cancel($id) {
    DB::table('push_campaigns')->where('id', $id)->whereIn('status', ['draft', 'scheduled', 'sending'])->update(['status' => 'cancelled', 'updated_at' => now()]);
    return back()->with('ok', 'Cancelled.');
  }

  public function sendDraft($id) {
    DB::table('push_campaigns')->where('id', $id)->where('status', 'draft')->update(['status' => 'scheduled', 'schedule_at' => now(), 'updated_at' => now()]);
    try { Push::runCampaign((int)$id, 100); } catch (\Throwable $e) {}
    return back()->with('ok', 'Sending started.');
  }

  public function count(Request $r) { return response()->json(['n' => Push::audienceCount((string)$r->input('t', 'all'), $r->input('v'))]); }

  public function device(Request $r) {
    $e = (string)$r->input('endpoint'); $k = $r->input('keys', []);
    if (!preg_match('#^https://#', $e) || empty($k['p256dh']) || empty($k['auth'])) return response()->json(['ok' => false], 422);
    $h = hash('sha256', $e); $row = ['customer_id' => null, 'endpoint' => $e, 'p256dh' => (string)$k['p256dh'], 'auth' => (string)$k['auth'], 'label' => 'admin', 'ua' => mb_substr((string)$r->userAgent(), 0, 200), 'active' => true, 'fail_count' => 0, 'updated_at' => now()];
    if (DB::table('push_subscriptions')->where('endpoint_hash', $h)->exists()) DB::table('push_subscriptions')->where('endpoint_hash', $h)->update($row); else DB::table('push_subscriptions')->insert($row + ['endpoint_hash' => $h, 'created_at' => now()]);
    return response()->json(['ok' => true]);
  }

  public function test(Request $r) {
    $s = DB::table('push_subscriptions')->where('endpoint_hash', hash('sha256', (string)$r->input('endpoint')))->where('label', 'admin')->first();
    if (!$s) return response()->json(['ok' => false, 'msg' => 'Enable notifications on this device first.'], 404);
    $code = WebPush::send($s, ['title' => mb_substr((string)$r->input('title', 'DutyFreeBD'), 0, 120), 'body' => mb_substr((string)$r->input('body', 'Test notification'), 0, 400), 'url' => $r->input('url') ?: '/', 'image' => $r->input('image') ?: null, 'cta' => $r->input('cta') ?: null, 'tag' => 'admin-test']);
    return response()->json(['ok' => $code >= 200 && $code < 300, 'code' => $code]);
  }
}
