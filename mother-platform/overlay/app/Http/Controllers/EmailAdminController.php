<?php
namespace App\Http\Controllers;

use App\Support\Mailer as M;
use App\Support\ProfitFloor;
use App\Support\Procure;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Customer Email (Owner / Admin only): mailbox connection, which order emails go out, previews, a test send and the log. Nothing is sent to customers while the master switch is off. */
class EmailAdminController extends Controller {
  private function gate(): void { abort_unless(ProfitFloor::admin(), 403); }
  private function sample(): ?object { try { return DB::table('orders')->whereExists(fn($q) => $q->select(DB::raw(1))->from('order_items')->whereColumn('order_items.order_id', 'orders.id'))->orderByDesc('id')->first(); } catch (\Throwable $e) { return null; } }
  public function index() {
    $this->gate(); $counts = collect(); $log = collect(); $withEmail = null;
    if (M::tableReady()) { $counts = DB::table('mail_outbox')->where('created_at', '>=', now()->subDays(7))->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'); $log = DB::table('mail_outbox')->orderByDesc('id')->limit(30)->get(['id', 'to', 'subject', 'type', 'status', 'error', 'created_at', 'sent_at']); }
    try { $withEmail = (int)DB::table('customers')->whereNotNull('email')->where('email', '!=', '')->count(); } catch (\Throwable $e) {}
    return view('email_admin', ['on' => M::set('on', '0') === '1', 'ready' => M::ready(), 'hasPw' => M::hasPassword(), 'table' => M::tableReady(), 'user' => M::user(), 'fromName' => M::fromName(), 'replyTo' => M::replyTo(), 'cap' => M::cap(), 'today' => M::sentToday(),
      'kinds' => collect(M::KINDS)->map(fn($v, $k) => ['label' => $v[1], 'on' => M::kindOn($k)]), 'counts' => $counts, 'log' => $log, 'withEmail' => $withEmail, 'hasSample' => (bool)$this->sample()]);
  }
  public function preview(string $kind) {
    $this->gate(); abort_unless(isset(M::KINDS[$kind]), 404); $o = $this->sample(); abort_unless($o, 404);
    [$sub, $html] = M::orderHtml($o, $kind);
    return response($html)->header('Content-Type', 'text/html; charset=utf-8')->header('X-Robots-Tag', 'noindex')->header('Cache-Control', 'no-store');
  }
  public function save(Request $r) {
    $this->gate();
    $d = $r->validate(['act' => 'required|in:settings,password,test', 'on' => 'nullable|in:0,1', 'from_name' => 'nullable|string|max:60', 'reply_to' => 'nullable|email|max:190', 'daily_cap' => 'nullable|integer|min:50|max:1000', 'password' => 'nullable|string|min:8|max:200', 'to' => 'nullable|email|max:190', 'kind' => 'nullable|string|max:20']);
    $audit = fn($what) => DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name, 'action' => 'email.settings', 'subject' => $what, 'ip' => $r->ip(), 'created_at' => now(), 'updated_at' => now()]);
    if ($d['act'] === 'password') {
      if (empty($d['password'])) return back()->with('err', 'Type the mailbox password.');
      Sms::setSecret('email_password', $d['password']); $audit('Mailbox password saved');
      return back()->with('ok', 'Mailbox password saved. Send a test email to check the connection.');
    }
    if ($d['act'] === 'test') {
      if (empty($d['to'])) return back()->with('err', 'Type the address the test should go to.');
      $o = $this->sample(); if (!$o) return back()->with('err', 'No order to build the sample from.');
      $kind = isset(M::KINDS[$d['kind'] ?? '']) ? $d['kind'] : 'received'; [$sub, $html] = M::orderHtml($o, $kind);
      [$ok, $err] = M::deliver($d['to'], '[TEST] ' . $sub, $html);
      if (M::tableReady()) DB::table('mail_outbox')->insert(['to' => $d['to'], 'subject' => mb_substr('[TEST] ' . $sub, 0, 250), 'type' => 'test', 'status' => $ok ? 'sent' : 'failed', 'html' => '', 'error' => $err, 'sent_at' => $ok ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
      $audit('Test email to ' . $d['to'] . ': ' . ($ok ? 'sent' : 'failed'));
      return back()->with($ok ? 'ok' : 'err', $ok ? 'Test email sent to ' . $d['to'] . '. Check the inbox (and the spam folder).' : 'The test email could not be sent: ' . $err);
    }
    if ($r->input('on') === '1' && !M::ready()) return back()->with('err', 'Save the mailbox password and send a test email first.');
    Procure::put('email_on', $r->input('on') === '1' ? '1' : '0');
    if (isset($d['from_name']) && trim($d['from_name']) !== '') Procure::put('email_from_name', trim($d['from_name']));
    if (!empty($d['reply_to'])) Procure::put('email_reply_to', $d['reply_to']);
    if (!empty($d['daily_cap'])) Procure::put('email_daily_cap', (string)(int)$d['daily_cap']);
    foreach (array_keys(M::KINDS) as $k) Procure::put('email_kind_' . $k, $r->input('kind_' . $k) === '1' ? '1' : '0');
    $audit('Settings saved (customer emails ' . ($r->input('on') === '1' ? 'ON' : 'OFF') . ')');
    return back()->with('ok', 'Saved.');
  }
}
