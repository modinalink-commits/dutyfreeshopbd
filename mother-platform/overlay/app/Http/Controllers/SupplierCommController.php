<?php
namespace App\Http\Controllers;
use App\Support\ChatOwner;
use App\Support\SupplierComm as SC;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Supplier Conversations: contact data with its change history, each supplier's messages in one timeline, who holds the conversation, internal notes. Replies go through the Messages inbox rules. */
class SupplierCommController extends Controller {
  public function index(Request $r) {
    if (!SC::ready()) return view('supplier_comm', ['off' => true]);
    $q = trim((string)$r->query('q', '')); $owner = $r->query('owner'); $from = $r->query('from'); $to = $r->query('to'); $gap = $r->boolean('gap');
    $sup = DB::table('suppliers')->orderBy('name')->get(['id', 'name', 'business_name', 'contact_person', 'phone', 'whatsapp', 'active']);
    // every supplier number -> its inbox conversation, in one query
    $num = []; foreach ($sup as $s) foreach (SC::numbers($s) as $wa) $num[$wa][] = (int)$s->id;
    $th = $num ? DB::table('chat_threads')->where('channel', 'whatsapp')->whereIn('external_id', array_map('strval', array_keys($num)))->get(['id', 'external_id', 'assigned_to', 'last_at', 'last_message', 'last_dir', 'awaiting_reply', 'status']) : collect();
    $by = []; foreach ($th as $t) foreach ($num[$t->external_id] ?? [] as $sid) { if (!isset($by[$sid]) || (string)$t->last_at > (string)$by[$sid]->last_at) $by[$sid] = $t; }
    $portal = []; try { $portal = DB::table('supplier_messages')->groupBy('supplier_id')->selectRaw('supplier_id, max(created_at) last_at, count(*) n')->get()->keyBy('supplier_id')->all(); } catch (\Throwable $e) {}
    $users = DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name'])->keyBy('id');
    $dq = preg_replace('/\D/', '', $q); $dqa = $dq !== '' && str_starts_with($dq, '0') ? '88' . $dq : $dq;
    $rows = [];
    foreach ($sup as $s) {
      $t = $by[$s->id] ?? null; $p = $portal[$s->id] ?? null; $last = max((string)($t->last_at ?? ''), (string)($p->last_at ?? '')); $gaps = SC::gaps($s);
      if ($q !== '') { $hay = mb_strtolower($s->name . ' ' . ($s->business_name ?? '') . ' ' . ($s->contact_person ?? '')); $nums = preg_replace('/\D/', '', ($s->phone ?? '') . ' ' . ($s->whatsapp ?? '')) . ' ' . implode(' ', SC::numbers($s));
        if (!str_contains($hay, mb_strtolower($q)) && !($dq !== '' && strlen($dq) >= 3 && (str_contains($nums, $dq) || str_contains($nums, $dqa)))) continue; }
      if ($owner === 'none') { if (!$t || $t->assigned_to) continue; } elseif ($owner !== null && $owner !== '') { if (!$t || (int)$t->assigned_to !== (int)$owner) continue; }
      if ($from && ($last === '' || \Carbon\Carbon::parse($last, 'UTC')->timezone('Asia/Dhaka')->toDateString() < $from)) continue;
      if ($to && ($last === '' || \Carbon\Carbon::parse($last, 'UTC')->timezone('Asia/Dhaka')->toDateString() > $to)) continue;
      if ($gap && !$gaps) continue;
      $rows[] = ['s' => $s, 't' => $t, 'last' => $last, 'gaps' => $gaps, 'owner' => $t && $t->assigned_to ? ($users[$t->assigned_to]->name ?? 'Staff #' . $t->assigned_to) : null, 'portal' => (int)($p->n ?? 0)];
    }
    usort($rows, fn($a, $b) => strcmp($b['last'], $a['last']) ?: strcasecmp($a['s']->name, $b['s']->name));
    return view('supplier_comm', ['off' => false, 'rows' => $rows, 'total' => $sup->count(), 'users' => $users, 'f' => compact('q', 'owner', 'from', 'to', 'gap')]);
  }
  public function show(int $id) {
    abort_unless(SC::ready(), 404); $s = DB::table('suppliers')->find($id); abort_unless($s, 404);
    $threads = SC::threads($s); $t = $threads->first(); $me = auth()->user(); $own = $t && ChatOwner::ready() ? ChatOwner::ownerId((int)$t->id) : null;
    return view('supplier_comm_show', ['s' => $s, 'threads' => $threads, 't' => $t, 'tl' => SC::timeline($s, $threads), 'gaps' => SC::gaps($s), 'numbers' => SC::numbers($s),
      'log' => DB::table('supplier_contact_log')->where('supplier_id', $id)->orderByDesc('id')->limit(60)->get(),
      'owner' => ['id' => $own, 'name' => $own ? ChatOwner::name($own) : null, 'mine' => $own !== null && $own === (int)$me->id, 'supervisor' => ChatOwner::supervisor($me)],
      'events' => $t && ChatOwner::ready() ? ChatOwner::events((int)$t->id, 30) : [], 'users' => DB::table('users')->where('active', true)->orderBy('name')->get(['id', 'name']),
      'bot' => $t ? !($t->ai_paused ?? false) && $own === null : false]);
  }
  public function contact(Request $r, int $id) {
    abort_unless(SC::ready(), 404); $s = DB::table('suppliers')->find($id); abort_unless($s, 404);
    $d = $r->validate(['name' => 'required|string|max:200', 'business_name' => 'nullable|string|max:200', 'contact_person' => 'nullable|string|max:160', 'phone' => 'nullable|string|max:30', 'whatsapp' => 'nullable|string|max:30']);
    foreach (['phone' => 'Mobile', 'whatsapp' => 'WhatsApp'] as $f => $lab) if (!SC::valid($d[$f] ?? '')) return back()->withErrors([$f => $lab . ' number is not a valid mobile number (01XXXXXXXXX). Nothing was saved.']);
    $n = SC::saveContact($s, $d, auth()->user());
    return back()->with('ok', $n ? $n . ' field(s) updated. The change is in the history below.' : 'Nothing changed.');
  }
  /** Internal note on the supplier. Staff only: it is stored apart from messages and no code path sends it. */
  public function note(Request $r, int $id) {
    abort_unless(SC::ready(), 404); abort_unless(DB::table('suppliers')->where('id', $id)->exists(), 404);
    $d = $r->validate(['body' => 'required|string|max:1500']); $b = trim($d['body']); if ($b === '') return back()->withErrors(['body' => 'Write the note first.']);
    DB::table('supplier_comm_notes')->insert(['supplier_id' => $id, 'body' => $b, 'user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? '', 'created_at' => now(), 'updated_at' => now()]);
    return back()->with('ok', 'Internal note saved. It is not sent to the supplier.');
  }
  /** An inbox attachment, only for a signed-in staff member and only when the message belongs to this supplier's conversation. */
  public function att(int $id, int $mid) {
    abort_unless(SC::ready(), 404); $s = DB::table('suppliers')->find($id); abort_unless($s, 404);
    $tids = SC::threads($s)->pluck('id')->all(); $m = $tids ? DB::table('chat_messages')->where('id', $mid)->whereIn('thread_id', $tids)->first(['attachment_url']) : null; abort_unless($m && $m->attachment_url, 404);
    $path = parse_url((string)$m->attachment_url, PHP_URL_PATH) ?: ''; abort_unless(str_starts_with($path, '/uploads/chat/') && !str_contains($path, '..'), 404);
    $abs = public_path(ltrim($path, '/')); abort_unless(is_file($abs), 404);
    return response()->file($abs, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
  }
}
