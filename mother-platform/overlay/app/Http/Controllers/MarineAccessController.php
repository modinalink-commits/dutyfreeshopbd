<?php
namespace App\Http\Controllers;

use App\Support\MarineAccess as A;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/** Marine Supply Access Management: the three backend subtabs (Shipping Owners, Local Agencies, Vessels) and the account portal at /marine-portal.
 *  Every portal read is limited to the signed-in account's own rows; every staff action is written to the audit log. */
class MarineAccessController extends Controller
{
  // ================= backend =================
  public function index(Request $r, $type = 'owner') {
    if (!isset(A::TYPES[$type])) return redirect('/admin/marine/access/owner');
    if (!A::ready()) return view('marine.access', ['ready' => false, 'type' => $type, 'types' => A::TYPES]);
    $st = in_array($r->query('status'), ['review', 'approved', 'rejected'], true) ? $r->query('status') : 'review'; $kind = A::KIND[$type];
    $b = DB::table('marine_access_requests')->where('kind', $kind); $n = ['review' => (clone $b)->whereIn('status', ['pending', 'docs'])->count(), 'approved' => (clone $b)->where('status', 'approved')->count(), 'rejected' => (clone $b)->where('status', 'rejected')->count()];
    $rows = $b->when($st === 'review', fn($w) => $w->whereIn('status', ['pending', 'docs']), fn($w) => $w->where('status', $st))->orderByDesc('id')->paginate(40)->withQueryString(); $ids = collect($rows->items())->pluck('id')->all();
    $acc = DB::table('marine_accounts')->whereIn('request_id', $ids)->get()->keyBy('request_id'); $docs = DB::table('marine_documents')->whereIn('request_id', $ids)->orderBy('id')->get()->groupBy('request_id');
    $mail = DB::table('marine_invites')->whereIn('request_id', $ids)->where('kind', '!=', 'otp')->orderByDesc('id')->get()->groupBy('request_id');
    $ves = DB::table('marine_account_vessels')->whereIn('account_id', $acc->pluck('id')->all())->orderBy('id')->get()->groupBy('account_id');
    $tabs = []; foreach (A::KIND as $t => $k) $tabs[$t] = DB::table('marine_access_requests')->where('kind', $k)->whereIn('status', ['pending', 'docs'])->count();
    return view('marine.access', ['ready' => true, 'type' => $type, 'types' => A::TYPES, 'tabs' => $tabs, 'st' => $st, 'n' => $n, 'rows' => $rows, 'acc' => $acc, 'docs' => $docs, 'mail' => $mail, 'ves' => $ves, 'vroles' => A::VROLES, 'mailReady' => \App\Support\Mailer::hasPassword(), 'blocked' => \App\Support\Mailer::blockedAt()]);
  }

