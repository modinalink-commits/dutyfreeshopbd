<?php
namespace App\Http\Controllers;

use App\Support\Procure as P;
use App\Support\SupplierPortal as SP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Supplier-facing portal. Every query is scoped to the logged-in supplier (SP::sid()); nothing here can read another supplier's rows. */
class SupplierPortalController extends Controller
{
  private function need(string $area): void { if (!SP::can($area)) abort(403, 'Your portal role does not include this page.'); }
  private function v(string $view, array $d = []) { return view('sp.'.$view, $d + ['u'=>SP::user(), 'brand'=>SP::brand()]); }

  // ---------- auth ----------
  public function loginForm() { if (SP::user()) return redirect('/supplier-portal'); return view('sp.login', ['brand'=>SP::brand()]); }
  public function login(Request $r) {
    $ident = trim((string)$r->input('login')); $u = SP::find($ident);
    if ($u && $u->locked_until && strtotime((string)$u->locked_until) > time()) { SP::log($r, $u, 'locked', $ident); return back()->withInput($r->only('login'))->with('bad','Too many failed attempts. Try again in a few minutes or use Forgot password.'); }
    if (!$u || $u->status !== 'active' || !Hash::check((string)$r->input('password'), $u->password)) {
      if ($u) { $n = $u->failed_attempts + 1; DB::table('supplier_users')->where('id',$u->id)->update(['failed_attempts'=>$n,'locked_until'=>$n >= 5 ? now()->addMinutes(15) : null]); }
      SP::log($r, $u, 'failed', $ident);
      return back()->withInput($r->only('login'))->with('bad', $u && $u->status !== 'active' ? 'This account is not active. Please contact DutyFreeBD.' : 'Wrong login or password.');
    }
    if ($u->twofa && $u->mobile) { SP::otpSend('login', $u); session(['sp_2fa'=>$u->id,'sp_2fa_t'=>time()]); SP::log($r, $u, '2fa-sent', $ident); return redirect('/supplier-portal/verify')->with('ok','Enter the OTP sent to your registered mobile.'); }
    return $this->finish($r, $u, $ident);
  }
  private function finish(Request $r, object $u, string $ident) {
    DB::table('supplier_users')->where('id',$u->id)->update(['failed_attempts'=>0,'locked_until'=>null,'last_login_at'=>now(),'last_login_ip'=>$r->ip()]);
    $r->session()->regenerate(); session(['sp_uid'=>$u->id,'sp_iat'=>time()]); session()->forget(['sp_2fa','sp_2fa_t']);
    SP::log($r, $u, 'login', $ident); DB::table('supplier_activity')->insert(['supplier_id'=>$u->supplier_id,'supplier_user_id'=>$u->id,'action'=>'Login','ip'=>$r->ip(),'created_at'=>now(),'updated_at'=>now()]);
    return redirect('/supplier-portal');
  }
  /** Passwordless sign-in: OTP to the registered mobile. */
  public function otpLogin(Request $r) {
    if ($r->input('step') === 'send') {
      $u = SP::find((string)$r->input('login'));
      if ($u && $u->status === 'active' && $u->mobile) { SP::otpSend('login', $u); session(['sp_2fa'=>$u->id,'sp_2fa_t'=>time(),'sp_otp_only'=>1]); SP::log($r, $u, 'otp-sent', $r->input('login')); }
      return redirect('/supplier-portal/verify')->with('ok','If the account exists, an OTP was sent to its registered mobile.');
    }
    return back();
  }
  public function verifyForm() { if (!session('sp_2fa')) return redirect('/supplier-portal/login'); return view('sp.verify', ['brand'=>SP::brand()]); }
  public function verify(Request $r) {
    $uid = (int)session('sp_2fa'); if (!$uid || time() - (int)session('sp_2fa_t') > 600) return redirect('/supplier-portal/login')->with('bad','Session expired. Please sign in again.');
    $u = DB::table('supplier_users as u')->join('suppliers as s','s.id','=','u.supplier_id')->where('u.id',$uid)->first(['u.*']);
    if (!$u || $u->status !== 'active' || !SP::otpCheck('login', $uid, (string)$r->input('otp'))) { SP::log($r, $u, 'failed', 'otp'); return back()->with('bad','OTP is wrong or expired.'); }
    return $this->finish($r, $u, 'otp');
  }
  public function twofa(Request $r) {
    $u = SP::user(); if (!Hash::check((string)$r->input('current'), $u->password)) return back()->with('bad','Password is wrong.');
    if (!$u->mobile) return back()->with('bad','Add a mobile number to your login first (ask DutyFreeBD).');
    DB::table('supplier_users')->where('id',$u->id)->update(['twofa'=>$r->boolean('on'),'updated_at'=>now()]); SP::act('2FA '.($r->boolean('on') ? 'enabled' : 'disabled'));
    return back()->with('ok', $r->boolean('on') ? 'Two-step sign-in is ON.' : 'Two-step sign-in is OFF.');
  }


