<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/** My Day: each staff member sees their own good work and their own slips, from the same records the owner's reports use. Read-only. Admins may open anyone's page. */
class StaffDayController extends Controller
{
  /** When every extension rings together each agent gets a 'missed' row even if a colleague picked up: that is not this agent's slip. */
  const NOT_PICKED_BY_OTHER = "not exists (select 1 from call_logs a2 where a2.id <> call_logs.id and right(a2.phone, 11) = right(call_logs.phone, 11) and a2.direction = 'in' and a2.connected_at is not null and a2.started_at between call_logs.started_at - interval '2 minutes' and call_logs.started_at + interval '2 minutes')";
  /** One person's numbers between two UTC instants. Every source is optional: a missing table just gives zero. */
  public static function stats(object $u, $from, $to): array {
    $o = ['chats'=>0, 'replies'=>0, 'reply_s'=>null, 'calls'=>0, 'call_ok'=>0, 'orders'=>0, 'approved'=>0, 'late'=>0, 'idle'=>0, 'missed'=>0];
    $name = trim((string)$u->name); $id = (int)$u->id;
    try { $r = DB::table('chat_messages')->where('created_at', '>=', $from)->where('created_at', '<', $to)->where('dir', 'out')->where('status', '<>', 'failed')->whereRaw('not coalesce(is_ai, false)')->where('user_name', $name)->selectRaw('count(*) m, count(distinct thread_id) c')->first(); $o['replies'] = (int)$r->m; $o['chats'] = (int)$r->c; } catch (\Throwable $e) {}
    try { $r = DB::selectOne("select percentile_cont(0.5) within group (order by w) med from (select extract(epoch from (o.created_at - (select min(i.created_at) from chat_messages i where i.thread_id = o.thread_id and i.dir = 'in' and i.created_at <= o.created_at and i.created_at > coalesce((select max(p.created_at) from chat_messages p where p.thread_id = o.thread_id and p.dir = 'out' and p.status <> 'failed' and p.created_at < o.created_at), timestamp '2000-01-01')))) w from chat_messages o where o.created_at >= ? and o.created_at < ? and o.dir = 'out' and o.status <> 'failed' and not coalesce(o.is_ai, false) and o.user_name = ?) x where w is not null", [$from, $to, $name]); if ($r && $r->med !== null) $o['reply_s'] = (int)round($r->med); } catch (\Throwable $e) {}
    try { $r = DB::table('call_logs')->where('user_id', $id)->where('started_at', '>=', $from)->where('started_at', '<', $to)->selectRaw("count(*) filter (where connected_at is not null or duration > 0) ans, count(*) filter (where outcome = 'confirmed') ok, count(*) filter (where direction = 'in' and connected_at is null and coalesce(duration, 0) = 0 and status in ('missed', 'rejected') and ".self::NOT_PICKED_BY_OTHER.") missed")->first(); $o['calls'] = (int)$r->ans; $o['call_ok'] = (int)$r->ok; $o['missed'] = (int)$r->missed; } catch (\Throwable $e) {}
    try { $r = DB::table('order_activity_logs')->where('source', 'staff')->where('staff_id', $id)->where('created_at', '>=', $from)->where('created_at', '<', $to)->selectRaw("count(distinct order_id) h, count(*) filter (where action_type = 'status_changed' and new_value = 'Approved') a")->first(); $o['orders'] = (int)$r->h; $o['approved'] = (int)$r->a; } catch (\Throwable $e) {}
    try { $o['late'] = (int)DB::table('chat_wait_notices')->where('user_id', $id)->where('assigned', true)->where('created_at', '>=', $from)->where('created_at', '<', $to)->count(); } catch (\Throwable $e) {}
    try { $o['idle'] = (int)DB::table('staff_idle_notices')->where('user_id', $id)->where('created_at', '>=', $from)->where('created_at', '<', $to)->where(fn($w) => $w->whereNull('verdict')->orWhere('verdict', '<>', 'accepted'))->count(); } catch (\Throwable $e) {}
    $o['good'] = $o['chats'] + $o['calls'] + $o['approved']; $o['bad'] = $o['late'] + $o['idle'] + $o['missed'];
    return $o;
  }
  public function index(Request $r) {
    $me = Auth::user(); $u = $me; $admin = \App\Support\ProfitFloor::admin();
    if ($admin && $r->filled('u')) { $x = DB::table('users')->find((int)$r->query('u')); if ($x) $u = $x; }
    $d0 = now('Asia/Dhaka')->startOfDay();
    $today = self::stats($u, $d0->copy()->utc(), $d0->copy()->addDay()->utc());
    $days = []; for ($i = 1; $i <= 6; $i++) { $a = $d0->copy()->subDays($i); $days[] = ['d'=>$a->format('D, d M')] + self::stats($u, $a->copy()->utc(), $a->copy()->addDay()->utc()); }
    $slips = [];
    try { foreach (DB::table('chat_wait_notices')->where('user_id', $u->id)->where('assigned', true)->where('created_at', '>=', $d0->copy()->utc())->orderByDesc('id')->limit(30)->get() as $n) $slips[] = ['at'=>$n->created_at, 'what'=>'Late reply', 'detail'=>trim((string)($n->customer ?: 'A customer')).' waited '.(int)$n->wait_min.' minutes without a reply.', 'href'=>'/admin/chat?open='.(int)$n->thread_id]; } catch (\Throwable $e) {}
    try { foreach (DB::table('staff_idle_notices')->where('user_id', $u->id)->where('created_at', '>=', $d0->copy()->utc())->orderByDesc('id')->limit(30)->get() as $n) $slips[] = ['at'=>$n->created_at, 'what'=>'Idle notice', 'detail'=>'Idle for '.(int)$n->idle_minutes.' minutes while '.(int)$n->waiting_chats.' chats and '.(int)$n->waiting_orders.' orders were waiting.'.($n->verdict ? ' Reviewed: '.$n->verdict.'.' : ''), 'href'=>null]; } catch (\Throwable $e) {}
    try { foreach (DB::table('call_logs')->where('user_id', $u->id)->where('direction', 'in')->whereNull('connected_at')->whereIn('status', ['missed', 'rejected'])->whereRaw(self::NOT_PICKED_BY_OTHER)->where('started_at', '>=', $d0->copy()->utc())->orderByDesc('id')->limit(30)->get() as $n) $slips[] = ['at'=>$n->started_at, 'what'=>'Missed call', 'detail'=>trim((string)($n->customer_name ?: 'Customer')).' '.$n->phone.' called and was not answered.', 'href'=>'/admin/call-center/history?f=missed']; } catch (\Throwable $e) {}
    usort($slips, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));
    $limit = max(5, min(240, (int)(\App\Support\Sms::secret('chat_stale_min') ?: 30)));
    $staff = $admin ? DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name']) : collect();
    return view('staff_day', compact('u', 'me', 'admin', 'today', 'days', 'slips', 'limit', 'staff'));
  }
  /** Unseen "customer kept waiting" notices for the signed-in person (polled by the popup on every admin page). */
  public function notices() {
    try { $rows = DB::table('chat_wait_notices')->where('user_id', Auth::id())->whereNull('seen_at')->where('created_at', '>=', now()->subHours(12))->orderBy('id')->limit(5)->get(['id', 'thread_id', 'customer', 'wait_min', 'assigned', 'created_at']); } catch (\Throwable $e) { $rows = collect(); }
    return response()->json(['notices'=>$rows->map(fn($n) => ['id'=>(int)$n->id, 'thread'=>(int)$n->thread_id, 'customer'=>(string)($n->customer ?: 'A customer'), 'min'=>(int)$n->wait_min, 'mine'=>(bool)$n->assigned])->all()]);
  }
  public function seen(Request $r) {
    $ids = array_slice(array_filter(array_map('intval', (array)$r->input('ids', []))), 0, 20);
    if ($ids) try { DB::table('chat_wait_notices')->where('user_id', Auth::id())->whereIn('id', $ids)->whereNull('seen_at')->update(['seen_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return response()->json(['ok'=>true]);
  }
}
