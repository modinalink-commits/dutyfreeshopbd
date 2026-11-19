<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * AI order-confirmation call (owner spec 19 Nov 2026).
 * Reuses the existing Voice PBX path (Event Socket originate, text-to-speech audio, key-press webhook). Nothing new on the PBX side.
 *  Key 1 = customer confirmed the order, key 2 = customer asks to keep it pending. An answered call alone is NOT a confirmation.
 *  Modes (setting ocv_mode): off (default) | assist (call and record evidence only) | gate (an eligible order is not auto-approved until key 1 or a staff call).
 *  ocv_test_only = 1 (default): only the test phone list is ever called, no real customer.
 *  The confirmation belongs to the order version heard (phone, total, paid, items). A changed order needs a new confirmation.
 *  Staff calls stay separate (call_logs); this class never merges the two sources.
 */
class OrderCall
{
  const TEST_DEFAULT = '01884808080';

  public static function set(string $k, string $d = ''): string { return Voice::set('ocv_' . $k, $d); }
  public static function put2(string $k, string $v): void { Voice::put('ocv_' . $k, $v); }
  public static function ready(): bool { try { return Schema::hasTable('order_call_verifications'); } catch (\Throwable $e) { return false; } }
  public static function mode(): string { $m = self::set('mode', 'off'); return in_array($m, ['assist', 'gate'], true) ? $m : 'off'; }
  public static function testOnly(): bool { return self::set('test_only', '1') !== '0'; }
  public static function testPhones(): array { return array_values(array_filter(array_map(fn($p) => Voice::norm($p), preg_split('/[\s,;]+/', self::set('test_phones', self::TEST_DEFAULT)) ?: []))); }
  public static function pbxReady(): bool {
    return (Voice::set('esl_host') !== '' || env('PBX_ESL_HOST')) && (Sms::secret('vc_esl_pass') || env('PBX_ESL_PASSWORD')) && Voice::set('gateway') !== ''
      && Voice::set('script_ok') === '1' && Voice::set('direct', '0') !== '1' && Voice::set('tts_provider', 'none') !== 'none' && (bool)Sms::secret('vc_tts_key');
  }
  public static function readiness(): array {
    return [
      ['PBX connection and gateway', (Voice::set('esl_host') !== '' || (bool)env('PBX_ESL_HOST')) && (bool)(Sms::secret('vc_esl_pass') || env('PBX_ESL_PASSWORD')) && Voice::set('gateway') !== '', 'Set in Marketing → Voice Campaigns → Settings'],
      ['PBX key-press script installed (needed to hear key 1 / 2)', Voice::set('script_ok') === '1', 'Same voice_offer script, tick "installed" in Voice settings'],
      ['Key-press mode (not "direct play")', Voice::set('direct', '0') !== '1', 'Turn off "direct" in Voice settings, direct mode cannot read key presses'],
      ['Bangla voice (text-to-speech)', Voice::set('tts_provider', 'none') !== 'none' && (bool)Sms::secret('vc_tts_key'), 'Voice settings → text-to-speech provider and key'],
    ];
  }

