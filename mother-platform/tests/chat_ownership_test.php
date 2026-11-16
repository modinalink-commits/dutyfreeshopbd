<?php
/**
 * Conversation ownership tests (not deployed: this file is outside overlay/).
 * They run the real code against a real PostgreSQL database, with several PHP processes started at the same moment, so the races are real.
 * Facebook and the AI model are replaced by local stand-ins that wait a little (like the real ones) and write every message that "left the server" to a log.
 *
 * Run:  TEST_APP=/path/to/laravel-app-with-overlay php chat_ownership_test.php
 * The app must point at a throw-away database (APP_ENV=testing): the test creates users, conversations and messages.
 */
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Support\Chat;
use App\Support\ChatOwner;
use App\Support\Sms;

$APP = getenv('TEST_APP') ?: getcwd();
require $APP.'/vendor/autoload.php';
$app = require $APP.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('app.env') !== 'testing') { fwrite(STDERR, "Refusing to run: APP_ENV is not 'testing'.\n"); exit(2); }
define('SENDLOG', getenv('SENDLOG') ?: sys_get_temp_dir().'/chat_owner_sends.log');

/** Stand-ins for Facebook Messenger and the AI model. Every Messenger send is logged with its start and end time. */
function fakes(): void {
  Http::fake(function ($req) {
    $u = $req->url();
    if (str_contains($u, 'api.anthropic.com')) {
      static $n = 0; $n++;
      usleep((int)(getenv('AI_MS') ?: 300) * 1000);
      if (getenv('AI_MODE') === 'handoff' && $n === 1) return Http::response(['content'=>[['type'=>'tool_use', 'id'=>'tu1', 'name'=>'handoff_to_human', 'input'=>['reason'=>'customer asks for a person', 'summary'=>'test', 'priority'=>'normal']]], 'stop_reason'=>'tool_use'], 200);
      return Http::response(['content'=>[['type'=>'text', 'text'=>'<reply>AI-ANSWER</reply>']], 'stop_reason'=>'end_turn'], 200);
    }
    if (str_contains($u, 'graph.facebook.com') && str_contains($u, '/messages')) {
      $t0 = microtime(true); $text = (string)($req['message']['text'] ?? '[attachment]'); $to = (string)($req['recipient']['id'] ?? '');
      usleep((int)(getenv('SEND_MS') ?: 200) * 500);
      if (getenv('CRASH') === '1') posix_kill(getmypid(), SIGKILL); // the server process dies while the message is on its way
      usleep((int)(getenv('SEND_MS') ?: 200) * 500);
      file_put_contents(SENDLOG, json_encode(['to'=>$to, 'text'=>$text, 't0'=>$t0, 't1'=>microtime(true), 'pid'=>getmypid()])."\n", FILE_APPEND | LOCK_EX);
      return Http::response(['message_id'=>'m.'.bin2hex(random_bytes(5))], 200);
    }
    return Http::response([], 200);
  });
}
function ctl() { return app(\App\Http\Controllers\ChatController::class); }
function req(string $path, array $data, $u): Request { $r = Request::create($path, 'POST', $data); $r->setUserResolver(fn() => $u); app()->instance('request', $r); return $r; }