  public function act(Request $r, $id) {
    if (!A::ready()) return back();
    $q = DB::table('marine_access_requests')->where('id', (int)$id)->first(); if (!$q) return back();
    $type = A::typeOf($q->kind); $back = '/admin/marine/access/' . $type . ($r->input('st') ? '?status=' . urlencode((string)$r->input('st')) : ''); $by = A::staff(); $do = (string)$r->input('action');
    $who = '#' . $q->id . ' (' . A::TYPES[$type] . ': ' . ($q->company ?: $q->name ?: $q->email) . ')'; $audit = fn($a, $s, $p = null, $n = null) => \App\Support\Procure::audit('marine.access.' . $a, $s, $p, $n);
    $acc = DB::table('marine_accounts')->where('request_id', $q->id)->first();
    if ($do === 'docs') {
      $note = trim((string)$r->input('note')); if (mb_strlen($note) < 5) return redirect($back)->with('ok', 'Write what is missing, so the applicant knows what to send.');
      if (!in_array($q->status, ['pending', 'docs'], true)) return redirect($back)->with('ok', 'This application is already decided.');
      [$ok, $err] = A::requestDocuments($q, $note, $by); $audit('documents', 'Documents requested for marine application ' . $who, null, ['note' => $note, 'email_sent' => $ok]);
      return redirect($back)->with('ok', $ok ? 'Documents requested. The applicant has been emailed what is missing.' : 'Saved, but the email was NOT sent: ' . $err);
    }
    if ($do === 'approve') {
      if ($q->status === 'rejected') return redirect($back)->with('ok', 'This application was rejected.');
      if ($acc) return redirect($back)->with('ok', 'An account already exists for this application. Use Resend Invite.');
      DB::table('marine_access_requests')->where('id', $q->id)->update(['status' => 'approved', 'decided_by' => $by, 'decided_at' => now(), 'staff_note' => mb_substr(trim((string)$r->input('note')), 0, 300) ?: $q->staff_note, 'updated_at' => now()]);
      [$ok, $err] = A::approveAndInvite($q, $by); $audit('approved', 'Marine application approved and invite sent ' . $who, $q->status, ['status' => 'approved', 'email_sent' => $ok]);
      try { \Illuminate\Support\Facades\Cache::forget('sb.counts.v3'); } catch (\Throwable $e) {}
      return redirect($back)->with('ok', $ok ? 'Approved. The invitation with the username and the Set Password link was emailed to ' . $q->email . '.' : 'Approved and the account was created, but the invitation email was NOT sent: ' . $err . ' Use Resend Invite after fixing it.');
    }
    if ($do === 'reject') {
      if (!in_array($q->status, ['pending', 'docs'], true)) return redirect($back)->with('ok', 'This application is already decided.');
      DB::table('marine_access_requests')->where('id', $q->id)->update(['status' => 'rejected', 'decided_by' => $by, 'decided_at' => now(), 'staff_note' => mb_substr(trim((string)$r->input('note')), 0, 300) ?: null, 'doc_hash' => null, 'updated_at' => now()]);
      $audit('rejected', 'Marine application rejected ' . $who, $q->status, 'rejected'); try { \Illuminate\Support\Facades\Cache::forget('sb.counts.v3'); } catch (\Throwable $e) {}
      return redirect($back)->with('ok', 'Rejected.');
    }
    if (!$acc) return redirect($back)->with('ok', 'No account exists for this application yet.');
    if ($do === 'resend') {
      if ($acc->status === 'active') return redirect($back)->with('ok', 'This account is already active. The person can use "Forgot password" on the sign-in page.');
      [$ok, $err] = A::invite($acc, $by, 'resend'); $audit('resend', 'Marine invitation resent ' . $who . ' ' . $acc->username, null, ['email_sent' => $ok]);
      return redirect($back)->with('ok', $ok ? 'A new invitation was emailed. The earlier link and code no longer work.' : 'The invitation email was NOT sent: ' . $err);
    }
    if ($do === 'suspend' || $do === 'reactivate') {
      if (!\App\Support\ProfitFloor::admin()) return redirect($back)->with('ok', 'Only an admin can suspend or reactivate an account.');
      $to = $do === 'suspend' ? 'suspended' : ($acc->password ? 'active' : 'invited'); if ($do === 'reactivate' && $acc->status !== 'suspended') return redirect($back);
      DB::table('marine_accounts')->where('id', $acc->id)->update(['status' => $to, 'force_logout_at' => now(), 'invite_hash' => null, 'otp_hash' => null, 'updated_at' => now()]);
      $audit($do, 'Marine account ' . $acc->username . ' ' . ($do === 'suspend' ? 'suspended' : 'reactivated') . ' ' . $who, $acc->status, $to);
      return redirect($back)->with('ok', $do === 'suspend' ? 'Account suspended and signed out everywhere.' : 'Account reactivated.');
    }
    if ($do === 'imo') {
      $imo = preg_replace('/\D/', '', (string)$r->input('imo')); if (strlen($imo) !== 7) return redirect($back)->with('ok', 'An IMO number has 7 digits.');
      DB::table('marine_account_vessels')->insertOrIgnore(['account_id' => $acc->id, 'imo' => $imo, 'name' => mb_substr(trim((string)$r->input('vessel')), 0, 120) ?: null, 'note' => mb_substr(trim((string)$r->input('vnote')), 0, 200) ?: null, 'added_by' => $by, 'created_at' => now(), 'updated_at' => now()]);
      $audit('vessel', 'Vessel IMO ' . $imo . ' linked to marine account ' . $acc->username, null, ['imo' => $imo]);
      return redirect($back)->with('ok', 'Vessel linked.');
    }
    return redirect($back);
  }