  // ---------- activation by single-use SMS link ----------
  private function invite(string $token): ?object {
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) return null;
    return DB::table('supplier_users as u')->join('suppliers as s','s.id','=','u.supplier_id')->where('u.invite_hash', hash('sha256',$token))->where('u.invite_expires_at','>',now())->where('u.status','active')->first(['u.*','s.name as supplier_name']);
  }
  public function activateForm($token) {
    $u = $this->invite($token); if (!$u) return response(view('sp.activate', ['brand'=>SP::brand(),'bad'=>true,'token'=>$token,'u'=>null]), 410);
    return view('sp.activate', ['brand'=>SP::brand(),'bad'=>false,'token'=>$token,'u'=>$u]);
  }
  public function activateOtp($token) {
    $u = $this->invite($token); if (!$u) abort(410);
    $ok = SP::otpSend('activate', $u); return back()->with($ok ? 'ok' : 'bad', $ok ? 'OTP sent to '.substr($u->mobile,0,3).'****'.substr($u->mobile,-3).'.' : 'Could not send OTP. Please contact DutyFreeBD.');
  }
  public function activate(Request $r, $token) {
    $u = $this->invite($token); if (!$u) abort(410); $pw = (string)$r->input('password');
    if (strlen($pw) < 8 || !preg_match('/[A-Z]/',$pw) || !preg_match('/[a-z]/',$pw) || !preg_match('/\d/',$pw)) return back()->with('bad','Password needs 8+ characters with an uppercase letter, a lowercase letter and a number.');
    if ($pw !== (string)$r->input('password2')) return back()->with('bad','Passwords do not match.');
    if (!SP::otpCheck('activate', $u->id, (string)$r->input('otp'))) { SP::log($r, $u, 'failed', 'activate-otp'); return back()->with('bad','OTP is wrong or expired. Press "Send OTP" again.'); }
    DB::table('supplier_users')->where('id',$u->id)->update(['password'=>Hash::make($pw),'must_change'=>false,'activated_at'=>now(),'password_changed_at'=>now(),'invite_hash'=>null,'invite_expires_at'=>null,'failed_attempts'=>0,'locked_until'=>null,'updated_at'=>now()]);
    SP::log($r, $u, 'activated'); return $this->finish($r, DB::table('supplier_users')->find($u->id), 'activation');
  }
  public function logout(Request $r) { $u = SP::user(); if ($u) SP::log($r, $u, 'logout'); session()->forget(['sp_uid','sp_iat']); return redirect('/supplier-portal/login'); }

  public function forgotForm() { return view('sp.forgot', ['brand'=>SP::brand(), 'step'=>session('sp_fstep', 1)]); }
  public function forgot(Request $r) {
    if ($r->input('step') === 'send') {
      $u = SP::find((string)$r->input('login'));
      if ($u && $u->status === 'active') { SP::otpSend('reset', $u); SP::log($r, $u, 'reset-otp'); session(['sp_fuid'=>$u->id]); }
      return redirect('/supplier-portal/forgot')->with('sp_fstep', 2)->with('ok','If the account exists, an OTP was sent to its registered mobile.');
    }
    $uid = (int)session('sp_fuid'); $pw = (string)$r->input('password');
    if (!$uid || strlen($pw) < 8 || $pw !== (string)$r->input('password2')) return back()->with('bad','Password must be 8+ characters and match.')->with('sp_fstep',2);
    if (!SP::otpCheck('reset', $uid, (string)$r->input('otp'))) return back()->with('bad','OTP is wrong or expired.')->with('sp_fstep',2);
    $u = DB::table('supplier_users')->find($uid);
    DB::table('supplier_users')->where('id',$uid)->update(['password'=>Hash::make($pw),'must_change'=>false,'failed_attempts'=>0,'locked_until'=>null,'force_logout_at'=>now(),'updated_at'=>now()]);
    SP::log($r, $u, 'password-reset'); session()->forget('sp_fuid');
    return redirect('/supplier-portal/login')->with('ok','Password changed. Please sign in.');
  }
  public function passwordForm() { return $this->v('password'); }
  public function password(Request $r) {
    $u = SP::user(); $pw = (string)$r->input('password');
    if (!Hash::check((string)$r->input('current'), $u->password)) return back()->with('bad','Current password is wrong.');
    if (strlen($pw) < 8 || $pw !== (string)$r->input('password2')) return back()->with('bad','New password must be 8+ characters and match.');
    DB::table('supplier_users')->where('id',$u->id)->update(['password'=>Hash::make($pw),'must_change'=>false,'password_changed_at'=>now(),'updated_at'=>now()]);
    SP::log($r, $u, 'password-change'); return redirect('/supplier-portal')->with('ok','Password updated.');
  }
  public function termsForm() { return $this->v('terms'); }
  public function terms(Request $r) {
    if (!$r->boolean('agree')) return back()->with('bad','Please tick "I Agree" to continue.');
    DB::table('supplier_users')->where('id',SP::user()->id)->update(['terms_at'=>now(),'terms_ver'=>SP::TERMS,'updated_at'=>now()]); SP::act('Accepted portal terms', SP::TERMS);
    return redirect('/supplier-portal');
  }

  // ---------- dashboard ----------
  public function home() {
    $sid = SP::sid(); $k = SP::kpis($sid);
    $act = [];
    if ($k['pending_rfq']) $act[] = ['Submit Price', $k['pending_rfq'], '/supplier-portal/rfq', 'urgent'];
    if ($k['need_accept']) $act[] = ['Accept PO', $k['need_accept'], '/supplier-portal/pos?s=sent', 'urgent'];
    $due = DB::table('supplier_pos')->where('supplier_id',$sid)->whereIn('status',['accepted','revised'])->whereNotNull('delivery_date')->where('delivery_date','<=',now('Asia/Dhaka')->addDays(3)->toDateString())->count();
    if ($due) $act[] = ['Pending Delivery', $due, '/supplier-portal/pos', 'action'];
    $pay = DB::table('supplier_payments')->where('supplier_id',$sid)->whereIn('status',['paid'])->where('pay_date','>=',now('Asia/Dhaka')->subDays(7)->toDateString())->count();
    if ($pay) $act[] = ['Payment Update', $pay, '/supplier-portal/payments', 'info'];
    $recent = DB::table('supplier_notifications')->where('supplier_id',$sid)->orderByDesc('id')->limit(6)->get();
    return $this->v('home', compact('k','act','recent'));
  }
  public function summary() { $k = SP::kpis(SP::sid()); return response()->json(['unread'=>$k['unread'],'outstanding'=>$k['outstanding']]); }

  // ---------- purchase orders ----------
  public function pos(Request $r) {
    $this->need('po'); $s = $r->query('s');
    $q = DB::table('supplier_pos')->where('supplier_id',SP::sid())->where('status','!=','draft')->orderByDesc('id');
    if ($s) $q->where('status',$s);
    if ($r->filled('q')) $q->where('po_no','ilike','%'.trim((string)$r->query('q')).'%');
    return $this->v('pos', ['rows'=>$q->paginate(20)->withQueryString(),'s'=>$s]);
  }
  private function po(int $id): object { $p = DB::table('supplier_pos')->where('id',$id)->where('supplier_id',SP::sid())->where('status','!=','draft')->first(); if (!$p) abort(404); return $p; }
  public function poShow($id) {
    $this->need('po'); $p = $this->po((int)$id); SP::act('PO viewed', $p->po_no);
    return $this->v('po_show', ['p'=>$p,'items'=>DB::table('supplier_po_items')->where('po_id',$p->id)->orderBy('id')->get(),'reasons'=>SP::REJECT]);
  }
  public function poRespond(Request $r, $id) {
    $this->need('po'); $p = $this->po((int)$id); if ($p->status !== 'sent') return back()->with('bad','This PO was already answered.');
    $a = $r->input('do'); $items = DB::table('supplier_po_items')->where('po_id',$p->id)->get(); $note = mb_substr((string)$r->input('note'),0,500) ?: null;
    if ($a === 'accept') {
      DB::transaction(function () use ($p, $note) { DB::table('supplier_po_items')->where('po_id',$p->id)->update(['accepted_qty'=>DB::raw('qty')]); DB::table('supplier_pos')->where('id',$p->id)->update(['status'=>'accepted','response_note'=>$note,'responded_at'=>now(),'updated_at'=>now()]); });
      SP::act('PO accepted', $p->po_no); $m = 'PO accepted.';
    } elseif ($a === 'partial') {
      $q = (array)$r->input('acc'); $any = false; $full = true; $set = [];
      foreach ($items as $it) { $n = max(0, min((int)$it->qty, (int)($q[$it->id] ?? 0))); $set[$it->id] = $n; if ($n > 0) $any = true; if ($n < $it->qty) $full = false; }
      if (!$any) return back()->with('bad','Enter an available quantity for at least one item, or reject the PO.');
      DB::transaction(function () use ($p, $set, $full, $note) { foreach ($set as $iid=>$n) DB::table('supplier_po_items')->where('id',$iid)->where('po_id',$p->id)->update(['accepted_qty'=>$n]); DB::table('supplier_pos')->where('id',$p->id)->update(['status'=>$full ? 'accepted' : 'partial','response_note'=>$note,'responded_at'=>now(),'updated_at'=>now()]); });
      SP::act('PO partially accepted', $p->po_no, $set); $m = $full ? 'PO accepted.' : 'Partial acceptance sent. DutyFreeBD will approve the revised quantity.';
    } elseif ($a === 'reject') {
      $reason = (string)$r->input('reason'); if (!in_array($reason, SP::REJECT, true)) return back()->with('bad','Please choose a rejection reason.');
      DB::table('supplier_pos')->where('id',$p->id)->update(['status'=>'rejected','reject_reason'=>$reason,'response_note'=>$note,'responded_at'=>now(),'updated_at'=>now()]);
      SP::act('PO rejected', $p->po_no, ['reason'=>$reason]); $m = 'PO rejected.';
    } else abort(422);
    return redirect('/supplier-portal/pos/'.$p->id)->with('ok',$m);
  }

  // ---------- RFQ ----------
  /** Reorder Requests = RFQs created by Auto Reorder (low stock). */
  public function reorders() {
    $this->need('rfq'); $sid = SP::sid();
    $rows = DB::table('supplier_rfq_invites as i')->join('supplier_rfqs as r','r.id','=','i.rfq_id')->join('reorder_requests as o','o.id','=','r.reorder_id')->leftJoin('products as p','p.id','=','r.product_id')->leftJoin('supplier_quotes as q', fn($j) => $j->on('q.rfq_id','=','r.id')->where('q.supplier_id','=',$sid))
      ->where('i.supplier_id',$sid)->where('r.status','!=','draft')->orderByRaw('coalesce(p.stock,0)')->orderBy('r.id')->limit(1000)
      ->selectRaw("(select pi.cost from purchase_items pi join purchases pu on pu.id = pi.purchase_id where pi.product_id = r.product_id and pu.supplier_id = ? order by pu.purchase_date desc, pi.id desc limit 1) as last_cost, (select pu.purchase_date from purchase_items pi join purchases pu on pu.id = pi.purchase_id where pi.product_id = r.product_id and pu.supplier_id = ? order by pu.purchase_date desc, pi.id desc limit 1) as last_date, p.stock as cur_stock, p.published as pub, o.threshold as thr", [$sid, $sid])
      ->get(['r.id','r.rfq_no','r.product','r.sku','r.qty','r.required_date','r.expires_at','r.status as rstatus','i.status as istatus','i.decline_reason','o.urgency','o.kind','o.stock_at','p.image','q.unit_price']);
    $contact = preg_replace('/[^0-9+]/', '', (string)\App\Support\Procure::set('reorder_contact', ''));
    return $this->v('reorder_list', compact('rows', 'contact'));
  }
  public function rfqs() {
    $this->need('rfq'); $sid = SP::sid();
    $rows = DB::table('supplier_rfq_invites as i')->join('supplier_rfqs as r','r.id','=','i.rfq_id')->leftJoin('products as pp','pp.id','=','r.product_id')->leftJoin('supplier_quotes as q', fn($j) => $j->on('q.rfq_id','=','r.id')->where('q.supplier_id','=',$sid))
      ->where('i.supplier_id',$sid)->where('r.status','!=','draft')->orderByDesc('r.id')->limit(100)
      ->get(['pp.image','r.id','r.rfq_no','r.product','r.sku','r.qty','r.required_date','r.expires_at','r.status as rstatus','i.status as istatus','q.unit_price','q.updated_at as qat']);
    return $this->v('rfqs', compact('rows'));
  }
  private function rfq(int $id): array {
    $sid = SP::sid(); $i = DB::table('supplier_rfq_invites')->where('rfq_id',$id)->where('supplier_id',$sid)->first(); if (!$i) abort(404);
    $r = DB::table('supplier_rfqs')->where('id',$id)->where('status','!=','draft')->first(); if (!$r) abort(404);
    return [$r, $i, DB::table('supplier_quotes')->where('rfq_id',$id)->where('supplier_id',$sid)->first()];
  }
  public function rfqShow($id) {
    $this->need('rfq'); [$rfq,$inv,$q] = $this->rfq((int)$id);
    if (!$inv->viewed_at) DB::table('supplier_rfq_invites')->where('id',$inv->id)->update(['viewed_at'=>now()]);
    $open = $rfq->status === 'sent' && (!$rfq->expires_at || strtotime((string)$rfq->expires_at) > time());
    return $this->v('rfq_show', compact('rfq','inv','q','open'));
  }
  public function rfqQuote(Request $r, $id) {
    $this->need('rfq'); [$rfq,$inv,$old] = $this->rfq((int)$id);
    if ($rfq->status !== 'sent' || ($rfq->expires_at && strtotime((string)$rfq->expires_at) <= time())) return back()->with('bad','This price request is closed.');
    $d = $r->validate(['unit_price'=>'required|numeric|min:0.01|max:99999999','available_qty'=>'required|integer|min:0|max:99999999','lead_days'=>'required|integer|min:0|max:365','valid_until'=>'nullable|date|after_or_equal:today','moq'=>'nullable|integer|min:0','delivery_charge'=>'nullable|numeric|min:0','note'=>'nullable|string|max:500']);
    $row = ['unit_price'=>$d['unit_price'],'available_qty'=>$d['available_qty'],'lead_days'=>$d['lead_days'],'valid_until'=>$d['valid_until'] ?? null,'moq'=>(int)($d['moq'] ?? 0),'delivery_charge'=>$d['delivery_charge'] ?? 0,'note'=>$d['note'] ?? null,'updated_at'=>now()];
    DB::table('supplier_quotes')->updateOrInsert(['rfq_id'=>$rfq->id,'supplier_id'=>SP::sid()], $row + ['created_at'=>$old->created_at ?? now()]);
    DB::table('supplier_rfq_invites')->where('id',$inv->id)->update(['status'=>'quoted','updated_at'=>now()]);
    SP::act('RFQ submitted', $rfq->rfq_no, ['rate'=>$d['unit_price'],'qty'=>$d['available_qty']]); try { \App\Support\SupplierInsight::priceCheck($rfq, SP::sid(), (float)$d['unit_price']); } catch (\Throwable $e) {}
    return redirect('/supplier-portal/rfq/'.$rfq->id)->with('ok','Quote submitted.');
  }
  public function rfqDecline($id) {
    $this->need('rfq'); [$rfq,$inv] = $this->rfq((int)$id); if ($rfq->status !== 'sent') return back();
    $why = (string)request('reason'); $why = in_array($why, \App\Support\Reorder::DECLINE, true) ? $why : null;
    DB::table('supplier_rfq_invites')->where('id',$inv->id)->update(['status'=>'declined','decline_reason'=>$why,'updated_at'=>now()]); SP::act('RFQ declined', $rfq->rfq_no, $why ? ['reason'=>$why] : null);
    return redirect($rfq->reorder_id ? '/supplier-portal/reorder' : '/supplier-portal/rfq')->with('ok','Marked as not quoting.');
  }

  // ---------- bills / ledger / payments ----------
  public function bills(Request $r) {
    $this->need('bills'); SP::act('Bills viewed'); $rows = SP::bills(SP::sid());
    if ($r->filled('s')) $rows = $rows->where('pay_status', $r->query('s'))->values();
    if ($r->filled('q')) { $q = mb_strtolower(trim((string)$r->query('q'))); $rows = $rows->filter(fn($b) => str_contains(mb_strtolower((string)$b->invoice), $q))->values(); }
    return $this->v('bills', ['rows'=>$rows,'s'=>$r->query('s')]);
  }
  public function ledger(Request $r) {
    $this->need('ledger'); $sid = SP::sid();
    $from = $r->query('from') ?: now('Asia/Dhaka')->startOfMonth()->toDateString(); $to = $r->query('to') ?: now('Asia/Dhaka')->toDateString();
    if ($from > $to) [$from,$to] = [$to,$from];
    $L = P::events($sid, $from, $to); $fmt = $r->query('export');
    if ($fmt) {
      SP::act('Ledger downloaded', "$from..$to", ['fmt'=>$fmt]);
      $head = ['Date','Reference','Description','Debit','Credit','Balance']; $name = 'ledger-'.$from.'_'.$to;
      $rows = [['','','Opening balance','','',number_format($L['opening'],2,'.','')]];
      foreach ($L['rows'] as $e) $rows[] = [$e['d'],$e['ref'],$e['t'],$e['debit'] ?: '',$e['credit'] ?: '',number_format($e['bal'],2,'.','')];
      $rows[] = ['','','Closing balance',number_format($L['tot']['debit'],2,'.',''),number_format($L['tot']['credit'],2,'.',''),number_format($L['closing'],2,'.','')];
      if ($fmt === 'csv') return response()->streamDownload(function () use ($head, $rows) { $o = fopen('php://output','w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, $head); foreach ($rows as $x) fputcsv($o, $x); fclose($o); }, "$name.csv", ['Content-Type'=>'text/csv; charset=utf-8']);
      if ($fmt === 'xls') return response()->streamDownload(function () use ($head, $rows) { echo '<html><head><meta charset="utf-8"></head><body><table border="1"><tr>'; foreach ($head as $h) echo '<th style="background:#FF5A00;color:#fff">'.e($h).'</th>'; echo '</tr>'; foreach ($rows as $x) { echo '<tr>'; foreach ($x as $v) echo '<td>'.e((string)$v).'</td>'; echo '</tr>'; } echo '</table></body></html>'; }, "$name.xls", ['Content-Type'=>'application/vnd.ms-excel; charset=utf-8']);
      return response(view('sp.ledger_print', ['u'=>SP::user(),'L'=>$L,'from'=>$from,'to'=>$to,'s'=>$L['supplier'],'brand'=>SP::brand()]));
    }
    SP::act('Ledger viewed', "$from..$to");
    return $this->v('ledger', compact('L','from','to'));
  }
  public function payments() {
    $this->need('payments'); $sid = SP::sid();
    $rows = DB::table('supplier_payments as p')->where('p.supplier_id',$sid)->orderByDesc('p.pay_date')->orderByDesc('p.id')->limit(300)->get();
    $inv = DB::table('payment_allocations as a')->join('purchases as pu','pu.id','=','a.purchase_id')->whereIn('a.payment_id',$rows->pluck('id'))->get(['a.payment_id','pu.invoice'])->groupBy('payment_id');
    $rows->each(function ($p) use ($inv) { $p->bills = isset($inv[$p->id]) ? $inv[$p->id]->pluck('invoice')->implode(', ') : ''; });
    return $this->v('payments', compact('rows'));
  }

  // ---------- notifications ----------
  public function notifications() {
    $rows = DB::table('supplier_notifications')->where('supplier_id',SP::sid())
      ->orderByRaw('(read_at is null) desc')->orderByRaw("case priority when 'urgent' then 0 when 'action' then 1 else 2 end")->orderByDesc('id')->limit(100)->get();
    return $this->v('notifications', compact('rows'));
  }
  public function notificationsRead(Request $r) {
    $q = DB::table('supplier_notifications')->where('supplier_id',SP::sid())->whereNull('read_at'); if ($r->filled('id')) $q->where('id',(int)$r->input('id'));
    $q->update(['read_at'=>now(),'updated_at'=>now()]);
    if ($r->filled('go') && str_starts_with((string)$r->input('go'),'/supplier-portal/')) return redirect((string)$r->input('go'));
    return back();
  }

  // ---------- profile ----------
  const SOFT = ['contact_person','address'];
  const SENSITIVE = ['business_name','phone','email','tax_bin','bank_name','bank_account_name','bank_account_no','bank_branch','bank_routing','payment_pref'];
  public function profile() {
    $sid = SP::sid(); $s = DB::table('suppliers')->find($sid);
    $pending = DB::table('supplier_profile_changes')->where('supplier_id',$sid)->orderByDesc('id')->limit(5)->get();
    return $this->v('profile', ['s'=>$s,'pending'=>$pending,'logins'=>DB::table('supplier_login_logs')->where('supplier_user_id',SP::user()->id)->orderByDesc('id')->limit(8)->get(['event','ip','device','created_at'])]);
  }
  public function profileOtp(Request $r) { $this->need('profile_edit'); $ok = SP::otpSend('profile', SP::user()); return back()->with($ok ? 'ok' : 'bad', $ok ? 'OTP sent to your registered mobile.' : 'Could not send OTP. No mobile number on this login.'); }
  public function profileSave(Request $r) {
    $this->need('profile_edit'); $sid = SP::sid(); $s = DB::table('suppliers')->find($sid); $soft = []; $sens = [];
    foreach (self::SOFT as $f) { $v = trim((string)$r->input($f)); if ($v !== (string)($s->$f ?? '')) $soft[$f] = mb_substr($v,0,300); }
    foreach (self::SENSITIVE as $f) { $v = trim((string)$r->input($f)); if ($v !== (string)($s->$f ?? '')) $sens[$f] = mb_substr($v,0,200); }
    if ($soft) { DB::table('suppliers')->where('id',$sid)->update($soft + ['updated_at'=>now()]); SP::act('Profile updated', null, $soft); }
    if ($sens) {
      if (!SP::otpCheck('profile', SP::user()->id, (string)$r->input('otp'))) return back()->withInput()->with('bad','Sensitive changes need a valid OTP. Press "Send OTP" first.');
      $old = []; foreach ($sens as $f=>$_) $old[$f] = $s->$f ?? null;
      DB::table('supplier_profile_changes')->insert(['supplier_id'=>$sid,'supplier_user_id'=>SP::user()->id,'changes'=>json_encode(['old'=>$old,'new'=>$sens], JSON_UNESCAPED_UNICODE),'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
      SP::act(isset($sens['bank_account_no']) || isset($sens['bank_name']) ? 'Bank change requested' : 'Profile change requested', null, array_keys($sens));
      return back()->with('ok','Change request sent for admin verification. Current details stay active until approved.');
    }
    return back()->with('ok', $soft ? 'Profile updated.' : 'Nothing changed.');
  }

  // ---------- global search ----------
  public function search(Request $r) {
    $q = trim((string)$r->query('q')); $sid = SP::sid(); $out = [];
    if (mb_strlen($q) >= 2) {
      $l = '%'.$q.'%';
      if (SP::can('po')) foreach (DB::table('supplier_pos')->where('supplier_id',$sid)->where('status','!=','draft')->where('po_no','ilike',$l)->limit(8)->get(['id','po_no','status']) as $x) $out[] = ['PO',$x->po_no,$x->status,'/supplier-portal/pos/'.$x->id];
      if (SP::can('po')) foreach (DB::table('supplier_po_items as i')->join('supplier_pos as p','p.id','=','i.po_id')->where('p.supplier_id',$sid)->where('p.status','!=','draft')->where(fn($w)=>$w->where('i.name','ilike',$l)->orWhere('i.sku','ilike',$l))->limit(6)->get(['p.id','p.po_no','i.name']) as $x) $out[] = ['Product',$x->name,$x->po_no,'/supplier-portal/pos/'.$x->id];
      if (SP::can('bills')) foreach (DB::table('purchases')->where('supplier_id',$sid)->whereNotIn('status',P::BAD)->where('invoice','ilike',$l)->limit(8)->get(['invoice','total']) as $x) $out[] = ['Bill',$x->invoice,SP::tk($x->total),'/supplier-portal/bills?q='.urlencode($x->invoice)];
      if (SP::can('payments')) foreach (DB::table('supplier_payments')->where('supplier_id',$sid)->where(fn($w)=>$w->where('code','ilike',$l)->orWhere('reference','ilike',$l)->orWhere('txn_id','ilike',$l))->limit(8)->get(['code','amount']) as $x) $out[] = ['Payment',$x->code,SP::tk($x->amount),'/supplier-portal/payments'];
      if (SP::can('rfq')) foreach (DB::table('supplier_rfq_invites as i')->join('supplier_rfqs as r','r.id','=','i.rfq_id')->where('i.supplier_id',$sid)->where('r.status','!=','draft')->where(fn($w)=>$w->where('r.rfq_no','ilike',$l)->orWhere('r.product','ilike',$l)->orWhere('r.sku','ilike',$l))->limit(8)->get(['r.id','r.rfq_no','r.product']) as $x) $out[] = ['RFQ',$x->rfq_no,$x->product,'/supplier-portal/rfq/'.$x->id];
    }
    return $this->v('search', compact('q','out'));
  }

  // ---------- PWA ----------
  public function manifest() {
    return response()->json(['name'=>SP::brand(),'short_name'=>'Supplier','start_url'=>'/supplier-portal','scope'=>'/supplier-portal','display'=>'standalone','background_color'=>'#FFFBF8','theme_color'=>'#FF5A00',
      'icons'=>[['src'=>'/android-chrome-192x192.png','sizes'=>'192x192','type'=>'image/png'],['src'=>'/android-chrome-512x512.png','sizes'=>'512x512','type'=>'image/png']]], 200, ['Content-Type'=>'application/manifest+json']);
  }
  public function sw() {
    $js = "const C='sp-shell-v1';self.addEventListener('install',e=>{e.waitUntil(caches.open(C).then(c=>c.add('/supplier-portal/offline')).catch(()=>{}));self.skipWaiting()});self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));self.addEventListener('fetch',e=>{if(e.request.mode==='navigate'){e.respondWith(fetch(e.request).catch(()=>caches.match('/supplier-portal/offline')))}});";
    return response($js, 200, ['Content-Type'=>'application/javascript','Service-Worker-Allowed'=>'/supplier-portal']);
  }
  public function offline() { return response('<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><body style="font-family:system-ui;text-align:center;padding:60px 20px;color:#0B1220"><h2>You are offline</h2><p>Please reconnect to see your latest orders, ledger and payments.</p><button onclick="location.reload()" style="padding:10px 18px;border:0;border-radius:9px;background:#FF5A00;color:#fff;font-weight:600">Retry</button>'); }
}
