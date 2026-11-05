<?php
namespace App\Http\Controllers;

use App\Support\StaffTrack as T;
use App\Support\Inv;
use App\Support\Pg;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Staff Activity & Order Tracking. READ-ONLY: every number comes from the append-only logs (order_activity_logs, staff_login_logs, call_logs, sms_logs, audit_logs).
 * Nothing on these pages can edit or delete a log row.
 */
class StaffTrackingController extends Controller
{
  const PAGES = [
    'dashboard' => 'Dashboard', 'activity' => 'Staff Activity', 'orders' => 'Order Tracking', 'status' => 'Status Changes', 'payment' => 'Payment Changes', 'courier' => 'Courier Changes',
    'price' => 'Price & Discount Changes', 'comm' => 'Customer Communication', 'logins' => 'Login & Session Activity', 'audit' => 'Audit Logs', 'reports' => 'Reports',
  ];
  /** Rows scanned when looking for suspicious actions (newest first). */
  const SCAN = 6000;

  private function range(Request $r): array { return T::range($r->query('range'), $r->query('from'), $r->query('to')); }

  /** Base query on the order activity log joined to the order (invoice, customer). */
  private function activityQ(Request $r, ?string $group, array $rg)
  {
    $q = DB::table('order_activity_logs as a')->leftJoin('orders as o', 'o.id', '=', 'a.order_id')
      ->select('a.*', 'o.invoice', 'o.customer_name', 'o.phone as customer_phone', 'o.status as order_status')
      ->where('a.created_at', '>=', $rg[0])->where('a.created_at', '<', $rg[1]);
    if ($group && isset(T::GROUPS[$group])) $q->whereRaw('a.action_type ~ ?', [T::re($group)]);
    if ($r->filled('staff')) { $s = (string)$r->query('staff'); $s === 'System' ? $q->where('a.source', 'system') : $q->where('a.staff_name', $s); }
    if ($r->filled('type')) $q->where('a.action_type', (string)$r->query('type'));
    if ($r->filled('q')) {
      $s = trim((string)$r->query('q')); $c = Inv::candidates($s);
      $q->where(function ($w) use ($s, $c) {
        if ($c) $w->whereIn('o.invoice', $c);
        $w->orWhere('o.customer_name', 'ilike', '%' . $s . '%')->orWhere('o.phone', 'like', '%' . preg_replace('/\D+/', '', $s) . '%');
        if (ctype_digit($s)) $w->orWhere('a.order_id', (int)$s);
      });
    }
    return $q;
  }

  private function roles(): array { try { return DB::table('users')->pluck('role', 'name')->all(); } catch (\Throwable $e) { return []; } }

  private function staffNames()
  {
    try { return DB::table('users')->orderBy('name')->pluck('name')->all(); } catch (\Throwable $e) { return []; }
  }

  /** Suspicious entries in the range, newest first. The rules are the visible ones in StaffTrack::flag(); a SQL pre-filter keeps the scan small. */
  private function flagged(Request $r, array $rg, int $limit = 0): array
  {
    $q = $this->activityQ($r, null, $rg)->where('a.source', 'staff')
      ->where(function ($w) {
        $w->whereIn('a.action_type', ['status_changed', 'manual_override', 'payment_verified', 'discount_line_changed', 'order_edited'])
          ->orWhereRaw("extract(hour from ((a.created_at at time zone 'UTC') at time zone 'Asia/Dhaka')) < 5");
      })->orderByDesc('a.id')->limit(self::SCAN);
    $out = [];
    foreach ($q->get() as $a) { $f = T::flag($a); if ($f !== null) { $a->flag = $f; $out[] = $a; if ($limit && count($out) >= $limit) break; } }
    return $out;
  }

  /** One row per staff member for the range. */
  private function staffSummary(array $rg)
  {
    $rc = T::re('courier'); $rm = T::re('comm');
    $sql = "select staff_name, count(*) actions, count(distinct order_id) orders,
      count(*) filter (where action_type='status_changed' and new_value='Approved') confirmed,
      count(*) filter (where action_type='status_changed' and new_value='Cancel') cancelled,
      count(*) filter (where action_type='status_changed') status_ch,
      count(*) filter (where action_type='order_edited') edits,
      count(*) filter (where action_type ~ '^payment_') pay,
      count(*) filter (where action_type='discount_line_changed') disc,
      count(*) filter (where action_type ~ ?) courier,
      count(*) filter (where action_type ~ ?) comm,
      max(created_at) last_at
      from order_activity_logs where source='staff' and created_at >= ? and created_at < ? group by staff_name order by actions desc";
    return DB::select($sql, [$rc, $rm, $rg[0], $rg[1]]);
  }

