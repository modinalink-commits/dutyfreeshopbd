<?php
namespace App\Http\Controllers;

use App\Jobs\GenerateSupplierPortalAccountJob as GenJob;
use App\Jobs\SendSupplierInvitationSmsJob as SendJob;
use App\Support\Procure as P;
use App\Support\Sms;
use App\Support\SupplierPortal as SP;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Admin "Supplier Account Setup": generate portal accounts for all active suppliers, send single-use activation links, track status, export. */
class SupplierPortalSetupController extends Controller
{
  private function mobile(?string $raw): array {
    $raw = trim((string)$raw); if ($raw === '') return ['', 'Mobile Missing']; $n = Sms::number($raw);
    return ($n && preg_match('/^8801[3-9]\d{8}$/', $n)) ? ['0'.substr($n,3), null] : [$raw, 'Invalid Mobile'];
  }
  public static function status(?object $u): string {
    if (!$u) return 'Not Created'; if ($u->status !== 'active') return 'Suspended';
    if ($u->password_changed_at) return 'Password Changed'; if ($u->activated_at) return 'Activated'; if ($u->invite_sent_at) return 'Invitation Sent'; return 'Created';
  }
  private function rows(): array {
    $users = DB::table('supplier_users')->orderBy('id')->get()->groupBy('supplier_id'); $sms = DB::table('supplier_sms_log')->orderBy('id')->get()->groupBy('supplier_id'); $out = [];
    foreach (DB::table('suppliers')->where('active',true)->orderBy('name')->get(['id','name','code','phone','whatsapp','contact_person']) as $s) {
      $u = isset($users[$s->id]) ? $users[$s->id]->first() : null; [$m, $flag] = $this->mobile($s->phone ?: $s->whatsapp); $l = isset($sms[$s->id]) ? $sms[$s->id]->last() : null;
      $out[] = ['s'=>$s,'u'=>$u,'mobile'=>$u->mobile ?? $m,'flag'=>$u ? null : $flag,'user'=>$u->username ?? GenJob::username($s),'status'=>self::status($u),'sms'=>$l ? ($l->status === 'sent' ? 'Sent' : ($l->status === 'failed' ? 'Failed' : 'Pending')) : 'Not Sent','sms_at'=>$l->sent_at ?? null];
    }
    return $out;
  }
  public function index() {
    $rows = $this->rows(); $c = ['total'=>count($rows),'valid'=>0,'bad'=>0,'active'=>0,'send'=>0];
    foreach ($rows as $r) { if ($r['flag']) $c['bad']++; else $c['valid']++; if (in_array($r['status'],['Activated','Password Changed'])) $c['active']++; if (!$r['flag'] && !in_array($r['status'],['Activated','Password Changed','Suspended','Invitation Sent'])) $c['send']++; }
    $log = DB::table('supplier_sms_log as l')->join('suppliers as s','s.id','=','l.supplier_id')->orderByDesc('l.id')->limit(25)->get(['l.*','s.name as sname']);
    return view('sp.admin_setup', compact('rows','c','log') + ['brand'=>SP::brand()]);
  }
  /** One action for any selection: generate | send | resend | reset | disable | enable. */
  public function act(Request $r) {
    $a = (string)$r->input('do'); $ids = array_map('intval', (array)$r->input('ids')); if (!$ids) return back()->with('bad','Select at least one supplier.');
    if (!in_array($a, ['generate','send','resend','reset','disable','enable'], true)) abort(404);
    $n = ['created'=>0,'exists'=>0,'skip'=>0,'queued'=>0]; $who = auth()->user()->name;
    foreach ($ids as $sid) {
      [$res, $uid] = (new GenJob($sid))->handle(); if ($res === 'created') $n['created']++; elseif ($res === 'exists') $n['exists']++; else { $n['skip']++; if (!in_array($a,['disable','enable'])) continue; }
      $uid = $uid ?: DB::table('supplier_users')->where('supplier_id',$sid)->orderBy('id')->value('id'); if (!$uid) continue; $u = DB::table('supplier_users')->find($uid);
      if ($a === 'disable') { DB::table('supplier_users')->where('supplier_id',$sid)->update(['status'=>'suspended','force_logout_at'=>now(),'updated_at'=>now()]); continue; }
      if ($a === 'enable') { DB::table('supplier_users')->where('supplier_id',$sid)->update(['status'=>'active','failed_attempts'=>0,'locked_until'=>null,'updated_at'=>now()]); continue; }
      if ($a === 'generate') continue;
      if ($u->status !== 'active') { $n['skip']++; continue; }
      $done = $u->password_changed_at || $u->activated_at;
      if ($a === 'send' && ($done || $u->invite_sent_at)) { $n['skip']++; continue; }       // plain Send never repeats or touches live accounts
      if ($a === 'resend' && $done) { $n['skip']++; continue; }                               // activated accounts use Reset instead
      if ($a === 'reset') DB::table('supplier_users')->where('id',$u->id)->update(['password'=>Hash::make(bin2hex(random_bytes(24))),'must_change'=>true,'activated_at'=>null,'password_changed_at'=>null,'force_logout_at'=>now(),'failed_attempts'=>0,'locked_until'=>null]);
      $tok = bin2hex(random_bytes(20));
      DB::table('supplier_users')->where('id',$u->id)->update(['invite_hash'=>hash('sha256',$tok),'invite_expires_at'=>now()->addDays(7),'invite_status'=>'pending','updated_at'=>now()]);
      $lid = DB::table('supplier_sms_log')->insertGetId(['supplier_id'=>$sid,'supplier_user_id'=>$u->id,'mobile'=>$u->mobile,'type'=>$a === 'reset' ? 'reset-login' : 'invitation','status'=>'pending','triggered_by'=>$who,'created_at'=>now(),'updated_at'=>now()]);
      $job = new SendJob($u->id, $lid, $tok); dispatch(fn() => $job->handle())->afterResponse(); $n['queued']++;
    }
    P::audit('sp.setup.'.$a, 'suppliers', null, ['selected'=>count($ids)] + $n);
    $msg = match ($a) { 'generate' => "Accounts: {$n['created']} created, {$n['exists']} already existed, {$n['skip']} need review (mobile).", 'disable' => 'Selected accounts suspended and logged out.', 'enable' => 'Selected accounts enabled.', default => "{$n['queued']} activation SMS being sent now; {$n['skip']} skipped (no valid mobile, already activated or already invited)." };
    return back()->with('ok', $msg);
  }
  public function export($fmt) {
    $head = ['Supplier Code','Supplier Name','Mobile','Username','Account Status','Invitation Status','Activated At','Last Login']; $rows = [];
    foreach ($this->rows() as $r) $rows[] = [$r['s']->code,$r['s']->name,$r['mobile'],$r['u'] ? $r['user'] : '',$r['status'],$r['u'] ? $r['sms'] : 'Not Sent',$r['u']->activated_at ?? '',$r['u']->last_login_at ?? ''];
    $name = 'supplier-accounts-'.now('Asia/Dhaka')->format('Ymd');
    if ($fmt === 'csv') return response()->streamDownload(function () use ($head,$rows) { $o = fopen('php://output','w'); fwrite($o,"\xEF\xBB\xBF"); fputcsv($o,$head); foreach ($rows as $x) fputcsv($o,$x); fclose($o); }, "$name.csv", ['Content-Type'=>'text/csv; charset=utf-8']);
    $tbl = function () use ($head,$rows) { echo '<table border="1" cellspacing="0" cellpadding="4"><tr>'; foreach ($head as $h) echo '<th style="background:#FF5A00;color:#fff">'.e($h).'</th>'; echo '</tr>'; foreach ($rows as $x) { echo '<tr>'; foreach ($x as $v) echo '<td>'.e((string)$v).'</td>'; echo '</tr>'; } echo '</table>'; };
    if ($fmt === 'xls') return response()->streamDownload(function () use ($tbl) { echo '<html><head><meta charset="utf-8"></head><body>'; $tbl(); echo '</body></html>'; }, "$name.xls", ['Content-Type'=>'application/vnd.ms-excel; charset=utf-8']);
    return response()->stream(function () use ($tbl) { $b = P::base(); echo '<html><head><meta charset="utf-8"><title>Supplier accounts</title><style>body{font-family:Arial;font-size:12px;margin:24px}.wm{position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:.06;transform:rotate(-24deg)}.wm img{height:70px}.wm b{font-size:44px;letter-spacing:4px}</style></head><body><div class="wm"><img src="'.$b.'/brand/export-logo.png"><b>DUTYFREEBD</b></div><img src="'.$b.'/brand/export-logo.png" style="height:44px"><h2>Supplier Portal Accounts</h2>'; $tbl(); echo '<script>window.onload=function(){window.print()}</script></body></html>'; }, 200, ['Content-Type'=>'text/html; charset=utf-8']);
  }
}
