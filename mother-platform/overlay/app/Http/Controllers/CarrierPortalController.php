<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
/** Call Clients portal: a client logs in with the username + password the admin set (Call Clients > System settings) and sees ONLY its own usage: calls, minutes, amount, rate and billing cycle. Read-only. */
class CarrierPortalController extends Controller {
  private function me() {
    $id = (int)session('cc_portal_id'); if ($id <= 0) return null;
    $c = DB::table('voice_carrier_clients')->find($id);
    return ($c && $c->portal_on && $c->portal_user) ? $c : null;
  }
  public function loginForm() { if ($this->me()) return redirect('/call-portal'); return view('carrier_portal_login'); }
  public function login(Request $r) {
    $d = $r->validate(['username' => 'required|string|max:60', 'password' => 'required|string|max:200']);
    $c = DB::table('voice_carrier_clients')->where('portal_user', mb_strtolower(trim($d['username'])))->where('portal_on', true)->first();
    if (!$c || !$c->portal_pass || !Hash::check($d['password'], $c->portal_pass)) { usleep(300000); return back()->withErrors(['username' => 'Wrong username or password.'])->withInput(['username' => $d['username']]); }
    $r->session()->regenerate(); session(['cc_portal_id' => (int)$c->id]);
    DB::table('voice_carrier_clients')->where('id', $c->id)->update(['portal_last_login' => now()]);
    return redirect('/call-portal');
  }
  public function logout(Request $r) { $r->session()->forget('cc_portal_id'); $r->session()->regenerateToken(); return redirect('/call-portal/login'); }
  public function home(Request $r) {
    $c = $this->me(); if (!$c) return redirect('/call-portal/login');
    $u = CarrierClientController::usage($c); $tz = 'Asia/Dhaka';
    [$from, $to] = CarrierClientController::cycleWindow($c);
    $rows = DB::table('voice_call_cdrs')->where('client_id', $c->id)->where('billsec', '>', 0)->where('ended_at', '>=', $from->copy()->utc())->where('ended_at', '<', $to->copy()->utc())
      ->orderByDesc('id')->limit(200)->get(['dest', 'billsec', 'ended_at'])
      ->map(fn($x) => ['time' => \Illuminate\Support\Carbon::parse($x->ended_at, 'UTC')->setTimezone($tz)->format('d M Y, h:i A'), 'number' => CarrierClientController::mobile((string)$x->dest, (string)$c->prefix), 'secs' => (int)$x->billsec, 'billed' => (int)ceil($x->billsec / 60),
        'amount' => $c->rate_per_min === null ? null : round(ceil($x->billsec / 60) * (float)$c->rate_per_min, 2)]);
    return view('carrier_portal', ['c' => $c, 'u' => $u, 'rows' => $rows, 'cycleDays' => max(1, (int)($c->cycle_days ?? 30)), 'cycleEnds' => $to->copy()->subDay()->format('d M Y')]);
  }
  /** Admin (Call Clients > System settings): set or change the client's portal login. A blank password keeps the saved one. */
  public function adminSave(Request $r, $id) {
    abort_unless(\App\Support\ProfitFloor::admin(), 403);
    $c = DB::table('voice_carrier_clients')->find($id); abort_unless($c, 404);
    $d = $r->validate(['portal_user' => 'nullable|string|min:3|max:60|regex:/^[A-Za-z0-9._-]+$/', 'portal_pass' => 'nullable|string|min:8|max:100']);
    $u = ($d['portal_user'] ?? '') === '' ? null : mb_strtolower($d['portal_user']);
    if ($u && DB::table('voice_carrier_clients')->where('portal_user', $u)->where('id', '!=', $id)->exists()) return back()->withErrors(['portal_user' => 'This username is already used by another client.']);
    $upd = ['portal_user' => $u, 'portal_on' => $r->boolean('portal_on') && $u !== null, 'updated_at' => now()];
    if (($d['portal_pass'] ?? '') !== '') $upd['portal_pass'] = Hash::make($d['portal_pass']);
    if ($upd['portal_on'] && empty($upd['portal_pass'] ?? $c->portal_pass)) return back()->withErrors(['portal_pass' => 'Set a password (at least 8 characters) before turning the portal on.']);
    DB::table('voice_carrier_clients')->where('id', $id)->update($upd);
    \App\Support\ProfitFloor::audit('voice.client_portal', 'Call client #'.$id.' portal '.($upd['portal_on'] ? 'ON' : 'OFF').($u ? ' (user '.$u.')' : '').(isset($d['portal_pass']) && $d['portal_pass'] !== '' ? ', password changed' : ''), null, ['id' => $id]);
    return back()->with('ok', 'Client portal login saved.');
  }
}