// ---------------- worker: one actor doing one thing at an agreed moment ----------------
if (($argv[1] ?? '') === 'worker') {
  fakes(); $op = $argv[2]; $at = (float)$argv[3]; $a = array_slice($argv, 4);
  $wait = $at - microtime(true); if ($wait > 0) usleep((int)($wait * 1e6));
  $t0 = microtime(true); $out = ['op'=>$op];
  try {
    if (in_array($op, ['reply', 'claim', 'assign', 'close', 'note'], true)) { $u = \App\Models\User::find((int)$a[0]); Auth::setUser($u); $tid = (int)$a[1]; }
    if ($op === 'reply') { $res = ctl()->reply(req("/admin/chat/thread/$tid/reply", ['body'=>$a[2], 'client_token'=>$a[3] ?? ''], $u), $tid); $out += ['code'=>$res->getStatusCode()] + $res->getData(true); }
    elseif ($op === 'claim') { $res = ctl()->claim(req("/admin/chat/thread/$tid/claim", [], $u), $tid); $out += ['code'=>$res->getStatusCode()] + $res->getData(true); }
    elseif ($op === 'assign') { $res = ctl()->assign(req("/admin/chat/thread/$tid/assign", ['user_id'=>$a[2] === 'null' ? null : (int)$a[2], 'note'=>$a[3] ?? ''], $u), $tid); $out += ['code'=>$res->getStatusCode()] + $res->getData(true); }
    elseif ($op === 'close') { $res = ctl()->status(req("/admin/chat/thread/$tid/status", ['status'=>'resolved'], $u), $tid); $out += ['code'=>$res->getStatusCode()] + $res->getData(true); }
    elseif ($op === 'note') { $res = ctl()->note(req("/admin/chat/thread/$tid/note", ['body'=>$a[2]], $u), $tid); $out += ['code'=>$res->getStatusCode()] + $res->getData(true); }
    elseif ($op === 'ai') { Chat::aiReply((int)$a[0]); $out['done'] = true; }
    elseif ($op === 'stale') { Chat::staleOne(DB::table('chat_threads')->find((int)$a[0]), 30); $out['done'] = true; }
    elseif ($op === 'ingest') { $th = Chat::ingest('facebook', $a[0], 'Cust', $a[2], null, $a[1], ['page_ref'=>'P1']); $out['new'] = $th !== null; }
  } catch (\Throwable $e) { $out['exception'] = get_class($e).': '.$e->getMessage(); }
  $out['t0'] = $t0; $out['t1'] = microtime(true);
  echo json_encode($out, JSON_UNESCAPED_UNICODE); exit(0);
}

// ---------------- orchestrator ----------------
$pass = 0; $fail = 0;
function ok(bool $c, string $label): void { global $pass, $fail; $c ? $pass++ : $fail++; echo ($c ? '  PASS  ' : '  FAIL  ').$label."\n"; }
/** Start several workers; each job is [delaySeconds, env, op, args...]. They all begin from the same clock. */
function run(array $jobs): array {
  $base = microtime(true) + 1.6; $procs = [];
  foreach ($jobs as $i => $j) {
    [$delay, $env, $op] = $j; $args = array_slice($j, 3);
    $cmd = array_merge([PHP_BINARY, __FILE__, 'worker', $op, (string)($base + $delay)], array_map('strval', $args));
    $pipes = []; $p = proc_open($cmd, [1=>['pipe', 'w'], 2=>['pipe', 'w']], $pipes, null, array_merge(getenv(), $env));
    $procs[$i] = [$p, $pipes];
  }
  $res = [];
  foreach ($procs as $i => [$p, $pipes]) { $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]); proc_close($p); $res[$i] = json_decode($o, true) ?: ['raw'=>$o, 'stderr'=>mb_substr($e, 0, 300), 'died'=>true]; }
  return $res;
}
function sends(?string $to = null): array { $o = []; foreach (@file(SENDLOG) ?: [] as $l) { $j = json_decode($l, true); if ($j && ($to === null || $j['to'] === $to)) $o[] = $j; } usort($o, fn($a, $b) => $a['t0'] <=> $b['t0']); return $o; }
function overlap(array $s): bool { for ($i = 1; $i < count($s); $i++) if ($s[$i]['t0'] < $s[$i - 1]['t1']) return true; return false; }
$seq = 0;
function thread(array $over = []): object {
  global $seq; $seq++; $ext = 'cust'.getmypid().'x'.$seq;
  $id = DB::table('chat_threads')->insertGetId($over + ['channel'=>'facebook', 'external_id'=>$ext, 'name'=>'Customer '.$seq, 'page_ref'=>'P1', 'status'=>'open', 'unread'=>1, 'awaiting_reply'=>true, 'awaiting_since'=>now(), 'last_customer_message_at'=>now(), 'last_message'=>'দাম কত?', 'last_dir'=>'in', 'last_at'=>now(), 'created_at'=>now(), 'updated_at'=>now()]);
  DB::table('chat_messages')->insert(['thread_id'=>$id, 'dir'=>'in', 'kind'=>'text', 'body'=>'দাম কত?', 'ext_id'=>'in.'.$ext, 'created_at'=>now(), 'updated_at'=>now()]);
  return DB::table('chat_threads')->find($id);
}
function owner(int $tid): ?int { $v = DB::table('chat_threads')->where('id', $tid)->value('assigned_to'); return $v === null ? null : (int)$v; }
function events(int $tid, string $action): int { return DB::table('chat_assign_events')->where('thread_id', $tid)->where('action', $action)->count(); }
function outMsgs(int $tid) { return DB::table('chat_messages')->where('thread_id', $tid)->where('dir', 'out')->orderBy('id')->get(); }