  // ---------------- order version and eligibility
  public static function version(object $o, $items = null): string {
    $items = $items ?? DB::table('order_items')->where('order_id', $o->id)->get();
    return md5(json_encode([Voice::norm((string)$o->phone), round((float)$o->total, 2), round((float)($o->paid_total ?? 0), 2), collect($items)->map(fn($i) => [$i->product_id, (int)$i->qty, round((float)$i->price, 2)])->sortBy(fn($x) => json_encode($x))->values()->all()]));
  }
  /** null = the order may be called; otherwise the reason it may not. */
  public static function eligible(object $o): ?string {
    if ($o->status !== 'New') return 'order is not waiting for approval';
    if (!empty($o->duplicate_of_order_id)) return 'duplicate order';
    $p = Voice::norm((string)$o->phone); if (!Voice::validPhone($p)) return 'invalid phone';
    if ((float)$o->total <= 0) return 'no amount';
    $src = trim(self::set('source', 'Web Site')); if ($src !== '' && strcasecmp((string)($o->source ?? ''), $src) !== 0) return 'order source is not ' . $src;
    if (self::testOnly() && !in_array($p, self::testPhones(), true)) return 'test-only mode: phone is not in the test list';
    if (!empty($o->customer_id)) { $cu = DB::table('customers')->find($o->customer_id); if ($cu && !empty($cu->blocked)) return 'customer is blocked'; }
    return null;
  }
  /** A staff call answered by this customer around this order (call_logs), AI calls excluded. */
  public static function staffCall(object $o): ?object {
    try {
      if (!Schema::hasTable('call_logs')) return null; $p = Voice::norm((string)$o->phone); if (!Voice::validPhone($p)) return null;
      $c = DB::table('call_logs')->whereRaw('right(phone,11) = ?', [$p])->where('status', 'answered')->where(fn($q) => $q->where('order_id', $o->id)->orWhere('started_at', '>=', \Carbon\Carbon::parse($o->created_at)->subHours(2)))->orderByDesc('id')->limit(5)->get();
      foreach ($c as $x) if (!self::isAiCall($p, $x->started_at)) return $x;
    } catch (\Throwable $e) {}
    return null;
  }
  public static function isAiCall(string $phone, $at): bool {
    if (!self::ready() || !$at) return false;
    try { $t = \Carbon\Carbon::parse($at); return DB::table('order_call_verifications')->where('phone', Voice::norm($phone))->whereBetween('started_at', [$t->copy()->subMinutes(6), $t->copy()->addMinutes(6)])->exists(); } catch (\Throwable $e) { return false; }
  }

  // ---------------- state shown in AI Verification
  /** Latest AI-call state for this order's current version: ['state','row','at'] or null. */
  public static function state(object $o): ?array {
    if (!self::ready()) return null;
    try {
      $ver = self::version($o); $rows = DB::table('order_call_verifications')->where('order_id', $o->id)->orderByDesc('id')->get(); if (!$rows->count()) return null;
      $cur = $rows->where('version_hash', $ver); $hit = $cur->first(fn($r) => in_array($r->status, ['confirmed', 'pending_requested'], true));
      if ($hit) return ['state' => $hit->status, 'row' => $hit, 'at' => $hit->ended_at ?: $hit->updated_at, 'attempts' => $cur->count()];
      $last = $cur->first(); if ($last) return ['state' => $last->status, 'row' => $last, 'at' => $last->updated_at, 'attempts' => $cur->count()];
      return ['state' => 'stale', 'row' => $rows->first(), 'at' => $rows->first()->updated_at, 'attempts' => 0];
    } catch (\Throwable $e) { return null; }
  }
  /** Gate mode: an order that may be called is not auto-approved until key 1 (or a staff call) has confirmed it. */
  public static function blocksApproval(object $o): bool {
    try {
      if (self::mode() !== 'gate' || !self::ready() || self::eligible($o) !== null) return false;
      $s = self::state($o); if ($s && $s['state'] === 'confirmed') return false; if (self::staffCall($o)) return false; return true;
    } catch (\Throwable $e) { return false; }
  }