  /** Staff download of an application document (private disk; never served by address). */
  public function doc($id) {
    $d = DB::table('marine_documents')->where('id', (int)$id)->first(); if (!$d || !Storage::disk('local')->exists($d->path)) abort(404);
    try { \App\Support\Procure::audit('marine.access.document', 'Marine application document opened: ' . $d->name . ' (application #' . $d->request_id . ')', null, ['id' => (int)$d->id]); } catch (\Throwable $e) {}
    return Storage::disk('local')->download($d->path, $d->name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
  }

  // ================= portal =================
  private function pg(string $view, array $d = [], int $code = 200) { return response(view('mp.' . $view, $d + ['u' => A::user()]), $code)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Frame-Options', 'DENY'); }
  private function sign(Request $r, object $a) {
    DB::table('marine_accounts')->where('id', $a->id)->update(['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now(), 'last_login_ip' => $r->ip()]);
    $r->session()->regenerate(); session(['ma_uid' => $a->id, 'ma_iat' => time()]); return redirect('/marine-portal');
  }

  public function loginForm() { if (!A::ready()) abort(404); return A::user() ? redirect('/marine-portal') : $this->pg('login'); }
  public function login(Request $r) {
    if (!A::ready()) abort(404);
    $ident = mb_substr(trim((string)$r->input('login')), 0, 160); $a = A::find($ident); $bad = 'The username or password is not correct.'; // same answer whether or not the account exists
    if ($a && $a->locked_until && strtotime((string)$a->locked_until) > time()) return back()->withInput($r->only('login'))->with('bad', 'Too many failed attempts. Try again in 15 minutes or use Forgot password.');
    if (!$a || $a->status !== 'active' || !$a->password || !Hash::check((string)$r->input('password'), $a->password)) {
      if ($a) { $n = (int)$a->failed_attempts + 1; DB::table('marine_accounts')->where('id', $a->id)->update(['failed_attempts' => $n >= 5 ? 0 : $n, 'locked_until' => $n >= 5 ? now()->addMinutes(15) : null]); } else Hash::check((string)$r->input('password'), '$2y$12$qjvU0a0ObH7a./H20yrC5eez7SyODl.9f/Rsi4UkWyQRDjfKsngXW'); // similar time for unknown names
      return back()->withInput($r->only('login'))->with('bad', $bad);
    }
    return $this->sign($r, $a);
  }
  public function logout(Request $r) { session()->forget(['ma_uid', 'ma_iat']); $r->session()->regenerate(); return redirect('/marine-portal/login')->with('ok', 'You are signed out.'); }

  public function forgotForm() { if (!A::ready()) abort(404); return $this->pg('forgot'); }
  public function forgot(Request $r) {
    if (!A::ready()) abort(404);
    $a = A::find((string)$r->input('login'));
    if ($a && $a->status === 'active' && !($a->invite_purpose === 'reset' && $a->invite_expires_at && strtotime((string)$a->invite_expires_at) - time() > 6900)) A::invite($a, 'self-service', 'reset'); // at most one reset email in 5 minutes
    return redirect('/marine-portal/forgot')->with('ok', 'If that account exists and is active, we have emailed a link to set a new password.');
  }

  /** Opening the link shows the page and changes nothing. */
  public function activateForm($token) {
    if (!A::ready()) abort(404);
    $a = A::byToken($token); if (!$a) return $this->pg('activate', ['bad' => true, 'token' => '', 'a' => null], 410);
    return $this->pg('activate', ['bad' => false, 'token' => $token, 'a' => $a, 'masked' => A::mask($a->email), 'sent' => (bool)$a->otp_hash && $a->otp_expires_at && strtotime((string)$a->otp_expires_at) > time()]);
  }
  public function activateOtp($token) {
    $a = A::byToken($token); if (!$a) abort(410);
    [$ok, $msg] = A::sendCode($a); return redirect('/marine-portal/activate/' . $token)->with($ok ? 'ok' : 'bad', $msg);
  }
  public function activate(Request $r, $token) {
    $a = A::byToken($token); if (!$a) abort(410);
    $pw = (string)$r->input('password'); $why = A::passwordProblem($pw, (string)$r->input('password2'), $a->username, $a->email); if ($why) return back()->with('bad', $why);
    if (!A::checkCode($a, trim((string)$r->input('code')))) return back()->with('bad', 'The code is wrong or has expired. Press "Send code" for a new one.');
    $was = $a->status;
    DB::table('marine_accounts')->where('id', $a->id)->where('invite_hash', A::th($token))->update(['password' => Hash::make($pw), 'status' => 'active', 'activated_at' => $a->activated_at ?: now(), 'invite_hash' => null, 'invite_expires_at' => null, 'invite_purpose' => null, 'otp_hash' => null, 'otp_expires_at' => null, 'otp_tries' => 0, 'failed_attempts' => 0, 'locked_until' => null, 'force_logout_at' => now(), 'updated_at' => now()]);
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'marine: ' . $a->username, 'action' => 'marine.access.' . ($was === 'active' ? 'password_reset' : 'activated'), 'subject' => 'Marine account ' . $a->username . ($was === 'active' ? ' set a new password' : ' activated by its owner (email code + own password)'), 'meta' => json_encode(['previous' => $was, 'new' => 'active']), 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    sleep(1); // the forced-logout stamp above must be older than the new session
    return $this->sign($r, DB::table('marine_accounts')->where('id', $a->id)->first());
  }

  public function home() {
    $u = A::user(); if (!$u) return redirect('/marine-portal/login');
    return $this->pg('home', ['docs' => DB::table('marine_documents')->where('account_id', $u->id)->orderByDesc('id')->get(), 'ves' => DB::table('marine_account_vessels')->where('account_id', $u->id)->orderBy('id')->get(), 'types' => A::TYPES, 'vroles' => A::VROLES]);
  }
  public function upload(Request $r) {
    $u = A::user(); if (!$u) return redirect('/marine-portal/login'); if (!$u->request_id) return back()->with('bad', 'Documents cannot be added to this account.');
    $files = array_slice(array_filter((array)$r->file('docs', [])), 0, A::DOC_COUNT); if (!$files) return back()->with('bad', 'Choose a file first.');
    $ok = 0; $bad = null; foreach ($files as $f) { $e = A::storeDoc($f, (int)$u->request_id, (int)$u->id, 'account'); if ($e) $bad = $e; else $ok++; }
    return back()->with($ok ? 'ok' : 'bad', $ok ? $ok . ' file(s) added.' . ($bad ? ' One was not accepted: ' . $bad : '') : $bad);
  }
  /** A person opens only the documents of their own account. */
  public function myDoc($id) {
    $u = A::user(); if (!$u) return redirect('/marine-portal/login');
    $d = DB::table('marine_documents')->where('id', (int)$id)->where('account_id', $u->id)->first(); if (!$d || !Storage::disk('local')->exists($d->path)) abort(404);
    return Storage::disk('local')->download($d->path, $d->name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
  }

  // ----- "Request Documents": the applicant adds files through the emailed link, before any account exists -----
  private function docReq(?string $token): ?object { if (!A::tokenOk($token)) return null; return DB::table('marine_access_requests')->where('doc_hash', A::th($token))->where('doc_expires_at', '>', now())->whereIn('status', ['pending', 'docs'])->first(); }
  public function docsForm($token) {
    if (!A::ready()) abort(404);
    $q = $this->docReq($token); if (!$q) return $this->pg('docs', ['bad' => true, 'token' => '', 'q' => null, 'n' => 0], 410);
    return $this->pg('docs', ['bad' => false, 'token' => $token, 'q' => $q, 'n' => DB::table('marine_documents')->where('request_id', $q->id)->count()]);
  }
  public function docsUpload(Request $r, $token) {
    $q = $this->docReq($token); if (!$q) abort(410);
    $files = array_slice(array_filter((array)$r->file('docs', [])), 0, A::DOC_COUNT); if (!$files) return back()->with('bad', 'Choose a file first.');
    $ok = 0; $bad = null; foreach ($files as $f) { $e = A::storeDoc($f, (int)$q->id, null, 'applicant'); if ($e) $bad = $e; else $ok++; }
    if ($ok) DB::table('marine_access_requests')->where('id', $q->id)->update(['status' => 'pending', 'updated_at' => now()]); // back to Under Review for the desk
    return back()->with($ok ? 'ok' : 'bad', $ok ? 'Thank you. ' . $ok . ' file(s) received; our marine desk will review them.' . ($bad ? ' One was not accepted: ' . $bad : '') : $bad);
  }
}