// ----- fixtures -----
@unlink(SENDLOG);
$rid = DB::table('roles')->where('name', 'Test Agent')->value('id') ?: DB::table('roles')->insertGetId(['name'=>'Test Agent', 'permissions'=>json_encode(['orders'=>'edit']), 'created_at'=>now(), 'updated_at'=>now()]);
$mk = function (string $name, string $role, ?int $roleId) { $e = strtolower($name).'@owner-test.local'; $id = DB::table('users')->where('email', $e)->value('id');
  return (int)($id ?: DB::table('users')->insertGetId(['name'=>$name, 'email'=>$e, 'password'=>bcrypt('x'), 'role'=>$role, 'role_id'=>$roleId, 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()])); };
$A = $mk('AgentA', 'staff', $rid); $B = $mk('AgentB', 'staff', $rid); $C = $mk('AgentC', 'staff', $rid); $S = $mk('Supervisor', 'admin', null);
DB::table('users')->whereNotIn('id', [$A, $B, $C, $S])->update(['active'=>false]);
foreach (['chat_fb_token'=>'test-token', 'ai_enabled'=>'1', 'ai_key'=>'test-key', 'ai_agent_tools'=>'1', 'ai_chat_auto'=>'1', 'ai_chat_chan'=>'1', 'chat_auto_assign'=>'1', 'chat_absent_min'=>'10', 'chat_stale_on'=>'0'] as $k => $v) Sms::setSecret($k, $v);
Cache::flush();
echo "Users: A=$A B=$B C=$C Supervisor=$S\n\n";

echo "1. Two (and four) representatives take the same conversation at the same moment\n";
for ($round = 1; $round <= 6; $round++) {
  $t = thread(); $who = $round % 2 ? [$A, $B] : [$A, $B, $C, $S];
  $r = run(array_map(fn($u) => [0, [], 'claim', $u, $t->id], $who));
  $wins = array_values(array_filter($r, fn($x) => !empty($x['ok'])));
  ok(count($wins) === 1 && owner($t->id) !== null && events($t->id, 'claim') === 1 && count(array_filter($r, fn($x) => ($x['code'] ?? 0) === 409)) === count($who) - 1,
    "round $round: ".count($who)." people pressed Take, exactly one got it (owner #".owner($t->id).", ".(count($who) - 1)." refused)");
}

echo "\n2. Two representatives answer the same unassigned customer at the same moment\n";
for ($round = 1; $round <= 4; $round++) {
  $t = thread();
  $r = run([[0, ['SEND_MS'=>'600'], 'reply', $A, $t->id, 'REPLY-FROM-A', "a$round"], [0, ['SEND_MS'=>'600'], 'reply', $B, $t->id, 'REPLY-FROM-B', "b$round"]]);
  $s = sends($t->external_id); $sent = outMsgs($t->id)->where('status', 'sent');
  ok(count($s) === 1 && $sent->count() === 1 && count(array_filter($r, fn($x) => ($x['code'] ?? 0) === 409 && !empty($x['blocked']))) === 1 && (int)$sent->first()->user_id === owner($t->id),
    "round $round: one message reached the customer (from the owner #".owner($t->id)."), the other representative was refused by the server");
}