  // ---------------- script
  private static function money(float $v): string { return Voice::bn(number_format(round($v), 0, '', '')); }
  public static function script(object $o, $items): string {
    $inv = preg_replace('/\D/', '', (string)$o->invoice); $last = Voice::bn(substr($inv !== '' ? $inv : (string)$o->id, -4));
    $names = collect($items)->map(fn($i) => trim((string)$i->name) . ' ' . Voice::bn((string)(int)$i->qty) . 'টি')->values();
    $list = $names->take(3)->implode(', ') . ($names->count() > 3 ? ' এবং আরও ' . Voice::bn((string)($names->count() - 3)) . 'টি পণ্য' : '');
    $total = (float)$o->total; $paid = (float)($o->paid_total ?? 0); $due = max(0, $total - $paid);
    $s = 'আসসালামু আলাইকুম। আমি ডিউটি ফ্রি বিডির স্বয়ংক্রিয় অর্ডার সহকারী। আপনার অর্ডার নম্বরের শেষ চার সংখ্যা ' . $last . '। আপনি ' . $list . ' অর্ডার করেছেন। ';
    if ($paid > 0) $s .= 'ডেলিভারি চার্জসহ মোট ' . self::money($total) . ' টাকা। যাচাই করা পরিশোধ ' . self::money($paid) . ' টাকা। বাকি ' . self::money($due) . ' টাকা। ';
    else $s .= 'ডেলিভারি চার্জসহ মোট ' . self::money($total) . ' টাকা। পণ্য গ্রহণের সময় দিতে হবে ' . self::money($due) . ' টাকা। ';
    return $s . 'সব তথ্য ঠিক থাকলে অর্ডারটি নিশ্চিত করতে ১ চাপুন। তথ্য ঠিক না থাকলে বা আমাদের সঙ্গে কথা বলতে চাইলে অর্ডারটি পেন্ডিং রাখতে ২ চাপুন।';
  }
  public static function testScript(): string {
    return 'আসসালামু আলাইকুম। আমি ডিউটি ফ্রি বিডির স্বয়ংক্রিয় অর্ডার সহকারী। এটি একটি পরীক্ষামূলক কল। আপনার অর্ডার নম্বরের শেষ চার সংখ্যা ০০০০। আপনি নমুনা পণ্য ১টি অর্ডার করেছেন। ডেলিভারি চার্জসহ মোট ১২৩৪ টাকা। পণ্য গ্রহণের সময় দিতে হবে ১২৩৪ টাকা। সব তথ্য ঠিক থাকলে অর্ডারটি নিশ্চিত করতে ১ চাপুন। তথ্য ঠিক না থাকলে বা আমাদের সঙ্গে কথা বলতে চাইলে অর্ডারটি পেন্ডিং রাখতে ২ চাপুন।';
  }

  // ---------------- calling
  private static function place(string $phone, string $text, ?int $orderId, ?string $ver, int $attempt, bool $test): array {
    if (!self::pbxReady()) return ['failed', 'PBX call setup is not complete (see readiness list)', null];
    $id = DB::table('order_call_verifications')->insertGetId(['order_id' => $orderId, 'version_hash' => $ver, 'attempt' => $attempt, 'phone' => $phone, 'status' => 'calling', 'started_at' => now(), 'test' => $test, 'created_at' => now(), 'updated_at' => now()]);
    $c = (object)['id' => 0, 'pbx_audio' => null, 'audio_path' => null, 'dtmf_on' => 1, 'script' => $text, 'expiry_date' => null, 'validity_days' => 3];
    $row = (object)['id' => $id, 'phone' => $phone, 'product_name' => '', 'discount_value' => 0, 'name' => ''];
    try { [$st, $cause, $uuid] = Voice::originate($c, $row); } catch (\Throwable $e) { [$st, $cause, $uuid] = ['failed', 'Call could not start', '']; Log::warning('order call originate failed: ' . $e->getMessage()); }
    $max = max(1, (int)self::set('max_attempts', '2')); $retry = !$test && $attempt < $max && in_array($st, ['not_answered', 'busy', 'failed'], true);
    $new = $st === 'answered' ? 'calling' : ($st === 'not_answered' ? 'no_answer' : $st);
    DB::table('order_call_verifications')->where('id', $id)->update(['pbx_call_id' => $uuid ?: null, 'status' => $new, 'cause' => $new === 'calling' ? null : mb_substr((string)$cause, 0, 240), 'next_attempt_at' => $retry ? now()->addMinutes(max(10, (int)self::set('retry_gap', '60'))) : null, 'ended_at' => $new === 'calling' ? null : now(), 'updated_at' => now()]);
    return [$new, (string)$cause, $id];
  }
  /** Owner test button: a sample call to a number in the test list. No order is touched. */
  public static function testCall(string $phone): array {
    $p = Voice::norm($phone); if (!in_array($p, self::testPhones(), true)) return ['failed', 'This number is not in the test list.', null];
    return self::place($p, self::testScript(), null, null, 1, true);
  }
  private static function dial(object $o, string $ver, int $attempt): void {
    $items = DB::table('order_items')->where('order_id', $o->id)->get(); $p = Voice::norm((string)$o->phone);
    [$st, $cause, $id] = self::place($p, self::script($o, $items), (int)$o->id, $ver, $attempt, in_array($p, self::testPhones(), true));
    if ($id) OrderActivity::log((int)$o->id, 'ai_call', null, $st, 'AI confirmation call, attempt ' . $attempt . ': ' . $st . ($st === 'failed' ? ' (' . mb_substr($cause, 0, 80) . ')' : ''), ['call_row' => $id]);
  }

