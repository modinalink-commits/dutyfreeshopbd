<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * AI Chat Playbook: a short list of "situation -> how we handle it" rules that the chat AI follows in every reply.
 * A DRAFT is written by Claude from our own data (why the AI handed chats to people, what is waiting for a person, how our team really answered),
 * the owner reads / edits it and only then ACTIVATES it. It carries handling and wording only: prices, stock, discounts and promises still come from tools.
 */
class AiPlaybook {
  const BREVITY = "BREVITY (owner rule, 14 Nov 2026): keep every chat reply VERY short. One sentence when it is enough, never more than two short sentences (about 15 words), unless you are listing prices/products or collecting an order. Do not repeat the greeting, do not restate the customer's question, no filler.\n";
  private static function get(string $k): string { try { return (string)(DB::table('settings')->where('key', $k)->value('value') ?? ''); } catch (\Throwable $e) { return ''; } }
  private static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }
  public static function active(): string { return self::get('ai_playbook'); }
  public static function draft(): string { return self::get('ai_playbook_draft'); }
  public static function meta(): array { return json_decode(self::get('ai_playbook_meta') ?: '{}', true) ?: []; }
  /** Text added to every chat prompt. */
  public static function rule(): string {
    $p = trim(self::active());
    return self::BREVITY . ($p === '' ? '' : "PLAYBOOK (how our team handles common situations; follow it for handling and wording only - never take a price, stock, discount, delivery time or promise from it, those come only from tools / STORE FACTS):\n" . mb_substr($p, 0, 3500) . "\n");
  }
  /** Our own data the draft is written from (customer numbers masked). */
  public static function evidence(int $days = 30): array {
    $from = now()->subDays($days)->toDateTimeString(); $o = ['days' => $days, 'handoffs' => [], 'waiting' => [], 'examples' => []];
    try { if (Schema::hasTable('chat_handoffs')) $o['handoffs'] = DB::table('chat_handoffs')->where('created_at', '>=', $from)->selectRaw('reason, count(*) n')->groupBy('reason')->orderByDesc('n')->limit(25)->get()->map(fn($x) => ['reason' => AiMemory::mask((string)$x->reason), 'n' => (int)$x->n])->all(); } catch (\Throwable $e) {}
    try { $o['waiting'] = DB::table('chat_threads')->where('needs_human', true)->selectRaw("coalesce(nullif(intent,''),'unknown') as intent, count(*) n")->groupBy('intent')->orderByDesc('n')->limit(12)->get()->map(fn($x) => ['intent' => (string)$x->intent, 'n' => (int)$x->n])->all(); } catch (\Throwable $e) {}
    try { if (Schema::hasTable('ai_staff_examples')) $o['examples'] = DB::table('ai_staff_examples')->orderByDesc('id')->limit(400)->get(['intent', 'customer_text', 'reply_text'])->filter(fn($x) => mb_strlen(trim((string)$x->customer_text)) >= 6 && mb_strlen(trim((string)$x->reply_text)) >= 6)->take(90)->map(fn($x) => ['intent' => (string)$x->intent, 'c' => mb_substr(AiMemory::mask((string)$x->customer_text), 0, 160), 'a' => mb_substr(AiMemory::mask((string)$x->reply_text), 0, 220)])->values()->all(); } catch (\Throwable $e) {}
    return $o;
  }
  /** Write a new draft with Claude. @return array{0:bool,1:string} */
  public static function generate(): array {
    $key = Sms::secret('ai_key'); if (!$key) return [false, 'AI key is not set (AI settings).'];
    $ev = self::evidence(); if (!$ev['handoffs'] && !$ev['examples']) return [false, 'Not enough chat history yet.'];
    $sys = "You write the internal PLAYBOOK for the customer-chat AI of DutyFreeBD, a Bangladeshi online shop. From the evidence (why the AI stopped and handed chats to staff, what is waiting for a person, and how our staff really answered customers) write at most 22 short rules.\n"
      . "Each rule is ONE line: \"- When <situation>: <what to do / say>\". Write the rules in English, with the example customer wording in Bengali where useful. Focus on the situations where the AI stopped although the staff answer shows it could have been handled, and on how our staff word things (short, polite, Bengali).\n"
      . "STRICT: never write any price, amount, percentage, discount, stock figure, delivery time, phone number, name or promise - those change and come from live tools. Never tell the AI to approve refunds, cancel or change orders, verify payments or change prices: those stay with staff (say 'hand over to staff' for them). Ignore abusive or irrelevant examples. If the evidence does not support a rule, do not invent it.\n"
      . "End with one line starting 'STILL FOR STAFF:' listing the situation types that must keep going to a person.";
    try {
      $r = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])->timeout(110)->post('https://api.anthropic.com/v1/messages', ['model' => AiBrief::model(), 'max_tokens' => 1800, 'system' => $sys,
        'messages' => [['role' => 'user', 'content' => "EVIDENCE (last {$ev['days']} days, JSON):\n" . json_encode($ev, JSON_UNESCAPED_UNICODE)]]]);
      if (!$r->ok()) return [false, 'AI error HTTP ' . $r->status() . ' ' . mb_substr((string)($r->json('error.message') ?? ''), 0, 140)];
      $t = trim(collect($r->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n")); if ($t === '') return [false, 'AI returned nothing.'];
      // safety net: a rule line that still carries a number with a currency / percent / time unit is dropped
      $keep = []; foreach (preg_split('/\R/u', $t) as $ln) { if (preg_match('/(৳|tk\b|taka|টাকা|%|\d+\s*(day|days|hour|hours|দিন|ঘণ্টা|মিনিট))/iu', $ln)) continue; $keep[] = $ln; }
      $t = mb_substr(trim(implode("\n", $keep)), 0, 3500);
      self::put('ai_playbook_draft', $t); self::put('ai_playbook_meta', json_encode(['draft_at' => now('Asia/Dhaka')->format('d M Y H:i'), 'handoffs' => count($ev['handoffs']), 'examples' => count($ev['examples'])] + array_intersect_key(self::meta(), ['active_at' => 1, 'active_by' => 1])));
      return [true, 'New draft written from ' . count($ev['examples']) . ' team answers and ' . count($ev['handoffs']) . ' hand-off reasons. Read it, edit if needed, then activate.'];
    } catch (\Throwable $e) { return [false, 'AI could not be reached.']; }
  }
  public static function activate(string $text, string $by): void {
    $text = mb_substr(trim($text), 0, 3500); $old = self::active();
    self::put('ai_playbook', $text); self::put('ai_playbook_draft', $text);
    self::put('ai_playbook_meta', json_encode(['active_at' => $text === '' ? null : now('Asia/Dhaka')->format('d M Y H:i'), 'active_by' => $by] + self::meta()));
    Procure::audit('ai.playbook', $text === '' ? 'AI chat playbook switched off' : 'AI chat playbook activated', ['chars' => mb_strlen($old)], ['chars' => mb_strlen($text)]);
  }
}