echo "\n3. A representative who is not the owner\n";
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()]);
$r = run([[0, [], 'reply', $B, $t->id, 'B-SHOULD-NOT-SEND', 'x1'], [0.2, [], 'note', $B, $t->id, 'B internal note'], [0.3, [], 'assign', $B, $t->id, $B, 'grab'], [0.4, [], 'close', $B, $t->id]]);
ok(($r[0]['code'] ?? 0) === 409 && count(sends($t->external_id)) === 0 && outMsgs($t->id)->count() === 0, 'reply refused, nothing sent, nothing stored as sent');
ok(!empty($r[1]['ok']) && DB::table('chat_notes')->where('thread_id', $t->id)->count() === 1, 'internal note is allowed');
ok(($r[2]['code'] ?? 0) === 409 && owner($t->id) === $A, 'cannot move the conversation to himself (only a supervisor can)');
ok(($r[3]['code'] ?? 0) === 409 && DB::table('chat_threads')->where('id', $t->id)->value('status') !== 'resolved', 'cannot close another representative\'s conversation');
ok(events($t->id, 'reply_blocked') >= 1, 'the refused attempt is recorded');

echo "\n4. A person and the AI at the same moment\n";
// 4a: the AI is still writing its answer when a person takes the conversation and replies
$t = thread();
$r = run([[0, ['AI_MS'=>'2500'], 'ai', $t->id], [0.6, ['SEND_MS'=>'300'], 'reply', $A, $t->id, 'HUMAN-REPLY', 'h1']]);
$s = sends($t->external_id);
ok(count($s) === 1 && str_contains($s[0]['text'], 'HUMAN-REPLY') && outMsgs($t->id)->where('is_ai', true)->count() === 0 && owner($t->id) === $A, 'AI was writing, a person took over: only the person\'s reply went out, the AI\'s waiting answer was dropped');
// 4b: the AI's message is already on its way when the person presses send
$t = thread();
$r = run([[0, ['AI_MS'=>'100', 'SEND_MS'=>'2000'], 'ai', $t->id], [1.2, ['SEND_MS'=>'300'], 'reply', $A, $t->id, 'HUMAN-REPLY', 'h2']]);
$s = sends($t->external_id);
ok(count($s) === 2 && !overlap($s) && str_contains($s[0]['text'], 'AI-ANSWER') && str_contains($s[1]['text'], 'HUMAN-REPLY'), 'AI message already leaving: the person\'s reply waited for it and went after it, never at the same time');
// 4c: a person owns the conversation, the customer writes again: the AI stays silent
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()]);
run([[0, [], 'ai', $t->id]]);
ok(count(sends($t->external_id)) === 0 && outMsgs($t->id)->count() === 0, 'conversation owned by a person: the AI does not answer');
// 4d: three AI runs for the same customer message (duplicate events / two workers)
$t = thread();
run([[0, ['AI_MS'=>'400'], 'ai', $t->id], [0, ['AI_MS'=>'400'], 'ai', $t->id], [0.05, ['AI_MS'=>'400'], 'ai', $t->id]]);
ok(count(sends($t->external_id)) === 1 && outMsgs($t->id)->where('is_ai', true)->count() === 1, 'three AI runs for one customer message: one answer');