  /** Worker tick (every cycle): close stuck calls, then call what is due. */
  public static function tick(): void {
    if (self::mode() === 'off' || !self::ready() || !self::pbxReady()) return;
    $lock = Cache::lock('ocv_tick', 90); if (!$lock->get()) return;
    try {
      $max = max(1, (int)self::set('max_attempts', '2')); $gap = max(10, (int)self::set('retry_gap', '60'));
      foreach (DB::table('order_call_verifications')->whereIn('status', ['calling', 'answered'])->where('updated_at', '<', now()->subMinutes(4))->get() as $r) {
        $st = $r->status === 'answered' ? 'no_input' : 'no_answer';
        DB::table('order_call_verifications')->where('id', $r->id)->update(['status' => $st, 'ended_at' => now(), 'next_attempt_at' => (!$r->test && $r->attempt < $max) ? now()->addMinutes($gap) : null, 'updated_at' => now()]);
      }
      $t = now('Asia/Dhaka')->format('H:i'); if ($t < self::set('start', '09:00') || $t >= self::set('end', '21:00')) return;
      $active = DB::table('order_call_verifications')->whereIn('status', ['calling', 'answered'])->where('updated_at', '>', now()->subMinutes(4))->count();
      $cap = max(1, (int)self::set('concurrency', '2')); if ($active >= $cap) return; $t0 = time();
      $orders = DB::table('orders')->where('status', 'New')->where('created_at', '>=', now()->subHours(max(1, (int)self::set('hours_back', '24'))))->orderBy('id')->limit(40)->get();
      foreach ($orders as $o) {
        if (time() - $t0 > 45 || $active >= $cap) return;
        if (self::eligible($o) !== null) continue; $ver = self::version($o);
        $rows = DB::table('order_call_verifications')->where('order_id', $o->id)->where('version_hash', $ver)->orderByDesc('id')->get();
        if ($rows->contains(fn($r) => in_array($r->status, ['confirmed', 'pending_requested'], true))) continue;
        if ($rows->contains(fn($r) => in_array($r->status, ['calling', 'answered'], true))) continue;
        $n = $rows->count(); if ($n >= $max) continue; $last = $rows->first();
        if ($last && (!$last->next_attempt_at || \Carbon\Carbon::parse($last->next_attempt_at)->isFuture())) continue;
        if ($n === 0 && $o->created_at && \Carbon\Carbon::parse($o->created_at)->gt(now()->subSeconds(90))) continue; // let the order settle first
        if (self::staffCall($o)) continue;
        if (DB::table('order_call_verifications')->where('phone', Voice::norm((string)$o->phone))->whereIn('status', ['calling', 'answered'])->where('updated_at', '>', now()->subMinutes(4))->exists()) continue;
        self::dial($o, $ver, $n + 1); $active++;
      }
    } finally { $lock->release(); }
  }

