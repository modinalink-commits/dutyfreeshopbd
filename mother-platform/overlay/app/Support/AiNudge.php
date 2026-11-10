<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/**
 * Owner rule (9 Nov 2026): when the AI has answered a customer and the customer stays silent, ask ONE short question after 2 minutes
 * ("Sir, what is your decision?"), in the customer's own style (Bangla / Banglish / English). Once per AI reply, never after a hand-off,
 * a thank-you, an invoice, or while staff have the chat. Runs from the scheduler tick (every minute).
 */
class AiNudge {
  const TEXT = [
    'bangla'   => ['স্যার, কী সিদ্ধান্ত নিলেন? অর্ডারটি করে দেব?', 'স্যার, আর কিছু জানার আছে? চাইলে এখনই অর্ডার করে দিই।'],
    'banglish' => ['Sir, ki decision nilen? Order ta kore dibo?', 'Sir, aro kichu jante chan? Chaile ekhoni order kore dei.'],
    'english'  => ['Sir, what would you like to do? Shall I place the order?', 'Sir, anything else you would like to know? I can place the order right away.'],
  ];
  public static function minutes(): int { $m = (int)(DB::table('settings')->where('key', 'ai_nudge_min')->value('value') ?: 2); return max(1, min(30, $m)); }
  public static function on(): bool { return (DB::table('settings')->where('key', 'ai_nudge_on')->value('value') ?? '1') === '1'; }
  public static function style(string $last, ?string $pref = null): string {
    if (AiChat::banglaOnly()) return 'bangla'; // owner rule 10 Nov 2026: the bot speaks clear Bengali only
    if (preg_match('/[\x{0980}-\x{09FF}]/u', $last)) return 'bangla';
    if (preg_match('/\b(ki|koto|dam|daam|ache|achhe|ase|ta|kothay|korbo|korben|lagbe|den|dibo|hobe|amar|apni|apnar|bhai|nibo|nite|chai|jante|pathan|pathaben|kemon|ekhon)\b/i', $last)) return 'banglish';
    if (preg_match('/[a-z]{3,}/i', $last)) return 'english';
    return in_array($pref, ['bangla', 'banglish', 'english'], true) ? $pref : 'bangla';
  }
  public static function tick(): void {
    try {
      if (!self::on() || !AiChat::chatOn() || !AiSchedule::activeNow()) return;
      $min = self::minutes();
      $ts = DB::table('chat_threads')->where('last_dir', 'out')->whereIn('channel', ['web', 'whatsapp', 'facebook'])
        ->where('last_at', '<=', now()->subMinutes($min))->where('last_at', '>=', now()->subMinutes(20))->orderByDesc('last_at')->limit(30)->get();
      foreach ($ts as $t) { try { self::one($t); } catch (\Throwable $e) { report($e); } }
    } catch (\Throwable $e) { report($e); }
  }
  private static function one(object $t): void {
    if (AiChat::paused($t) || !empty($t->needs_human)) return;
    if ($t->channel !== 'web' && !AiChat::chanOn()) return;
    $rows = DB::table('chat_messages')->where('thread_id', $t->id)->orderByDesc('id')->limit(4)->get();
    $last = $rows->first(); if (!$last || $last->dir !== 'out' || !$last->is_ai || $last->kind !== 'text' || $last->status === 'failed') return;
    if ($last->created_at > now()->subMinutes(self::minutes())->toDateTimeString()) return;
    $in = $rows->first(fn($m) => $m->dir === 'in'); if (!$in) return; // the customer must have written first
    $key = 'ai.nudge.'.$last->id; if (!Cache::add($key, 1, 86400 * 3)) return; // one nudge per AI reply
    // never nudge the nudge, and at most one per customer message
    if (Cache::get('ai.nudge.in.'.$in->id)) return; Cache::put('ai.nudge.in.'.$in->id, 1, 86400 * 3);
    $q = trim((string)($in->body !== '' ? $in->body : ($in->transcript ?? '')));
    if ($q !== '' && preg_match('/^(ok|okay|thanks?|thank you|thx|bye|ধন্যবাদ|থ্যাংকস|ঠিক আছে|আচ্ছা|👍|🙏|❤️?)[\s.!]*$/iu', $q)) return;
    if (DB::table('chat_messages')->where('thread_id', $t->id)->where('kind', 'invoice')->where('created_at', '>', now()->subMinutes(60)->toDateTimeString())->exists()) return; // an order was just placed
    if (DB::table('chat_handoffs')->where('thread_id', $t->id)->where('created_at', '>', now()->subMinutes(60))->exists()) return; // staff are taking over
    $body = (string)$last->body;
    if (preg_match('/(ধন্যবাদ|thank)[^?？]*$/iu', mb_substr($body, -60)) && !str_contains($body, '?')) { /* a closing line: still ask, but only once and short */ }
    $set = self::TEXT[self::style($q, (string)($t->ai_lang ?? ''))]; $txt = $set[$last->id % count($set)];
    $sent = true; $err = null;
    if ($t->channel !== 'web') [$sent, $err] = Chat::deliver($t, $txt);
    DB::table('chat_messages')->insert(['thread_id'=>$t->id, 'dir'=>'out', 'kind'=>'text', 'body'=>$txt, 'user_name'=>'Claude AI', 'is_ai'=>true, 'status'=>$sent ? 'sent' : 'failed', 'error'=>$err, 'created_at'=>now(), 'updated_at'=>now()]);
    if ($sent) DB::table('chat_threads')->where('id', $t->id)->update(['last_message'=>$txt, 'last_dir'=>'out', 'last_at'=>now(), 'updated_at'=>now()]);
    // the nudge itself must never be nudged again
    $new = DB::table('chat_messages')->where('thread_id', $t->id)->orderByDesc('id')->value('id'); if ($new) Cache::put('ai.nudge.'.$new, 1, 86400 * 3);
  }
}
