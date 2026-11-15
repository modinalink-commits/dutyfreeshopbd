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
  const MAX_DAYS = 7; // owner 15 Nov 2026: every comment of the last 7 days is answered (also the window Facebook allows for the private message); older ones are left alone
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
      $at = now(); try { $ct = $v['created_time'] ?? null; if ($ct) $at = is_numeric($ct) ? \Carbon\Carbon::createFromTimestamp((int)$ct) : \Carbon\Carbon::parse((string)$ct); if ($at->gt(now())) $at = now(); } catch (\Throwable $e) { $at = now(); }
      return (int)DB::table('chat_comments')->insertGetId(['channel' => $channel, 'page_ref' => $pageId, 'post_id' => (string)($v['post_id'] ?? '') ?: null, 'comment_id' => $cid, 'parent_id' => (string)($v['parent_id'] ?? '') ?: null,
        'from_id' => $from, 'from_name' => mb_substr((string)($v['from']['name'] ?? ''), 0, 120), 'message' => mb_substr($msg, 0, 2000), 'status' => 'new', 'created_at' => $at, 'updated_at' => now()]);
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
  /** Reason string when the text is spam / abuse, else null. Conservative: complaints and plain questions are never matched. */
  public static function bad(string $t): ?string {
    if (self::spam($t)) return 'spam link';
    if (preg_match('/(\bfuck|\bfck\b|\bbitch|\bbastard|\bscam(mer)?\b|\bfraud\b|\bcheat(er|ing)?\b|মাগি|মাগী|খানকি|খানকির|চুদ|চোদ|বাল\b|হারামি|হারামজাদা|কুত্তা|কুকুরের বাচ্চা|শুয়োর|বেশ্যা|ফালতু|বেটা চোর|জালিয়াত|প্রতারক|ভাঁওতাবাজ)/iu', $t)) return 'abusive words';
    return null;
  }
  /** Delete the comment on Facebook (Page token) and mark it 'deleted'. Returns true on success. */
  public static function remove(int $id, string $why, ?int $by = null): bool {
    try {
      $c = DB::table('chat_comments')->find($id); if (!$c || $c->status === 'deleted') return false;
      $tok = Chat::pageTok($c->channel, $c->page_ref); if (!$tok) { self::done($id, 'failed', null, 'Page token missing'); return false; }
      $r = Http::timeout(15)->asForm()->post(Chat::API . '/' . $c->comment_id . '?access_token=' . urlencode($tok), ['_method' => 'DELETE']);
      if ($r->ok() && $r->json('success') !== false) {
        self::done($id, 'deleted', null, 'deleted: ' . $why . ($by ? ' (staff)' : ' (auto)'));
        try { DB::table('audit_logs')->insert(['user_id' => $by, 'user_name' => $by ? (auth()->user()->name ?? '') : 'AI', 'action' => 'chat.comment_delete', 'subject' => 'Comment ' . $c->comment_id . ' removed: ' . $why, 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
        return true;
      }
      self::done($id, 'skipped', null, 'delete failed: HTTP ' . $r->status() . ' ' . mb_substr((string)($r->json('error.message') ?? ''), 0, 120));
    } catch (\Throwable $e) { report($e); }
    return false;
  }
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

  /** Owner request 15 Nov 2026: answer the old comments nobody answered yet. One Page per cycle is read once (posts of the last 14 days, comments with no reply from the Page yet), stored, then answered a few per cycle so no burst goes out. */
  public static function tick(): void {
    try {
      if (!self::ready() || !self::on() || !Cache::add('cc.tick', 1, 50)) return;
      $pages = DB::table('chat_pages')->get(['page_id', 'name']);
      $did = false;
      foreach ($pages as $pg) { if (Cache::has('cc.bf.' . $pg->page_id)) continue; self::backfillPage((string)$pg->page_id); $did = true; break; } // one Page per cycle
      // Recurring poll (owner 15 Nov 2026: ad comments must be answered): every Page is re-read about every 2 minutes for the last 2 days, one Page per cycle, so ad (dark) post comments - which the webhook does not deliver - are picked up too.
      if (!$did) foreach ($pages as $pg) { if (Cache::add('cc.poll.' . $pg->page_id, 1, 120)) { self::backfillPage((string)$pg->page_id, 2, false); break; } }
      foreach (DB::table('chat_comments')->where('channel', 'facebook')->where('status', 'new')->orderBy('id')->limit(3)->pluck('id') as $id) self::reply((int)$id);
    } catch (\Throwable $e) { report($e); }
  }
  /** $record=true: the one-time 14-day read (result kept in 'cc.bf.<page>'). $record=false: the light recurring poll (see tick()), result kept in 'cc.poll.last.<page>' and it never blocks a later poll. */
  public static function backfillPage(string $pageId, int $days = 14, bool $record = true): void {
    $fin = function (string $msg) use ($pageId, $record) { if ($record) Cache::forever('cc.bf.' . $pageId, $msg); else Cache::put('cc.poll.last.' . $pageId, now()->timezone('Asia/Dhaka')->format('d M H:i') . ' - ' . $msg, 86400); };
    $tok = Chat::pageTok('facebook', $pageId); if (!$tok) { $fin('no token'); return; }
    $cl = $record ? 60 : 30; // comments per post: the recurring poll asks for less
    try {
      $r = Http::timeout(40)->get(Chat::API . '/' . $pageId . '/published_posts', ['fields' => 'id,created_time,comments.limit(' . $cl . '){id,message,from,created_time,comments.limit(8){from}}', 'since' => now()->subDays($days)->timestamp, 'limit' => $record ? 20 : 10, 'access_token' => $tok]);
      if (!$r->ok()) { Cache::put('cc.bf.err.' . $pageId, mb_substr((string)($r->json('error.message') ?? $r->status()), 0, 160), 86400); $fin('error'); return; }
      $all = (array)$r->json('data');
      // Ad (dark) posts are not in published_posts; most customer comments live there, and the "feed" webhook does not reliably deliver them - so the recurring poll is what answers them.
      try { $ad = Http::timeout(40)->get(Chat::API . '/' . $pageId . '/ads_posts', ['fields' => 'id,created_time,comments.limit(' . $cl . '){id,message,from,created_time,comments.limit(8){from}}', 'limit' => $record ? 40 : 25, 'access_token' => $tok]);
        if ($ad->ok()) $all = array_merge($all, (array)$ad->json('data')); else Cache::put('cc.bf.err.' . $pageId, 'ads_posts: ' . mb_substr((string)($ad->json('error.message') ?? $ad->status()), 0, 140), 86400); } catch (\Throwable $e) {}
      $first = (array)($all[0] ?? []);
      if ($record && !empty($first['id'])) { $dbg = Http::timeout(30)->get(Chat::API . '/' . $first['id'], ['fields' => 'comments.summary(true).limit(3){id,message,from},reactions.summary(true).limit(0)', 'access_token' => $tok]); Cache::put('cc.bf.dbg.' . $pageId, mb_substr(preg_replace('/\s+/', ' ', (string)$dbg->body()), 0, 380), 86400); }
      $n = 0; $posts = count($all); $seen = 0; $nofrom = 0; $ans = 0;
      foreach ($all as $post) foreach ((array)($post['comments']['data'] ?? []) as $c) {
        $seen++; if (empty($c['from']['id'])) $nofrom++;
        $answered = false; foreach ((array)($c['comments']['data'] ?? []) as $x) if ((string)($x['from']['id'] ?? '') === $pageId) $answered = true;
        if ($answered) $ans++;
        if ($answered || empty($c['id']) || empty($c['from']['id'])) continue;
        if (!empty($c['created_time']) && strtotime((string)$c['created_time']) < now()->subDays(self::MAX_DAYS)->timestamp) continue; // older than the window
        if (self::ingest($pageId, ['item' => 'comment', 'verb' => 'add', 'comment_id' => $c['id'], 'message' => $c['message'] ?? '', 'from' => $c['from'], 'post_id' => $post['id'] ?? '', 'created_time' => $c['created_time'] ?? null]) ) $n++;
      }
      $fin("done {$n} (posts {$posts}, comments {$seen}, already answered {$ans}, no sender info {$nofrom})");
    } catch (\Throwable $e) { $fin('error'); report($e); }
  }
  /** Answer one stored comment (once). */
  public static function reply(int $id): void {
    try {
      if (!self::ready() || !self::on()) return; // owner 15 Nov 2026: comment replies run 24 hours, whatever the AI chat hours are
      $c = DB::table('chat_comments')->find($id); if (!$c || $c->status !== 'new') return;
      if (strtotime((string)$c->created_at) < now()->subDays(self::MAX_DAYS)->timestamp) { self::done($id, 'skipped', null, 'older than ' . self::MAX_DAYS . ' days'); return; }
      if (!Cache::add('cc.lock.' . $id, 1, 600)) return;
      $msg = trim((string)$c->message);
      if (mb_strlen($msg) < 2) { self::done($id, 'skipped', null, 'no text (sticker / photo / tag)'); return; }
      $d = 'cc.n.' . now()->format('Ymd'); Cache::add($d, 0, 90000); if (Cache::increment($d) > 1500) { self::done($id, 'skipped', null, 'daily limit'); return; }
      $tok = Chat::pageTok($c->channel, $c->page_ref); if (!$tok) { self::done($id, 'failed', null, 'Page token missing (Chat Settings)'); return; }
      // Owner rule 15 Nov 2026: the public reply only invites the person to our inbox (no price, no product detail, nothing about tobacco); the details go in a private message from the Page inbox.
      // Owner 15 Nov 2026: bad comments (links / scam promo / abuse) are removed from the Page, never answered.
      $why = self::bad($msg);
      if ($why !== null) { self::remove($id, $why); return; }
      $first = trim((string)preg_split('/\s+/u', trim((string)$c->from_name))[0]); $nm = $first !== '' && mb_strlen($first) <= 20 ? $first : '';
      $v = self::VARIANTS; $text = strtr($v[crc32($c->comment_id) % count($v)], ['{n}' => $nm !== '' ? $nm . ', ' : '']);
      $r = Http::timeout(15)->asForm()->post(Chat::API . '/' . $c->comment_id . '/comments?access_token=' . urlencode($tok), ['message' => $text]);
      if ($r->ok()) { self::done($id, 'replied', $text, self::privateMessage($c, $tok, $nm)); }
      else self::done($id, 'failed', $text, 'HTTP ' . $r->status() . ' ' . mb_substr((string)($r->json('error.message') ?? ''), 0, 150));
    } catch (\Throwable $e) { report($e); }
  }
}