  // ---------------- PBX webhook (called from Voice::hook before the campaign lookup)
  public static function hook(array $d): ?array {
    if (!self::ready()) return null; $uuid = (string)($d['uuid'] ?? $d['call_id'] ?? ''); if ($uuid === '') return null;
    $a = DB::table('order_call_verifications')->where('pbx_call_id', $uuid)->orderByDesc('id')->first(); if (!$a) return null;
    $log = json_decode((string)($a->webhook_payload ?? ''), true) ?: []; $log[] = ['at' => now()->toIso8601String(), 'data' => $d]; $log = array_slice($log, -30);
    DB::table('order_call_verifications')->where('id', $a->id)->update(['webhook_payload' => json_encode($log, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    $ev = (string)($d['ev'] ?? $d['event'] ?? '');
    if ($ev === 'answered') { if ($a->status === 'calling') DB::table('order_call_verifications')->where('id', $a->id)->update(['status' => 'answered', 'answered_at' => now(), 'updated_at' => now()]); }
    elseif ($ev === 'digit') {
      $g = substr((string)($d['digit'] ?? ''), 0, 1); if (!in_array($a->status, ['calling', 'answered', 'no_input'], true)) return ['ok' => true];
      DB::table('order_call_verifications')->where('id', $a->id)->update(['digit' => $g, 'updated_at' => now()]);
      if ($g === '1' || $g === '2') self::apply($a, $g);
    } elseif ($ev === 'hangup') {
      $dur = max(0, (int)($d['duration'] ?? 0)); $rec = ($d['recording'] ?? null) ? mb_substr((string)$d['recording'], 0, 250) : null; $max = max(1, (int)self::set('max_attempts', '2'));
      $upd = ['duration' => $dur, 'ended_at' => now(), 'recording_ref' => $rec ?: $a->recording_ref, 'updated_at' => now()];
      if (in_array($a->status, ['calling', 'answered'], true)) { $upd['status'] = $a->status === 'answered' ? 'no_input' : 'no_answer'; $upd['next_attempt_at'] = (!$a->test && $a->attempt < $max) ? now()->addMinutes(max(10, (int)self::set('retry_gap', '60'))) : null; }
      DB::table('order_call_verifications')->where('id', $a->id)->update($upd);
    }
    return ['ok' => true];
  }
  /** Key 1 or 2 pressed. Idempotent; the order version heard must still be the current version. */
  private static function apply(object $a, string $g): void {
    $upd = ['ended_at' => now(), 'updated_at' => now()];
    if (!$a->order_id) { DB::table('order_call_verifications')->where('id', $a->id)->update($upd + ['status' => $g === '1' ? 'confirmed' : 'pending_requested']); return; } // owner test call
    $o = DB::table('orders')->find($a->order_id); if (!$o) { DB::table('order_call_verifications')->where('id', $a->id)->update($upd + ['status' => 'failed', 'cause' => 'order not found']); return; }
    if ($a->version_hash !== self::version($o)) {
      DB::table('order_call_verifications')->where('id', $a->id)->update($upd + ['status' => 'stale', 'cause' => 'order changed during or after the call; new confirmation needed']);
      OrderActivity::log((int)$o->id, 'ai_call_stale', null, 'stale', 'Customer pressed ' . $g . ' but the order changed after the call: not applied. A new confirmation or staff review is needed.', ['call_row' => $a->id]); return;
    }
    DB::table('order_call_verifications')->where('id', $a->id)->update($upd + ['status' => $g === '1' ? 'confirmed' : 'pending_requested', 'next_attempt_at' => null]);
    OrderActivity::log((int)$o->id, $g === '1' ? 'ai_call_confirmed' : 'ai_call_pending', null, $g === '1' ? 'confirmed' : 'pending', $g === '1' ? 'Customer pressed 1 on the AI call: order confirmed (order status: ' . $o->status . ')' : 'Customer pressed 2 on the AI call: asked to keep the order pending (order status: ' . $o->status . ')', ['call_row' => $a->id]);
    if ($o->status === 'New') { try { AiVerify::run((int)$o->id, 'ai-call', false, true); } catch (\Throwable $e) { report($e); } } // refresh the panel; auto-approval still follows every existing rule
  }

  /** For the settings page. */
  public static function exceptions(int $limit = 40)
  {
    if (!self::ready()) return collect();
    return DB::table('order_call_verifications as v')->leftJoin('orders as o', 'o.id', '=', 'v.order_id')->whereIn('v.status', ['pending_requested', 'no_input', 'no_answer', 'busy', 'failed', 'stale'])->where('v.test', false)->where('o.status', 'New')
      ->orderByDesc('v.id')->limit($limit)->get(['v.*', 'o.invoice', 'o.customer_name', 'o.total']);
  }
}
