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
  const VARIANTS = ['{n}বিস্তারিত জানতে আমাদের ইনবক্সে আসুন, ধন্যবাদ। 🙏', '{n}দাম ও বিস্তারিত জানতে ইনবক্সে মেসেজ করুন, ধন্যবাদ। 🙏', '{n}আমাদের ইনবক্সে মেসেজ দিন, সব তথ্য সেখানে জানিয়ে দেব। ধন্যবাদ। 🙏', '{n}ইনবক্সে আসুন, বিস্তারিত সেখানেই জানাব। ধন্যবাদ। 🙏', '{n}পণ্যের বিস্তারিত জানতে ইনবক্সে আসুন, ধন্যবাদ। 🙏'];
  private static function spam(string $t): bool { return (bool)preg_match('#(https?://|www\.|\.com\b|\.xyz\b|t\.me/|wa\.me/|bit\.ly)#iu', $t); }
  /** One private message from the Page inbox to the commenter (Facebook allows one per comment, within 7 days). Returns a short note for the log. */
  private static function privateMessage(object $c, string $tok, string $nm): string {
    try {
      $m = 'আসসালামু আলাইকুম' . ($nm !== '' ? ' ' . $nm : '') . "। আপনার কমেন্টের জন্য ধন্যবাদ।\nকোন পণ্যের বিস্তারিত জানতে চান? এখানে লিখে জানান, আমরা সাহায্য করব। ধন্যবাদ।";
      $r = Http::timeout(15)->withToken($tok)->post(Chat::API . '/' . $c->comment_id . '/private_replies', ['message' => $m]);
      return $r->ok() ? 'inbox message sent' : 'inbox message failed: ' . mb_substr((string)($r->json('error.message') ?? $r->status()), 0, 120);
    } catch (\Throwable $e) { return 'inbox message error'; }
  }
  public static function isSmoke(string $t): bool { return (bool)preg_match('/(সিগারেট|সিগারেট|বিড়ি|তামাক|ভেপ|ভ্যাপ|কার্টন|cigar|sigar|sigaret|tobacco|vape|vap\b|nicotine|carton|benson|marlboro|dunhill|gold ?leaf|esse\b|camel\b|davidoff)/iu', $t); }

  /** Answer one stored comment (once). */
  public static function reply(int $id): void {
    try {
      if (!self::ready() || !self::on()) return; // owner 15 Nov 2026: comment replies run 24 hours, whatever the AI chat hours are
      $c = DB::table('chat_comments')->find($id); if (!$c || $c->status !== 'new') return;
      if (!Cache::add('cc.lock.' . $id, 1, 600)) return;
      $msg = trim((string)$c->message);
      if (mb_strlen($msg) < 2) { self::done($id, 'skipped', null, 'no text (sticker / photo / tag)'); return; }
      $d = 'cc.n.' . now()->format('Ymd'); Cache::add($d, 0, 90000); if (Cache::increment($d) > 1500) { self::done($id, 'skipped', null, 'daily limit'); return; }
      $tok = Chat::pageTok($c->channel, $c->page_ref); if (!$tok) { self::done($id, 'failed', null, 'Page token missing (Chat Settings)'); return; }
      // Owner rule 15 Nov 2026: the public reply only invites the person to our inbox (no price, no product detail, nothing about tobacco); the details go in a private message from the Page inbox.
      if (self::spam($msg)) { self::done($id, 'skipped', null, 'spam / link (left alone)'); return; }
      $first = trim((string)preg_split('/\s+/u', trim((string)$c->from_name))[0]); $nm = $first !== '' && mb_strlen($first) <= 20 ? $first : '';
      $v = self::VARIANTS; $text = strtr($v[crc32($c->comment_id) % count($v)], ['{n}' => $nm !== '' ? $nm . ', ' : '']);
      $r = Http::timeout(15)->asForm()->post(Chat::API . '/' . $c->comment_id . '/comments?access_token=' . urlencode($tok), ['message' => $text]);
      if ($r->ok()) { self::done($id, 'replied', $text, self::privateMessage($c, $tok, $nm)); }
      else self::done($id, 'failed', $text, 'HTTP ' . $r->status() . ' ' . mb_substr((string)($r->json('error.message') ?? ''), 0, 150));
    } catch (\Throwable $e) { report($e); }
  }
}