  public function show(Request $r, string $page = 'dashboard')
  {
    abort_unless(isset(self::PAGES[$page]), 404);
    $ready = T::tableOk('order_activity_logs'); $rg = $this->range($r);
    $v = ['page' => $page, 'pages' => self::PAGES, 'title' => self::PAGES[$page], 'rg' => $rg, 'ready' => $ready, 'staffNames' => $this->staffNames(), 'q' => $r->query(), 'roles' => $this->roles()];
    if (!$ready) return view('staff_tracking', $v);
    return match ($page) {
      'dashboard' => $this->dashboard($r, $rg, $v),
      'orders' => $this->orders($r, $rg, $v),
      'logins' => $this->logins($r, $rg, $v),
      'audit' => $this->audit($r, $rg, $v),
      'reports' => $this->reports($r, $rg, $v),
      default => $this->activity($r, $rg, $v, $page === 'activity' ? null : $page),
    };
  }

  private function dashboard(Request $r, array $rg, array $v)
  {
    $row = DB::selectOne("select
      count(distinct staff_id) filter (where source='staff') active,
      count(distinct order_id) filter (where source='staff') handled,
      count(*) filter (where source='staff' and action_type='status_changed' and new_value='Approved') confirmed,
      count(*) filter (where source='staff' and action_type='status_changed' and new_value='Cancel') cancelled,
      count(distinct order_id) filter (where source='staff' and action_type='order_edited') edited,
      count(*) filter (where source='staff' and action_type='status_changed') status_ch,
      count(*) filter (where source='staff' and action_type ~ '^payment_') pay,
      count(*) filter (where source='staff' and action_type='discount_line_changed') disc
      from order_activity_logs where created_at >= ? and created_at < ?", [$rg[0], $rg[1]]);
    $calls = 0; $msgs = 0;
    try { $calls = (int)DB::table('call_logs')->where('started_at', '>=', $rg[0])->where('started_at', '<', $rg[1])->where(fn($w) => $w->where('status', 'answered')->orWhere('duration', '>', 0))->count(); } catch (\Throwable $e) {}
    try { $msgs = (int)DB::table('sms_logs')->where('created_at', '>=', $rg[0])->where('created_at', '<', $rg[1])->where(fn($w) => $w->whereNotNull('sent_at')->orWhereIn('status', ['sent', 'delivered']))->count(); } catch (\Throwable $e) {}
    $sus = $this->flagged($r, $rg);
    $v['cards'] = [
      ['Active Staff', (int)$row->active, 'with logged actions'], ['Orders Handled', (int)$row->handled, 'distinct orders touched'], ['Orders Confirmed', (int)$row->confirmed, 'moved to Approved'],
      ['Orders Cancelled', (int)$row->cancelled, 'moved to Cancel'], ['Orders Edited', (int)$row->edited, 'items / price / address'], ['Status Changes', (int)$row->status_ch, 'by staff'],
      ['Payment Changes', (int)$row->pay, 'verify / reject / add'], ['Discount Changes', (int)$row->disc, 'on order lines'], ['Calls Completed', $calls, 'connected calls'],
      ['Messages Sent', $msgs, 'SMS sent'], ['Suspicious Actions', count($sus), count($sus) >= self::SCAN ? 'newest ' . self::SCAN . ' scanned' : 'see rules below'],
    ];
    $v['sus'] = array_slice($sus, 0, 12); $v['summary'] = $this->staffSummary($rg);
    return view('staff_tracking', $v);
  }

  private function activity(Request $r, array $rg, array $v, ?string $group)
  {
    $v['group'] = $group;
    $v['types'] = DB::table('order_activity_logs')->distinct()->orderBy('action_type')->pluck('action_type')->filter(fn($t) => !$group || T::group($t) === $group)->values()->all();
    if ($r->boolean('flag') && !$group) {
      $v['flagMode'] = true; $v['flags'] = $this->flagged($r, $rg, 300); $v['rows'] = null;
      return view('staff_tracking', $v);
    }
    $v['rows'] = $this->activityQ($r, $group, $rg)->orderByDesc('a.id')->paginate(Pg::per(50))->withQueryString();
    return view('staff_tracking', $v);
  }

  /** Orders with activity in the range; one search hit (or ?order=) opens that order's full timeline. */
  private function orders(Request $r, array $rg, array $v)
  {
    $oid = (int)$r->query('order');
    if (!$oid && $r->filled('q')) {
      $c = Inv::candidates(trim((string)$r->query('q')));
      if ($c) { $hit = DB::table('orders')->whereIn('invoice', $c)->pluck('id'); if ($hit->count() === 1) $oid = (int)$hit->first(); }
    }
    if ($oid) {
      $o = DB::table('orders')->find($oid);
      if ($o) {
        $asc = $r->query('sort') === 'asc'; $ev = collect();
        foreach (DB::table('order_activity_logs')->where('order_id', $oid)->get() as $a) {
          $ev->push((object)['id' => $a->id, 'at' => $a->created_at, 'kind' => 'activity', 'who' => $a->staff_name, 'source' => $a->source, 'type' => $a->action_type, 'label' => T::label($a->action_type),
            'old' => $a->old_value, 'new' => $a->new_value, 'desc' => $a->description, 'device' => T::device($a->user_agent, $a->ip_address), 'flag' => T::flag($a), 'group' => T::group($a->action_type), 'meta' => $a->metadata]);
        }
        try { foreach (DB::table('call_logs')->where('order_id', $oid)->get() as $c) $ev->push((object)['id' => 'c' . $c->id, 'at' => $c->started_at ?? $c->created_at, 'kind' => 'call', 'who' => $c->user_name, 'source' => 'staff', 'type' => 'call', 'label' => 'Call (' . ($c->direction === 'in' ? 'incoming' : 'outgoing') . ')', 'old' => null, 'new' => ucfirst((string)$c->status) . ($c->duration ? ' · ' . gmdate('i:s', (int)$c->duration) : ''), 'desc' => $c->note, 'device' => null, 'flag' => null, 'group' => 'comm', 'meta' => null]); } catch (\Throwable $e) {}
        try { foreach (DB::table('sms_logs')->where('order_id', $oid)->get() as $m) $ev->push((object)['id' => 's' . $m->id, 'at' => $m->sent_at ?? $m->created_at, 'kind' => 'sms', 'who' => 'System', 'source' => 'system', 'type' => 'sms', 'label' => 'SMS sent' . ($m->event ? ' (' . $m->event . ')' : ''), 'old' => null, 'new' => ucfirst((string)$m->status), 'desc' => mb_substr((string)$m->body, 0, 220), 'device' => null, 'flag' => null, 'group' => 'comm', 'meta' => null]); } catch (\Throwable $e) {}
        $ev = $ev->sortBy(fn($e) => strtotime((string)$e->at) * 1000000 + (is_numeric($e->id) ? (int)$e->id : 0), SORT_REGULAR, !$asc)->values();
        $v['order'] = $o; $v['events'] = $ev; $v['asc'] = $asc; $v['hl'] = (string)$r->query('hl');
        return view('staff_tracking', $v);
      }
    }
    $q = DB::table('order_activity_logs as a')->join('orders as o', 'o.id', '=', 'a.order_id')->where('a.created_at', '>=', $rg[0])->where('a.created_at', '<', $rg[1]);
    if ($r->filled('staff')) $q->where('a.staff_name', (string)$r->query('staff'));
    if ($r->filled('q')) { $s = trim((string)$r->query('q')); $q->where(fn($w) => $w->whereIn('o.invoice', Inv::candidates($s) ?: ['-'])->orWhere('o.customer_name', 'ilike', '%' . $s . '%')->orWhere('o.phone', 'like', '%' . preg_replace('/\D+/', '', $s) . '%')); }
    $rows = $q->groupBy('a.order_id', 'o.invoice', 'o.customer_name', 'o.phone', 'o.status')
      ->select('a.order_id', 'o.invoice', 'o.customer_name', 'o.phone', 'o.status', DB::raw('count(*) n'), DB::raw("count(distinct a.staff_name) filter (where a.source='staff') staffs"), DB::raw('max(a.created_at) last_at'))
      ->orderByDesc('last_at')->paginate(Pg::per(50))->withQueryString();
    $ids = $rows->pluck('order_id')->all(); $last = [];
    if ($ids) foreach (DB::select('select distinct on (order_id) order_id, staff_name, action_type, new_value from order_activity_logs where order_id in (' . implode(',', array_map('intval', $ids)) . ') order by order_id, id desc') as $l) $last[$l->order_id] = $l;
    $v['rows'] = $rows; $v['last'] = $last; $v['notFound'] = $r->filled('q') && $rows->total() === 0;
    return view('staff_tracking', $v);
  }

  private function loginQ(Request $r, array $rg)
  {
    $q = DB::table('staff_login_logs')->where('created_at', '>=', $rg[0])->where('created_at', '<', $rg[1]);
    if ($r->filled('staff')) $q->where(fn($w) => $w->where('user_name', (string)$r->query('staff'))->orWhere('email', (string)$r->query('staff')));
    if ($r->filled('event') && in_array($r->query('event'), ['login', 'failed', 'logout'], true)) $q->where('event', (string)$r->query('event'));
    return $q;
  }

  private function logins(Request $r, array $rg, array $v)
  {
    $v['ok'] = T::tableOk('staff_login_logs'); if (!$v['ok']) return view('staff_tracking', $v);
    $v['rows'] = $this->loginQ($r, $rg)->orderByDesc('id')->paginate(Pg::per(50))->withQueryString();
    $v['sess'] = DB::select("select coalesce(user_name, email) who, count(*) filter (where event='login') logins, count(*) filter (where event='failed') failed, count(*) filter (where event='logout') logouts,
      count(distinct ip_address) filter (where event='login') ips, max(created_at) filter (where event='login') last_login, max(created_at) filter (where event='logout') last_logout
      from staff_login_logs where created_at >= ? and created_at < ? group by coalesce(user_name, email) order by last_login desc nulls last", [$rg[0], $rg[1]]);
    return view('staff_tracking', $v);
  }

  private function auditQ(Request $r, array $rg)
  {
    $q = DB::table('audit_logs')->where('created_at', '>=', $rg[0])->where('created_at', '<', $rg[1]);
    if ($r->filled('staff')) $q->where('user_name', (string)$r->query('staff'));
    if ($r->filled('q')) { $s = '%' . trim((string)$r->query('q')) . '%'; $q->where(fn($w) => $w->where('action', 'ilike', $s)->orWhere('subject', 'ilike', $s)); }
    return $q;
  }

  private function audit(Request $r, array $rg, array $v)
  {
    $v['rows'] = $this->auditQ($r, $rg)->orderByDesc('id')->paginate(Pg::per(50))->withQueryString();
    return view('staff_tracking', $v);
  }

  private function reports(Request $r, array $rg, array $v)
  {
    $v['summary'] = $this->staffSummary($rg);
    $v['daily'] = DB::select("select to_char((created_at at time zone 'UTC') at time zone 'Asia/Dhaka', 'YYYY-MM-DD') d, count(*) actions, count(distinct staff_id) filter (where source='staff') staff, count(distinct order_id) orders
      from order_activity_logs where created_at >= ? and created_at < ? group by 1 order by 1 desc limit 62", [$rg[0], $rg[1]]);
    $v['types'] = DB::select("select action_type, count(*) n from order_activity_logs where created_at >= ? and created_at < ? group by action_type order by n desc limit 40", [$rg[0], $rg[1]]);
    return view('staff_tracking', $v);
  }

  /** CSV of whatever the page shows (same filters), capped at 20,000 rows. */
  public function export(Request $r, string $page)
  {
    abort_unless(isset(self::PAGES[$page]) && $page !== 'dashboard' && $page !== 'orders', 404);
    $rg = $this->range($r); $name = 'staff-tracking-' . $page . '-' . now('Asia/Dhaka')->format('Ymd-His') . '.csv';
    return response()->streamDownload(function () use ($r, $rg, $page) {
      $f = fopen('php://output', 'w'); fwrite($f, "\xEF\xBB\xBF");
      if ($page === 'logins') {
        fputcsv($f, ['Date & Time', 'Staff', 'Email', 'Event', 'IP', 'Device']);
        foreach ($this->loginQ($r, $rg)->orderByDesc('id')->limit(20000)->cursor() as $x) fputcsv($f, [T::dt($x->created_at), $x->user_name, $x->email, $x->event, $x->ip_address, T::device($x->user_agent)]);
      } elseif ($page === 'audit') {
        fputcsv($f, ['Date & Time', 'Staff', 'Action', 'Detail', 'IP']);
        foreach ($this->auditQ($r, $rg)->orderByDesc('id')->limit(20000)->cursor() as $x) fputcsv($f, [T::dt($x->created_at), $x->user_name, $x->action, $x->subject, $x->ip]);
      } elseif ($page === 'reports') {
        fputcsv($f, ['Staff', 'Actions', 'Orders', 'Confirmed', 'Cancelled', 'Status changes', 'Order edits', 'Payment changes', 'Discount changes', 'Courier changes', 'Communication', 'Last action']);
        foreach ($this->staffSummary($rg) as $x) fputcsv($f, [$x->staff_name, $x->actions, $x->orders, $x->confirmed, $x->cancelled, $x->status_ch, $x->edits, $x->pay, $x->disc, $x->courier, $x->comm, T::dt($x->last_at)]);
      } else {
        fputcsv($f, ['Date & Time', 'Staff', 'Role/Source', 'Module', 'Action', 'Invoice', 'Customer', 'Previous value', 'New value', 'Detail', 'IP', 'Device', 'Flag']);
        $g = $page === 'activity' ? null : $page; $roles = DB::table('users')->pluck('role', 'name');
        foreach ($this->activityQ($r, $g, $rg)->orderByDesc('a.id')->limit(20000)->cursor() as $a)
          fputcsv($f, [T::dt($a->created_at), $a->staff_name, $a->source === 'system' ? 'System' : ucfirst((string)($roles[$a->staff_name] ?? 'staff')), T::MODULE[T::group($a->action_type)], T::label($a->action_type), Inv::show($a->invoice), $a->customer_name, $a->old_value, $a->new_value, $a->description, $a->ip_address, T::device($a->user_agent), T::flag($a)]);
      }
      fclose($f);
    }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
  }
}