echo "\n5. Hand-over while the old representative is sending\n";
// 5a: A's message is on its way, the supervisor moves the conversation to B, then A tries again
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()]);
$r = run([[0, ['SEND_MS'=>'2000'], 'reply', $A, $t->id, 'A-FIRST', 'a1'], [0.6, [], 'assign', $S, $t->id, $B, 'গ্রাহক দাম জানতে চান, ডেলিভারি চার্জ বলা হয়েছে'], [0.9, ['SEND_MS'=>'200'], 'reply', $A, $t->id, 'A-SECOND', 'a2']]);
$s = sends($t->external_id);
ok(count($s) === 1 && str_contains($s[0]['text'], 'A-FIRST'), 'A\'s message that was already leaving was delivered once');
ok(!empty($r[1]['ok']) && $r[1]['t1'] >= $s[0]['t1'] - 0.05 && owner($t->id) === $B, 'the hand-over waited until that message had left, then B became the owner');
ok(($r[2]['code'] ?? 0) === 409 && !empty($r[2]['blocked']), 'A\'s next reply (sent during the hand-over) was refused by the server');
// 5b: the new owner sees the earlier messages, the hand-over note and the record
Auth::setUser(\App\Models\User::find($B)); $d = ctl()->thread($t->id)->getData(true);
ok(count($d['messages']) >= 2 && collect($d['notes'])->contains(fn($n) => str_contains($n['body'], 'ডেলিভারি চার্জ')) && !empty($d['owner']['mine']) && collect($d['owner_events'])->contains(fn($e) => $e['action'] === 'transfer'), 'B sees the earlier conversation, the hand-over note and who moved it');
$r = run([[0, ['SEND_MS'=>'200'], 'reply', $B, $t->id, 'B-REPLY', 'b1']]);
ok(!empty($r[0]['ok']) && count(sends($t->external_id)) === 2, 'B, the new owner, can answer');
// 5c: hand-over and the old owner's send start at the same instant, many times: the customer never gets a message from A after B owns it
$bad = 0;
for ($i = 0; $i < 6; $i++) { $t = thread(['assigned_to'=>$A, 'assigned_at'=>now()]);
  $r = run([[0, ['SEND_MS'=>'500'], 'reply', $A, $t->id, 'A-RACE', "r$i"], [0, [], 'assign', $S, $t->id, $B, '']]);
  $s = sends($t->external_id); $ev = DB::table('chat_assign_events')->where('thread_id', $t->id)->where('action', 'transfer')->first();
  // either A's send completed before the transfer was recorded, or A was refused
  if (owner($t->id) !== $B) $bad++; elseif (count($s) === 1 && empty($r[0]['ok'])) $bad++; elseif (count($s) === 0 && ($r[0]['code'] ?? 0) !== 409) $bad++;
  elseif (count($s) === 1 && $ev && strtotime($ev->created_at) + 1 < (int)$s[0]['t1']) $bad++; }
ok($bad === 0, 'hand-over and the old owner\'s send at the same instant (6 rounds): the send either finished before the hand-over or was refused');

echo "\n6. Lost connection and retries\n";
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()]);
$r = run([[0, ['SEND_MS'=>'1500'], 'reply', $A, $t->id, 'ONCE-ONLY', 'tok1'], [0.4, ['SEND_MS'=>'1500'], 'reply', $A, $t->id, 'ONCE-ONLY', 'tok1'], [0.5, ['SEND_MS'=>'1500'], 'reply', $A, $t->id, 'ONCE-ONLY', 'tok1'], [3.0, [], 'reply', $A, $t->id, 'ONCE-ONLY', 'tok1']]);
ok(count(sends($t->external_id)) === 1 && outMsgs($t->id)->count() === 1 && !empty($r[3]['ok']) && (int)$r[3]['id'] === (int)$r[0]['id'] && !empty($r[1]['dup']) && !empty($r[2]['dup']), 'the app retried the same message three times (during and after the send): the customer got it once');
// the server process dies in the middle of a send
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()]);
$r = run([[0, ['SEND_MS'=>'600', 'CRASH'=>'1'], 'reply', $A, $t->id, 'CRASH-MSG', 'tok2']]);
$m = outMsgs($t->id)->first();
ok(!empty($r[0]['died']) && $m && $m->status === 'sending', 'server died mid-send: the message is kept as "sending", not shown as sent');
$r = run([[0, [], 'reply', $A, $t->id, 'CRASH-MSG', 'tok2']]);
ok(!empty($r[0]['dup']) && count(sends($t->external_id)) === 0, 'retry of that message is not sent a second time automatically');
$r = run([[0, [], 'assign', $S, $t->id, $B, '']]);
ok(empty($r[0]['ok']) && owner($t->id) === $A, 'while that send is unresolved the conversation cannot be moved (it frees itself after the lease time)');
DB::table('chat_threads')->where('id', $t->id)->update(['send_lock_until'=>now()->subSecond()]); DB::table('chat_messages')->where('id', $m->id)->update(['created_at'=>now()->subMinutes(6)]); // time passes
Cache::forget('chat.owner.tick'); ChatOwner::tick();
ok(DB::table('chat_messages')->where('id', $m->id)->value('status') === 'failed', 'after the time limit the cut-off message is marked failed with a warning to check before sending again');
$r = run([[0, [], 'assign', $S, $t->id, $B, ''], [0.5, ['SEND_MS'=>'200'], 'reply', $B, $t->id, 'AFTER-CRASH', 'tok3']]);
ok(!empty($r[0]['ok']) && !empty($r[1]['ok']) && count(sends($t->external_id)) === 1, 'then the conversation can be moved and the new owner can answer');

