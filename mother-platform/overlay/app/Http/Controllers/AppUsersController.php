<?php
namespace App\Http\Controllers;

use App\Support\AppUsers as U;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Customers -> App Users: live names, install / last-seen history, device, push state, reward. */
class AppUsersController extends Controller
{
  public function live() { return response()->json(U::live())->header('Cache-Control', 'no-store'); }

  public function index(Request $r) {
    $rows = collect(); $live = U::live(); $st = ['users' => 0, 'push' => 0, 'guests' => 0];
    if (U::ready()) {
      $cid = DB::raw('coalesce(i.login_customer_id, i.customer_id)');
      $q = DB::table('app_installs as i')->leftJoin('customers as c', 'c.id', '=', $cid);
      if ($r->filled('q')) { $t = '%' . trim((string)$r->q) . '%'; $q->where(fn($x) => $x->where('c.name', 'ilike', $t)->orWhere('c.phone', 'like', $t)); }
      $f = (string)$r->get('f', '');
      if ($f === 'live') $q->where('i.last_seen_at', '>=', now()->subSeconds(U::LIVE_SEC))->whereNotNull('i.login_customer_id');
      elseif ($f === 'guest') $q->whereNull('c.id');
      elseif ($f === 'users') $q->whereNotNull('c.id');
      $rows = $q->orderByDesc('i.last_seen_at')->paginate(50)->withQueryString();
      $rows->getCollection()->transform(function ($x) { return $x; });
      $q2 = $rows->getCollection();
      $ids = $q2->map(fn($x) => $x->login_customer_id ?: $x->customer_id)->filter()->all();
      $rw = DB::table('app_rewards')->whereIn('customer_id', $ids ?: [0])->get()->keyBy('customer_id');
      $pu = DB::table('push_subscriptions')->whereIn('customer_id', $ids ?: [0])->where('active', true)->select('customer_id', DB::raw('count(*) n'))->groupBy('customer_id')->pluck('n', 'customer_id');
      $ord = DB::table('orders')->where('via_app', true)->whereIn('customer_id', $ids ?: [0])->select('customer_id', DB::raw('count(*) n'))->groupBy('customer_id')->pluck('n', 'customer_id');
      $rows->getCollection()->each(function ($x) use ($rw, $pu, $ord) { $k = $x->login_customer_id ?: $x->customer_id; $x->_rw = $rw[$k] ?? null; $x->_push = (int)($pu[$k] ?? 0); $x->_ord = (int)($ord[$k] ?? 0); });
      $st = ['users' => (int)DB::table('app_installs')->where(fn($a) => $a->whereNotNull('login_customer_id')->orWhereNotNull('customer_id'))->count(), 'push' => (int)DB::table('push_subscriptions')->where('active', true)->whereIn('customer_id', U::customerIds())->distinct('customer_id')->count('customer_id'), 'guests' => $live['guests']];
    }
    return view('app_users', ['rows' => $rows, 'live' => $live, 'st' => $st, 'f' => $r->only(['q', 'f'])]);
  }

  /** Send one customer an individual notification (push to their App devices + saved in their in-app notification list). */
  public function notify(Request $r, $id) {
    $c = DB::table('customers')->find((int)$id);
    if (!$c) return response()->json(['ok' => false, 'msg' => 'Customer not found'], 404);
    $d = $r->validate(['title' => 'required|string|max:120', 'body' => 'required|string|max:400', 'type' => 'required|in:special_discount,coupon,new_arrival,support_reply', 'url' => 'nullable|string|max:300']);
    $url = trim((string)($d['url'] ?? '')) ?: '/'; if (!preg_match('#^(/|https://)#', $url)) $url = '/' . $url;
    $kind = $d['type'] === 'support_reply' ? 'transactional' : 'marketing';
    $cat = \App\Support\Push::categoryOf($d['type']);
    if (!(\App\Support\Push::prefs((int)$c->id)[$cat] ?? true)) return response()->json(['ok' => false, 'msg' => 'এই কাস্টমার "' . (\App\Support\Push::CATEGORIES[$cat] ?? $cat) . '" নোটিফিকেশন বন্ধ রেখেছেন, তাই পাঠানো হয়নি।']);
    $devices = (int)DB::table('push_subscriptions')->where('customer_id', $c->id)->where('active', true)->whereNull('label')->count();
    $cid = DB::table('push_campaigns')->insertGetId(['kind' => $kind, 'type' => $d['type'], 'title' => $d['title'], 'body' => $d['body'], 'url' => $url, 'audience_type' => 'id', 'audience_value' => (string)$c->id, 'schedule_at' => now(), 'status' => 'scheduled', 'created_by' => optional(auth()->user())->name, 'created_at' => now(), 'updated_at' => now()]);
    if ($devices > 0) { try { \App\Support\Push::runCampaign($cid, 50); } catch (\Throwable $e) {} }
    else {
      DB::table('customer_notifications')->insert(['customer_id' => $c->id, 'campaign_id' => $cid, 'category' => $cat, 'type' => $d['type'], 'title' => $d['title'], 'body' => $d['body'], 'url' => $url, 'created_at' => now()]);
      DB::table('push_campaigns')->where('id', $cid)->update(['status' => 'sent', 'targeted' => 0, 'sent_at' => now(), 'updated_at' => now()]);
    }
    $row = DB::table('push_campaigns')->find($cid);
    $name = $c->name ?: $c->phone;
    if ($devices === 0) return response()->json(['ok' => true, 'msg' => $name . '-এর কোনো পুশ-চালু ডিভাইস নেই। নোটিফিকেশনটি তার অ্যাকাউন্টের নোটিফিকেশন তালিকায় সেভ হয়েছে; অ্যাপ খুললে দেখবেন।']);
    return response()->json(['ok' => (int)$row->sent > 0, 'msg' => (int)$row->sent > 0 ? $name . '-কে পাঠানো হয়েছে (' . (int)$row->sent . ' ডিভাইস)।' : $name . '-এর ডিভাইসে পাঠানো যায়নি (ডিভাইস মেয়াদোত্তীর্ণ হতে পারে)। অ্যাকাউন্টের নোটিফিকেশন তালিকায় সেভ হয়েছে।']);
  }
}
