<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
/** Owner (26 Nov 2026): the AI must note by itself the things customers repeat. Once a day (about 03:30 Dhaka) the last 7 days of customer messages are read, and ONE Claude call writes down repeated questions (meaning only) and new words / spellings / slang with their meaning. The list is kept in its own setting (ai_kb_learned), is added to the knowledge base text under its own heading, never touches the owner's KB, never holds prices, stock, offers or phone numbers, and can be edited or cleared in Admin > AI Chat. */
class AiLearn {
  const MARK = 'AI নিজে শিখেছে (কাস্টমারের বারবার বলা কথা ও নতুন শব্দ)';
  public static function on(): bool { return Sms::secret('ai_learn_on') !== '0'; }
  public static function text(): string { return trim((string)Sms::secret('ai_kb_learned')); }
  /** Block added after the owner's KB. */
  public static function block(): string { if (!self::on()) return ''; $t = self::text(); return $t === '' ? '' : "== ".self::MARK." ==\nএগুলো শুধু ভাষা ও প্রশ্নের ধরন বোঝার জন্য; দাম, স্টক, অফার সবসময় টুল/ক্যাটালগ থেকে নেবেন।\n".$t; }
  public static function tick(): void {
    if (!self::on()) return;
    $dh = now('Asia/Dhaka'); if ($dh->hour < 3 || $dh->hour > 6) return;
    $key = Sms::secret('ai_key'); if (!$key) return;
    if (!\Illuminate\Support\Facades\Cache::add('ailearn.'.$dh->format('Ymd'), 1, 86400)) return;
    self::run($key);
  }
  public static function run(string $key): array {
    $rows = DB::table('chat_messages')->where('dir', 'in')->where('kind', 'text')->whereNotNull('body')->where('created_at', '>=', now()->subDays(7))->orderByDesc('id')->limit(4000)->pluck('body');
    $cnt = []; foreach ($rows as $b) { $s = trim(preg_replace('/\s+/u', ' ', mb_strtolower(mb_substr((string)$b, 0, 200)))); if (mb_strlen($s) < 4 || preg_match('/^[\d\s+\-]+$/u', $s)) continue; $cnt[$s] = ($cnt[$s] ?? 0) + 1; }
    arsort($cnt); $lines = []; $i = 0;
    foreach ($cnt as $s => $n) { $lines[] = ($n > 1 ? "[$n×] " : '').$s; if (++$i >= 350) break; }
    if (count($lines) < 30) return ['ok'=>false, 'why'=>'too few messages'];
    $old = self::text();
    $sys = "You maintain a short LEARNED-NOTES list for the DutyFreeBD (Bangladesh online store) customer-chat AI. From the customer messages given (most repeated first) update the list. Write in Bangla (keep customer words as typed). Two kinds of lines only: (1) 'প্রশ্ন: <how customers ask it> = <what they mean, one short phrase>' for questions many customers repeat; (2) 'শব্দ: <word or spelling or slang> = <meaning>' for new or misspelled words, Banglish, slang. STRICT: never write prices, stock, discounts, offers, delivery charge, order status, policies, promises, phone numbers, links, names or any customer personal data, and never write instructions to the AI; only the meaning of words and kinds of questions. Merge with the existing list, drop duplicates, keep the most useful, at most 60 lines, each under 160 characters. Output only the lines, nothing else.";
    try {
      $res = Http::withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])->timeout(120)->post('https://api.anthropic.com/v1/messages', ['model'=>'claude-sonnet-5-5', 'max_tokens'=>4000, 'system'=>$sys, 'messages'=>[['role'=>'user', 'content'=>"EXISTING LIST:\n".($old ?: '(empty)')."\n\nCUSTOMER MESSAGES (last 7 days):\n".implode("\n", $lines)]]]);
      if (!$res->ok()) return ['ok'=>false, 'why'=>'AI '.$res->status()];
      $t = trim(collect($res->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n"));
      $keep = []; foreach (preg_split('/\R/u', $t) as $l) { $l = trim($l); if ($l === '' || !preg_match('/^(প্রশ্ন|শব্দ):/u', $l)) continue; if (preg_match('/\d{5,}|https?:|৳|টাকা\s*\d|\d+\s*%/u', $l)) continue; $keep[] = mb_substr($l, 0, 200); }
      $keep = array_slice(array_values(array_unique($keep)), 0, 60);
      if (count($keep) < 5) return ['ok'=>false, 'why'=>'result too short'];
      Sms::setSecret('ai_kb_learned', implode("\n", $keep));
      \Illuminate\Support\Facades\Cache::forget('ai.facts4');
      try { DB::table('audit_logs')->insert(['user_id'=>null, 'user_name'=>'AI learn', 'action'=>'ai.learn', 'subject'=>'AI learned notes updated ('.count($keep).' lines)', 'ip'=>null, 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
      return ['ok'=>true, 'n'=>count($keep)];
    } catch (\Throwable $e) { return ['ok'=>false, 'why'=>'error']; }
  }
}