echo "\n7. The same customer message delivered several times by the platform\n";
$ext = 'dup'.getmypid(); $mid = 'mid.'.bin2hex(random_bytes(6));
Sms::setSecret('ai_enabled', '0'); Cache::forget('ai.on'); DB::table('users')->whereIn('id', [$A, $B, $C, $S])->update(['active'=>false]); // nobody to auto-assign: plain storage test
$r = run(array_fill(0, 5, [0, [], 'ingest', $ext, $mid, 'একই মেসেজ']));
$tid = (int)DB::table('chat_threads')->where('external_id', $ext)->value('id');
ok(count(array_filter($r, fn($x) => !empty($x['new']))) === 1 && DB::table('chat_messages')->where('ext_id', $mid)->count() === 1 && empty(array_filter($r, fn($x) => !empty($x['exception']))), 'five copies of one event at once: stored once, four dropped quietly, no error');
ok((int)DB::table('chat_threads')->where('id', $tid)->value('unread') === 1, 'the conversation counts one unread message, not five');
DB::table('users')->whereIn('id', [$A, $B, $C, $S])->update(['active'=>true]); Sms::setSecret('ai_enabled', '1'); Cache::forget('ai.on');

echo "\n8. New conversations and allocation\n";
// AI answers this channel: a new customer message stays with the AI (nobody is assigned)
$r = run([[0, [], 'ingest', 'newA'.getmypid(), 'm.'.bin2hex(random_bytes(5)), 'হ্যালো']]);
$tid = (int)DB::table('chat_threads')->where('external_id', 'newA'.getmypid())->value('id');
ok(owner($tid) === null, 'AI is on: a new conversation stays with the AI, no representative is assigned');
// AI off: allocated to the least busy available representative
Sms::setSecret('ai_enabled', '0'); Cache::forget('ai.on');
DB::table('chat_threads')->whereNotNull('assigned_to')->update(['assigned_to'=>null]);
for ($i = 0; $i < 3; $i++) thread(['assigned_to'=>$A, 'assigned_at'=>now()]); thread(['assigned_to'=>$B, 'assigned_at'=>now()]); thread(['assigned_to'=>$S, 'assigned_at'=>now()]); thread(['assigned_to'=>$S, 'assigned_at'=>now()]);
$r = run([[0, [], 'ingest', 'newB'.getmypid(), 'm.'.bin2hex(random_bytes(5)), 'হ্যালো']]);
$tid = (int)DB::table('chat_threads')->where('external_id', 'newB'.getmypid())->value('id');
ok(owner($tid) === null && events($tid, 'auto_assign') === 0, 'AI is off: the new conversation is not given to anyone in advance; it waits in the open list (rule of 16 Nov 2026)');
// closed conversation, customer writes again: the old owner is not kept
DB::table('chat_threads')->where('id', $tid)->update(['status'=>'resolved', 'assigned_to'=>$A, 'assigned_at'=>now()]); // a closed conversation that still carries its old owner
$r = run([[0, [], 'ingest', 'newB'.getmypid(), 'm.'.bin2hex(random_bytes(5)), 'আবার এলাম']]);
ok(owner($tid) === null && events($tid, 'release') === 1 && events($tid, 'auto_assign') === 0, 'closed conversation reopened by the customer: the old owner is released and nobody is assigned in advance');
Sms::setSecret('ai_enabled', '1'); Cache::forget('ai.on');
// closing releases the owner
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()]);
$r = run([[0, [], 'close', $A, $t->id]]);
ok(!empty($r[0]['ok']) && owner($t->id) === null && events($t->id, 'release') === 1, 'the owner closes the conversation: it no longer has an owner');

