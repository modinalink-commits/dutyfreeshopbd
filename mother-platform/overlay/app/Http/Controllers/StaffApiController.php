<?php
namespace App\Http\Controllers;
use App\Http\Middleware\ModulePermission as MP;
use App\Http\Middleware\StaffApi;
use App\Support\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
/** JSON API for the Mother Commerce Staff mobile app. Thin layer: same tables, same permissions, same services as the web admin. */
class StaffApiController extends Controller {
  private function dt($v): ?string { return $v ? \Carbon\Carbon::parse($v)->timezone('Asia/Dhaka')->format('Y-m-d H:i') : null; }
  private function audit(Request $r, string $action, string $subject): void {
    try { DB::table('audit_logs')->insert(['user_id'=>Auth::id(), 'user_name'=>Auth::user()->name ?? null, 'action'=>'staffapp.'.$action, 'subject'=>mb_substr($subject, 0, 200), 'ip'=>$r->ip(), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
  }
  private function userJson($u): array {
    $rules = MP::rules($u);
    $mods = []; foreach (array_keys(MP::MODULES) as $m) $mods[$m] = $rules === null ? 'all' : ($rules[$m] ?? 'none');
    return ['id'=>$u->id, 'name'=>$u->name, 'email'=>$u->email, 'phone'=>$u->phone ?? null, 'role'=>$u->role, 'permissions'=>$mods];
  }

  // ---- auth ----
  public function login(Request $r) {
    $d = $r->validate(['login'=>'required|string|max:120', 'password'=>'required|string|max:200', 'device_id'=>'required|string|max:80', 'device_name'=>'nullable|string|max:120', 'platform'=>'nullable|in:android,ios', 'app_version'=>'nullable|string|max:20', 'push_token'=>'nullable|string|max:600']);
    $login = trim($d['login']);
    $u = \App\Models\User::query()->where(fn($q) => $q->where('email', $login)->orWhere('phone', $login))->first();
    if (!$u || !$u->active || !Hash::check($d['password'], $u->password)) {
      \App\Support\StaffTrack::login('failed', null, 'app:'.$login, $r);
      return response()->json(['error'=>'invalid_credentials', 'message'=>'Wrong login or password'], 422);
    }
    $blocked = DB::table('staff_api_tokens')->where('user_id', $u->id)->where('device_id', $d['device_id'])->where('blocked', true)->exists();
    if ($blocked) return response()->json(['error'=>'device_blocked', 'message'=>'This device is blocked. Ask a manager.'], 403);
    DB::table('staff_api_tokens')->where('user_id', $u->id)->where('device_id', $d['device_id'])->whereNull('revoked_at')->update(['revoked_at'=>now()]);
    $plain = Str::random(64);
    DB::table('staff_api_tokens')->insert(['user_id'=>$u->id, 'token_hash'=>StaffApi::hash($plain), 'device_id'=>$d['device_id'], 'device_name'=>$d['device_name'] ?? null, 'platform'=>$d['platform'] ?? null, 'app_version'=>$d['app_version'] ?? null, 'push_token'=>$d['push_token'] ?? null, 'ip'=>$r->ip(), 'last_used_at'=>now(), 'expires_at'=>now()->addDays(30), 'created_at'=>now(), 'updated_at'=>now()]);
    \App\Support\StaffTrack::login('login', $u, 'app:'.$login, $r);
    return response()->json(['token'=>$plain, 'expires_in_days'=>30, 'user'=>$this->userJson($u)]);
  }
  public function logout(Request $r) {
    DB::table('staff_api_tokens')->where('id', $r->attributes->get('staff_token_id'))->update(['revoked_at'=>now()]);
    \App\Support\StaffTrack::login('logout', Auth::user(), 'app', $r);
    return response()->json(['ok'=>true]);
  }
  public function me(Request $r) { return response()->json(['user'=>$this->userJson(Auth::user())]); }
  public function devices(Request $r) {
    $u = Auth::user(); $all = in_array($u->role, ['super', 'admin']) && $r->query('all');
    $q = DB::table('staff_api_tokens as t')->join('users as x', 'x.id', '=', 't.user_id')->whereNull('t.revoked_at');
    if (!$all) $q->where('t.user_id', $u->id);
    $rows = $q->orderByDesc('t.last_used_at')->limit(200)->get(['t.id', 't.user_id', 'x.name as staff', 't.device_id', 't.device_name', 't.platform', 't.app_version', 't.last_used_at', 't.blocked', 't.created_at']);
    return response()->json(['devices'=>$rows->map(fn($d) => ['id'=>$d->id, 'staff'=>$d->staff, 'device_name'=>$d->device_name, 'platform'=>$d->platform, 'app_version'=>$d->app_version, 'last_used'=>$this->dt($d->last_used_at), 'blocked'=>(bool)$d->blocked, 'current'=>$d->id == request()->attributes->get('staff_token_id')])]);
  }
  public function deviceRevoke(Request $r, $id) {
    $u = Auth::user(); $row = DB::table('staff_api_tokens')->find($id); abort_unless($row, 404);
    abort_unless($row->user_id == $u->id || in_array($u->role, ['super', 'admin']), 403);
    $upd = ['revoked_at'=>now(), 'updated_at'=>now()]; if ($r->boolean('block') && in_array($u->role, ['super', 'admin'])) $upd['blocked'] = true;
    DB::table('staff_api_tokens')->where('id', $id)->update($upd);
    $this->audit($r, 'device.revoke', 'device #'.$id.' of user #'.$row->user_id.($upd['blocked'] ?? false ? ' (blocked)' : ''));
    return response()->json(['ok'=>true]);
  }
  public function pushToken(Request $r) {
    $d = $r->validate(['push_token'=>'required|string|max:600', 'app_version'=>'nullable|string|max:20']);
    DB::table('staff_api_tokens')->where('id', $r->attributes->get('staff_token_id'))->update(['push_token'=>$d['push_token'], 'app_version'=>$d['app_version'] ?? DB::raw('app_version'), 'updated_at'=>now()]);
    return response()->json(['ok'=>true]);
  }

  // ---- dashboard ----
  public function dashboard(Request $r) {
    $by = DB::table('orders')->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
    $c = fn(...$s) => (int)collect($s)->sum(fn($k) => (int)($by[$k] ?? 0));
    $today = now('Asia/Dhaka')->startOfDay()->utc();
    $out = ['orders'=>[
      'pending'=>$c('Pending', 'New', 'WFP'), 'ai_approved'=>0, 'manual_approved'=>0, 'approved'=>$c('Approved'), 'packaging'=>$c('Packaging'), 'shipment'=>$c('Shipment'),
      'delivered_today'=>(int)DB::table('order_status_logs')->where('to_status', 'Delivered')->where('created_at', '>=', $today)->distinct()->count('order_id'),
      'late_delivery'=>$c('Late Delivery'), 'returns'=>$c('Return Pending', 'Returned'), 'cancelled_today'=>(int)DB::table('order_status_logs')->where('to_status', 'Cancel')->where('created_at', '>=', $today)->distinct()->count('order_id'),
    ]];
    try { $ap = DB::table('orders')->whereIn('status', ['Approved', 'Packaging', 'Shipment'])->selectRaw('approval_source s, count(*) c')->groupBy('approval_source')->pluck('c', 's'); $out['orders']['ai_approved'] = (int)($ap['AI'] ?? 0); $out['orders']['manual_approved'] = (int)($ap['MANUAL'] ?? 0); } catch (\Throwable $e) {}
    $out['messages'] = ['unread'=>Chat::unreadTotal(), 'waiting'=>(int)DB::table('chat_threads')->where('awaiting_reply', true)->where('status', '!=', 'resolved')->count(), 'assigned_to_me'=>(int)DB::table('chat_threads')->where('assigned_to', Auth::id())->where('status', '!=', 'resolved')->count()];
    $out['tasks'] = ['open_mine'=>(int)DB::table('staff_tasks')->where('assigned_to', Auth::id())->whereIn('status', ['open', 'in_progress', 'waiting'])->count(), 'overdue_mine'=>(int)DB::table('staff_tasks')->where('assigned_to', Auth::id())->whereIn('status', ['open', 'in_progress', 'waiting'])->where('due_at', '<', now())->count()];
    try { $out['low_stock'] = (int)DB::table('products')->where('stock', '<', 100)->where('stock', '>', 0)->count(); } catch (\Throwable $e) { $out['low_stock'] = null; }
    return response()->json($out);
  }

  // ---- orders ----
  private function orderCard($o): array {
    return ['id'=>$o->id, 'invoice'=>\App\Support\Inv::show($o->invoice), 'customer'=>$o->customer_name, 'phone'=>$o->phone, 'total'=>(float)$o->total, 'status'=>$o->status,
      'approval'=>($o->approval_source ?? null) ? 'Approved · '.($o->approval_source === 'AI' ? 'AI' : 'Manual') : null, 'payment'=>$o->payment_method ?? null, 'courier'=>$o->courier, 'tracking'=>$o->consignment_id ?? null,
      'source'=>$o->source, 'store'=>$o->store_name ?? null, 'created_at'=>$this->dt($o->created_at)];
  }
  public function orders(Request $r) {
    $q = DB::table('orders as o')->leftJoin('stores as s', 's.id', '=', 'o.store_id')->select('o.*', 's.name as store_name');
    if ($st = $r->query('status')) { if ($st === 'Pending') $q->whereIn('o.status', ['Pending', 'New', 'WFP']); elseif ($st === 'Returned') $q->whereIn('o.status', ['Returned', 'Return Pending']); else $q->where('o.status', $st); }
    if ($ap = $r->query('approval')) $q->where('o.approval_source', $ap === 'ai' ? 'AI' : 'MANUAL');
    if ($sid = (int)$r->query('store')) $q->where('o.store_id', $sid);
    if ($s = trim((string)$r->query('q'))) { $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'; $q->where(fn($w) => $w->where('o.invoice', 'ilike', $like)->orWhere('o.phone', 'ilike', $like)->orWhere('o.customer_name', 'ilike', $like)->orWhere('o.consignment_id', 'ilike', $like)); }
    if ($cur = (int)$r->query('cursor')) $q->where('o.id', '<', $cur);
    $rows = $q->orderByDesc('o.id')->limit(31)->get(); $more = $rows->count() > 30; $rows = $rows->take(30);
    return response()->json(['orders'=>$rows->map(fn($o) => $this->orderCard($o)), 'next_cursor'=>$more ? $rows->last()->id : null]);
  }
  public function order(Request $r, $id) {
    $o = DB::table('orders as o')->leftJoin('stores as s', 's.id', '=', 'o.store_id')->where('o.id', $id)->select('o.*', 's.name as store_name')->first(); abort_unless($o, 404);
    $items = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $id)->get(['i.name', 'i.qty', 'i.price', 'p.image', 'p.sku']);
    $logs = DB::table('order_status_logs')->where('order_id', $id)->orderBy('id')->get();
    $canCost = MP::can(Auth::user(), 'costs', 'view');
    $card = $this->orderCard($o) + ['address'=>$o->address, 'district'=>$o->district, 'shipping'=>(float)$o->shipping, 'discount'=>(float)($o->discount ?? 0), 'note'=>$o->note,
      'approved_by'=>$o->approved_by ?? null, 'approved_at'=>$this->dt($o->approved_at ?? null), 'courier_status'=>$o->courier_status ?? null];
    $card['items'] = $items->map(fn($i) => ['name'=>$i->name, 'qty'=>(int)$i->qty, 'price'=>(float)$i->price, 'sku'=>$i->sku, 'image'=>$i->image]);
    $card['timeline'] = $logs->map(fn($l) => ['at'=>$this->dt($l->created_at), 'by'=>$l->user_name, 'from'=>$l->from_status, 'to'=>$l->to_status, 'note'=>$l->note]);
    $card['next_statuses'] = \App\Models\Mother::STAGES;
    $card['customer_stats'] = $this->custStats($o->phone);
    if (!$canCost) unset($card['profit']);
    $this->audit($r, 'order.open', \App\Support\Inv::show($o->invoice));
    return response()->json(['order'=>$card]);
  }

  // ---- customers ----
  private function custStats(string $phone): array {
    $s = DB::table('orders')->where('phone', $phone)->selectRaw("count(*) total, sum(case when status='Delivered' then 1 else 0 end) delivered, sum(case when status in ('Returned','Return Pending') then 1 else 0 end) returned, sum(case when status='Cancel' then 1 else 0 end) cancelled, coalesce(sum(case when status='Delivered' then total else 0 end),0) spent, max(created_at) last_order")->first();
    $t = (int)$s->total; $d = (int)$s->delivered;
    return ['total_orders'=>$t, 'delivered'=>$d, 'returned'=>(int)$s->returned, 'cancelled'=>(int)$s->cancelled, 'success_rate'=>$t ? round($d * 100 / $t) : null, 'spent'=>(float)$s->spent, 'last_order'=>$this->dt($s->last_order)];
  }
  public function customers(Request $r) {
    $q = DB::table('customers');
    if ($s = trim((string)$r->query('q'))) { $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%'; $q->where(fn($w) => $w->where('name', 'ilike', $like)->orWhere('phone', 'ilike', $like)); }
    if ($cur = (int)$r->query('cursor')) $q->where('id', '<', $cur);
    $rows = $q->orderByDesc('id')->limit(31)->get(); $more = $rows->count() > 30; $rows = $rows->take(30);
    return response()->json(['customers'=>$rows->map(fn($c) => ['id'=>$c->id, 'name'=>$c->name, 'phone'=>$c->phone, 'membership'=>$c->membership, 'blocked'=>(bool)$c->blocked]), 'next_cursor'=>$more ? $rows->last()->id : null]);
  }
  public function customer(Request $r, $id) {
    $c = DB::table('customers')->find($id); abort_unless($c, 404);
    $orders = DB::table('orders')->where('phone', $c->phone)->orderByDesc('id')->limit(10)->get(['id', 'invoice', 'total', 'status', 'created_at']);
    return response()->json(['customer'=>['id'=>$c->id, 'name'=>$c->name, 'phone'=>$c->phone, 'email'=>$c->email, 'membership'=>$c->membership, 'blocked'=>(bool)$c->blocked, 'stats'=>$this->custStats($c->phone),
      'recent_orders'=>$orders->map(fn($o) => ['id'=>$o->id, 'invoice'=>\App\Support\Inv::show($o->invoice), 'total'=>(float)$o->total, 'status'=>$o->status, 'created_at'=>$this->dt($o->created_at)])]]);
  }

  // ---- tasks ----
  private function taskJson($t): array {
    return ['id'=>$t->id, 'title'=>$t->title, 'description'=>$t->description, 'priority'=>$t->priority, 'status'=>$t->status, 'department'=>$t->department, 'due_at'=>$this->dt($t->due_at), 'assigned_to'=>$t->assigned_to, 'assignee'=>$t->assignee ?? null, 'order_id'=>$t->order_id, 'customer_id'=>$t->customer_id, 'thread_id'=>$t->thread_id, 'created_at'=>$this->dt($t->created_at)];
  }
  public function tasks(Request $r) {
    $q = DB::table('staff_tasks as t')->leftJoin('users as u', 'u.id', '=', 't.assigned_to')->select('t.*', 'u.name as assignee');
    $scope = $r->query('scope', 'mine');
    if ($scope === 'mine') $q->where('t.assigned_to', Auth::id()); elseif ($scope === 'created') $q->where('t.created_by', Auth::id());
    elseif (!in_array(Auth::user()->role, ['super', 'admin', 'manager'])) $q->where(fn($w) => $w->where('t.assigned_to', Auth::id())->orWhere('t.created_by', Auth::id()));
    if ($st = $r->query('status')) $q->where('t.status', $st); else $q->whereIn('t.status', ['open', 'in_progress', 'waiting']);
    return response()->json(['tasks'=>$q->orderByRaw("case t.priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->orderBy('t.due_at')->limit(100)->get()->map(fn($t) => $this->taskJson($t))]);
  }
  public function taskCreate(Request $r) {
    $d = $r->validate(['title'=>'required|string|max:200', 'description'=>'nullable|string|max:2000', 'priority'=>'nullable|in:low,normal,high,urgent', 'assigned_to'=>'nullable|integer|exists:users,id', 'department'=>'nullable|string|max:40', 'due_at'=>'nullable|date', 'order_id'=>'nullable|integer', 'customer_id'=>'nullable|integer', 'thread_id'=>'nullable|integer']);
    $id = DB::table('staff_tasks')->insertGetId($d + ['priority'=>$d['priority'] ?? 'normal', 'assigned_to'=>$d['assigned_to'] ?? Auth::id(), 'created_by'=>Auth::id(), 'status'=>'open', 'created_at'=>now(), 'updated_at'=>now()]);
    $this->audit($r, 'task.create', '#'.$id.' '.$d['title']);
    return response()->json(['task'=>$this->taskJson(DB::table('staff_tasks')->find($id))], 201);
  }
  public function taskUpdate(Request $r, $id) {
    $t = DB::table('staff_tasks')->find($id); abort_unless($t, 404);
    abort_unless(in_array(Auth::user()->role, ['super', 'admin', 'manager']) || in_array(Auth::id(), [$t->assigned_to, $t->created_by]), 403);
    $d = $r->validate(['status'=>'nullable|in:open,in_progress,waiting,done,cancelled', 'priority'=>'nullable|in:low,normal,high,urgent', 'assigned_to'=>'nullable|integer|exists:users,id', 'due_at'=>'nullable|date', 'description'=>'nullable|string|max:2000']);
    $upd = array_filter($d, fn($v) => $v !== null); if (($d['status'] ?? null) === 'done') $upd['done_at'] = now();
    if ($upd) DB::table('staff_tasks')->where('id', $id)->update($upd + ['updated_at'=>now()]);
    $this->audit($r, 'task.update', '#'.$id.' '.json_encode($upd));
    return response()->json(['task'=>$this->taskJson(DB::table('staff_tasks')->find($id))]);
  }
}
