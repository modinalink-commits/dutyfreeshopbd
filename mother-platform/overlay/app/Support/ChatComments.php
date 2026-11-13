<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * AI Comment Reply: public comments on our Facebook Page posts are stored and, when the owner switched it on (Chat Settings), answered by the AI.
 * Rules: one short public reply per comment; facts only from the catalogue (same AI as the inbox); ordering and private details are moved to the inbox;
 * tobacco / vape is NEVER discussed in public (platform rules) - the customer is invited to the inbox instead; spam, abuse and complaints are left for staff.
 * Needs the Page's "feed" webhook subscription and the Page token permission to manage comments; without them nothing arrives / the reply fails and is logged.
 */
class ChatComments {
  const INBOX = 'বিস্তারিত জানতে আমাদের ইনবক্সে মেসেজ করুন, ধন্যবাদ। 🙏';
  public static function ready(): bool { try { return Schema::hasTable('chat_comments'); } catch (\Throwable $e) { return false; } }
  public static function on(): bool { return Sms::secret('ai_comment_reply') === '1'; }

  /** Webhook "feed" change. Returns the stored row id when this is a new customer comment. */
  public static function ingest(string $pageId, array $v, string $channel = 'facebook'): ?int {
    try {
      if (!self::ready() || ($v['item'] ?? '') !== 'comment' || ($v['verb'] ?? '') !== 'add') return null;
      $cid = (string)($v['comment_id'] ?? ''); $msg = trim((string)($v['message'] ?? '')); $from = (string)($v['from']['id'] ?? '');
      if ($cid === '' || $from === '' || $from === $pageId) return null; // our own comment / reply
      if (DB::table('chat_comments')->where('comment_id', $cid)->exists()) return null;
      return (int)DB::table('chat_comments')->insertGetId(['channel' => $channel, 'page_ref' => $pageId, 'post_id' => (string)($v['post_id'] ?? '') ?: null, 'comment_id' => $cid, 'parent_id' => (string)($v['parent_id'] ?? '') ?: null,
        'from_id' => $from, 'from_name' => mb_substr((string)($v['from']['name'] ?? ''), 0, 120), 'message' => mb_substr($msg, 0, 2000), 'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) { report($e); return null; }
  }
  private static function done(int $id, string $status, ?string $reply = null, ?string $note = null): void {
    DB::table('chat_comments')->where('id', $id)->update(['status' => $status, 'reply_text' => $reply, 'note' => $note ? mb_substr($note, 0, 200) : null, 'replied_at' => $status === 'replied' ? now() : null, 'updated_at' => now()]);
  }
  private static function postText(string $postId, string $tok): string {
    if ($postId === '') return '';
    return (string)Cache::remember('cc.post.' . md5($postId), 86400, function () use ($postId, $tok) {
      try { $r = Http::timeout(8)->get(Chat::API . '/' . $postId, ['fields' => 'message', 'access_token' => $tok]); return $r->ok() ? mb_substr(trim((string)$r->json('message')), 0, 500) : ''; } catch (\Throwable $e) { return ''; }
    });
  }
  public static function isSmoke(string $t): bool { return (bool)preg_match('/(সিগারেট|সিগারেট|বিড়ি|তামাক|ভেপ|ভ্যাপ|কার্টন|cigar|sigar|sigaret|tobacco|vape|vap\b|nicotine|carton|benson|marlboro|dunhill|gold ?leaf|esse\b|camel\b|davidoff)/iu', $t); }

  /** Answer one stored comment (once). */
  public static function reply(int $id): void {
    try {
      if (!self::ready() || !self::on() || !AiChat::chatOn() || !AiSchedule::activeNow()) return;
      $c = DB::table('chat_comments')->find($id); if (!$c || $c->status !== 'new') return;
      if (!Cache::add('cc.lock.' . $id, 1, 600)) return;
      $msg = trim((string)$c->message);
      if (mb_strlen($msg) < 2) { self::done($id, 'skipped', null, 'no text (sticker / photo / tag)'); return; }
      $d = 'cc.n.' . now()->format('Ymd'); Cache::add($d, 0, 90000); if (Cache::increment($d) > 400) { self::done($id, 'skipped', null, 'daily limit'); return; }
      $tok = Chat::pageTok($c->channel, $c->page_ref); if (!$tok) { self::done($id, 'failed', null, 'Page token missing (Chat Settings)'); return; }
      $post = self::postText((string)$c->post_id, $tok);
      if (self::isSmoke($msg . ' ' . $post)) $text = self::INBOX; // never discuss tobacco / vape in public
      else {
        $extra = "\nPUBLIC COMMENT MODE: you are answering a PUBLIC comment under our Facebook post, everyone can read it. Write ONE short friendly reply (max 2 short sentences). The comment usually refers to the product in the POST text. "
          . "Give the price or availability only if it is in your data; otherwise invite the person to message our inbox. Never ask for or repeat a phone number, address or order number in public. For ordering say: inbox us. "
          . "Never mention cigarettes, tobacco or vape here. If the comment is spam, abuse, a complaint, only a tag of another person, or needs no answer, reply with exactly: SKIP\n";
        [$ok, $text] = AiChat::reply([['role' => 'user', 'content' => ($post !== '' ? "[POST: " . $post . "]\n" : '') . 'COMMENT: ' . mb_substr($msg, 0, 500)]], $extra);
        if (!$ok) { DB::table('chat_comments')->where('id', $id)->update(['note' => 'AI: ' . mb_substr((string)$text, 0, 150), 'updated_at' => now()]); Cache::forget('cc.lock.' . $id); return; }
        $text = trim((string)$text);
        if ($text === '' || preg_match('/^\W*SKIP\W*$/i', $text)) { self::done($id, 'skipped', null, 'left for staff (not a question / complaint / spam)'); return; }
        if (self::isSmoke($text)) $text = self::INBOX;
        $text = mb_substr(preg_replace('/\s*\n+\s*/u', ' ', $text), 0, 500);
      }
      $r = Http::timeout(15)->asForm()->post(Chat::API . '/' . $c->comment_id . '/comments?access_token=' . urlencode($tok), ['message' => $text]);
      if ($r->ok()) self::done($id, 'replied', $text, null);
      else self::done($id, 'failed', $text, 'HTTP ' . $r->status() . ' ' . mb_substr((string)($r->json('error.message') ?? ''), 0, 150));
    } catch (\Throwable $e) { report($e); }
  }
}