echo "\n9. AI hand-off to a person\n";
DB::table('chat_threads')->whereNotNull('assigned_to')->update(['assigned_to'=>null]);
$t = thread();
run([[0, ['AI_MODE'=>'handoff', 'AI_MS'=>'200'], 'ai', $t->id]]);
$s = sends($t->external_id); $o = owner($t->id);
ok($o === null && count($s) === 1 && events($t->id, 'auto_assign') === 0, 'the AI handed the chat to staff: its one closing line reached the customer and the chat waits in the open list, assigned to nobody');
DB::table('chat_messages')->insert(['thread_id'=>$t->id, 'dir'=>'in', 'kind'=>'text', 'body'=>'আছেন?', 'created_at'=>now(), 'updated_at'=>now()]);
Cache::forget('ai.lock.'.$t->id); // the hand-off marks (needs a person, AI paused) keep the AI out while the chat waits for staff
run([[0, [], 'ai', $t->id]]);
ok(count(sends($t->external_id)) === 1, 'after the hand-off the AI does not answer the customer\'s next message');

echo "\n10. The owner is away\n";
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()->subMinutes(20), 'awaiting_since'=>now()->subMinutes(15)]);
$t2 = thread(['assigned_to'=>$B, 'assigned_at'=>now(), 'awaiting_since'=>now()->subMinutes(3)]);
DB::table('chat_wait_notices')->delete(); Cache::forget('chat.owner.tick'); ChatOwner::tick();
ok(DB::table('chat_wait_notices')->where('thread_id', $t->id)->where('user_id', $S)->count() === 1 && events($t->id, 'absent_alert') === 1, 'customer waited 15 minutes (limit 10): the supervisor is told and it is recorded');
ok(DB::table('chat_wait_notices')->where('thread_id', $t2->id)->count() === 0, 'a customer waiting 3 minutes raises no alert');
Cache::forget('chat.owner.tick'); ChatOwner::tick(); Cache::forget('chat.owner.tick'); ChatOwner::tick();
ok(DB::table('chat_wait_notices')->where('thread_id', $t->id)->count() === 1, 'the alert is sent once per waiting period, not on every check');
$r = run([[0, [], 'assign', $S, $t->id, $B, 'A অনুপস্থিত']]);
ok(!empty($r[0]['ok']) && owner($t->id) === $B, 'the supervisor moves the conversation to another representative');
Auth::setUser(\App\Models\User::find($A)); $h = ctl()->ownerHistory($t->id)->getData(true);
ok(collect($h['events'])->pluck('action')->intersect(['absent_alert', 'transfer'])->count() === 2, 'the record shows the alert and the move, with who and when');

echo "\n11. The older rule: a customer left waiting past the wait limit\n";
DB::table('chat_threads')->whereNotNull('assigned_to')->update(['assigned_to'=>null]);
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()->subHour(), 'awaiting_since'=>now()->subMinutes(40)]);
run([[0, ['AI_MS'=>'200'], 'stale', $t->id]]);
$s = sends($t->external_id);
ok(owner($t->id) === null && count($s) === 1 && str_contains($s[0]['text'], 'AI-ANSWER') && events($t->id, 'release') === 1, 'ordinary question, owner silent: the conversation is released (recorded) and then the AI answers, so only one side is answering');
$t = thread(['assigned_to'=>$A, 'assigned_at'=>now()->subHour(), 'awaiting_since'=>now()->subMinutes(40), 'intent'=>'complaint']);
run([[0, [], 'stale', $t->id]]);
$s = sends($t->external_id);
ok(owner($t->id) !== null && count($s) === 1 && !str_contains($s[0]['text'], 'AI-ANSWER') && outMsgs($t->id)->count() === 1, 'complaint, owner silent: it stays with a person (owner #'.owner($t->id).'), the customer gets one holding message and no AI answer');

echo "\n".str_repeat('-', 60)."\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
