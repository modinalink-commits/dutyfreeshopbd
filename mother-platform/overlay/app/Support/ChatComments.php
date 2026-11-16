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
    return (string)Cache::remember('cc.post2.' . md5($postId), 86400, function () use ($postId, $tok) {
      try { $r = Http::timeout(8)->get(Chat::API . '/' . $postId, ['fields' => 'message', 'access_token' => $tok]); return $r->ok() ? mb_substr(trim((string)$r->json('message')), 0, 3000) : ''; } catch (\Throwable $e) { return ''; }
    });
  }
  const VARIANTS = ['{n}বিস্তারিত জানতে আমাদের ইনবক্সে আসুন, ধন্যবাদ। 🙏', '{n}দাম ও বিস্তারিত জানতে ইনবক্সে মেসেজ করুন, ধন্যবাদ। 🙏', '{n}আমাদের ইনবক্সে মেসেজ দিন, সব তথ্য সেখানে জানিয়ে দেব। ধন্যবাদ। 🙏', '{n}ইনবক্সে আসুন, বিস্তারিত সেখানেই জানাব। ধন্যবাদ। 🙏', '{n}পণ্যের বিস্তারিত জানতে ইনবক্সে আসুন, ধন্যবাদ। 🙏'];
  /**
   * Owner rules 16 Nov 2026. Moderation verdict for a comment: ['delete', why] for obscenity / abuse / threats / clear spam, ['review', why] when it is only doubtful
   * (kept for staff, never deleted, never answered), or null. A real complaint, a bad experience or polite criticism is NEVER deleted: words like "scam", "প্রতারক",
   * "ফালতু" alone are a complaint (see intent()), not abuse.
   */
  public static function moderate(string $t): ?array {
    $own = preg_replace('#(https?://)?(www\.)?dutyfreebd\.(com|shop|store)\S*#iu', ' ', $t); // our own address in a comment is not spam
    $complaint = self::intent($t) === 'complaint';
    if (preg_match('/(\bfuck|\bfck\b|\bf\*+k|\bbitch|\bbastard|\basshole|\bmotherf|\bslut\b|\bwhore\b|\bporn|\bxxx\b|\bnude|মাগি|মাগী|খানকি|চুদ|চোদ(?!্দ)|হারামি|হারামজাদা|কুত্তার বাচ্চা|কুকুরের বাচ্চা|শুয়োরের বাচ্চা|বেশ্যা|ভোদা|পর্ন|\bmagi\b|\bkhanki|\bchudi|\bchod|\bharami)/iu', $t)) return ['delete', 'abusive / obscene words'];
    if (preg_match('/(মেরে ফেল|খুন কর|জানে মার|জবাই|হাত পা ভেঙ|পুড়িয়ে দ|বোমা মার|\bkill (you|u)\b|\bi will kill|\bmere fel|\bkhun kor)/iu', $t)) return ['delete', 'threat'];
    $link = (bool)preg_match('#(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|xyz|top|site|online|shop|store|info|live|club|vip)\b|t\.me/|wa\.me/|bit\.ly|tinyurl)#iu', (string)$own);
    $promo = (bool)preg_match('/(1xbet|casino|ক্যাসিনো|betting|বেটিং|জুয়া|লোন দেওয়া|loan offer|earn (money|\$|dollar)|ঘরে বসে আয়|আয় করুন|income per day|crypto|bitcoin|forex|লাইক ফলোয়ার|free recharge|ফ্রি রিচার্জ|টেলিগ্রামে)/iu', $t);
    if ($promo && !$complaint) return ['delete', 'spam promotion'];
    if ($link) return $complaint ? ['review', 'link in a complaint'] : ['delete', 'spam link'];
    if ($promo) return ['review', 'possible spam'];
    if (preg_match('/((?<![\x{0980}-\x{09FF}])(বাল|বালের|শালা|শালার)(?![\x{0980}-\x{09FF}])|কুত্তা|শুয়োর|গাধা|বেয়াদব|সেক্স|\bsex|\bstupid|\bidiot|\bdamn\b|\bshit\b|\bbal\b|\bsala\b)/iu', $t)) return ['review', 'rude words'];
    return null;
  }
  /** Kept for older callers: reason when the comment must be deleted, else null. */
  public static function bad(string $t): ?string { $m = self::moderate($t); return $m && $m[0] === 'delete' ? $m[1] : null; }
  /** What the comment is about: complaint | order | delivery | price | praise | other. Bangla, English and Banglish. Complaint wins over everything else. */
  public static function intent(string $t): string {
    if (preg_match('/(পাইনি|পাই নাই|পাইনাই|পাই নি|আসেনি|আসে নাই|আসে নি|দেয়নি|দেয় নাই|নষ্ট|ভাঙা|ভাঙ্গা|নকল|দুই নাম্বার|দুই নম্বর|২ নাম্বার|২ নম্বর|দু নম্বর|দুনম্বর|ডুপ্লিকেট|ভেজাল|আসল না|আসল নয়|অরিজিনাল না|অরিজিনাল নয়|\bdui num|\b2 number|\bduplicate|not original|original na\b|ভুল পণ্য|ভুল প্রোডাক্ট|রিফান্ড|টাকা ফেরত|ফেরত চাই|অভিযোগ|প্রতারণা|প্রতারক|ঠকবাজ|ঠকা|ঠকিয়ে|বাজে|খারাপ|ফালতু|জালিয়াত|ভাঁওতা|চোর\b|ভালো না|ভাল না|ভালো নয়|ফোন ধরে না|রিপ্লাই দেয় না|উত্তর দেয় না|\bscam|\bfraud|\bcheat|\bfake\b|\brefund|\bcomplain|not received|did ?n.?t (get|receive)|never (got|received)|\bdamaged?\b|\bbroken\b|\bworst\b|bad (service|product|experience|quality)|no (reply|response)|\bkharap|\bpaini\b|pai nai|\bnokol\b|\bferot\b|\bfalto?u\b|\bbaje\b|\bprotarok)/iu', $t)) return 'complaint';
    if (preg_match('/(অর্ডার|কিনতে চাই|কিনবো|কিনব\b|নিতে চাই|নিবো|নিব\b|নেব\b|লাগবে|দরকার|পাঠান|পাঠিয়ে দিন|বুকিং|\border|\bbuy\b|\bpurchase|\bkinbo|\bkinte\b|\bnibo\b|\bnebo\b|nite chai|\blagbe\b|\bdorkar|\bbook(ing)?\b|i want|\bpathan\b)/iu', $t)) return 'order';
    if (preg_match('/(ডেলিভারি|ডেলিভারী|ডেলিভারির|কুরিয়ার|কত দিন|কতদিন|কবে পাব|কবে পাবো|হোম ডেলিভারি|ক্যাশ অন|\bdelivery|\bcourier|\bshipping|koto ?din|kobe pabo|cash on|\bcod\b)/iu', $t)) return 'delivery';
    if (preg_match('/(দাম|প্রাইস|প্রাইজ|মূল্য|কত টাকা|কত\b|কতো|রেট|বিস্তারিত|ডিটেইল|স্টক|আছে কি|আছে\?|পাওয়া যাবে|পাওয়া যায়|সাইজ|অরিজিনাল|\bprice|\bprize\b|\bpp\b|\bdam\b|\bdaam\b|\bkoto\b|\bkto\b|\brate\b|\bdetails?\b|\bstock\b|\bavailable|\bache\b|\basse\b|\bsize\b|\boriginal\b|\binfo\b|how much)/iu', $t)) return 'price';
    if (preg_match('/(ধন্যবাদ|সুন্দর|ভালো|ভাল\b|দারুণ|দারুন|অসাধারণ|চমৎকার|মাশাআল্লাহ|মাশাল্লাহ|আলহামদুলিল্লাহ|সেরা|বেস্ট|\bnice\b|\bgood\b|\bgreat\b|\bawesome|\bbest\b|\bexcellent|\blove(ly)?\b|\bthanks?\b|thank you|\bwow\b|\bvalo\b|\bbhalo\b|\bsundor|\bdarun\b|\bmashallah|\bsuperb?\b|\bperfect\b|👍|❤|😍|🥰|👏)/iu', $t)) return 'praise';
    return 'other';
  }
  const SITE = 'https://dutyfreebd.com';
  /** Public reply wording per intent ({n} = "Name, " or '', {l} = the product link written in the post, else the site address). Never a price, stock, delivery or refund promise; never asks for a phone number or address in public. */
  const REPLIES = [
    'price' => ['{n}ধন্যবাদ। কোন পণ্যটি সম্পর্কে জানতে চাচ্ছেন? নাম বা ছবি ইনবক্সে দিলে বিস্তারিত জানাতে পারব। 🙏', '{n}ধন্যবাদ। দাম ও বিস্তারিত জানতে আমাদের ইনবক্সে মেসেজ দিন অথবা ভিজিট করুন: {l}', '{n}আপনার আগ্রহের জন্য ধন্যবাদ। পণ্যের বিস্তারিত ইনবক্সে জানিয়ে দেব, ওয়েবসাইটেও দেখতে পারেন: {l}'],
    'order' => ['{n}আপনার আগ্রহের জন্য ধন্যবাদ। অর্ডার করতে ভিজিট করুন: {l} অথবা সহায়তার জন্য ইনবক্সে মেসেজ দিন। 🙏', '{n}ধন্যবাদ। সহজে অর্ডার করতে পারবেন এখানে: {l} প্রয়োজনে ইনবক্সে মেসেজ দিন, আমরা সাহায্য করব।'],
    'delivery' => ['{n}ধন্যবাদ। ডেলিভারি সম্পর্কে বিস্তারিত জানতে ইনবক্সে মেসেজ দিন, আমরা জানিয়ে দেব। অর্ডার করতে ভিজিট করুন: {l}', '{n}আপনার প্রশ্নের জন্য ধন্যবাদ। ডেলিভারির তথ্য ইনবক্সে জানিয়ে দিচ্ছি, অনুগ্রহ করে একটি মেসেজ দিন। 🙏'],
    'complaint' => ['{n}আপনার অসুবিধার বিষয়টি জানানোয় ধন্যবাদ। অনুগ্রহ করে অর্ডার নম্বরটি ইনবক্সে দিন। বিষয়টি যাচাই করে সহায়তা করা হবে।', '{n}অসুবিধার জন্য আন্তরিকভাবে দুঃখিত। অর্ডার নম্বরটি ইনবক্সে দিলে আমরা বিষয়টি যাচাই করে সহায়তা করব।'],
    'praise' => ['{n}আপনার সুন্দর মন্তব্যের জন্য আন্তরিক ধন্যবাদ। আপনার আস্থা আমাদের অনুপ্রেরণা। 🙏', '{n}আন্তরিক ধন্যবাদ। আপনার ভালো লাগা আমাদের অনুপ্রেরণা। পাশে থাকবেন। 🙏'],
  ];
  /** The public reply for a comment: [intent, text]. Tobacco / vape (in the comment or the post) only ever gets the plain inbox invitation, with no link and no product talk. */
  public static function replyText(string $msg, string $nm, string $commentId, string $postText = '', string $link = ''): array {
    $in = self::intent($msg); $n = $nm !== '' ? $nm . ', ' : ''; $k = crc32($commentId);
    $smoke = self::isSmoke($msg . ' ' . $postText);
    if ($in === 'complaint' || $in === 'praise') { $v = self::REPLIES[$in]; return [$in, strtr($v[$k % count($v)], ['{n}' => $n])]; }
    if ($smoke || $in === 'other') { $v = self::VARIANTS; return [$in, strtr($v[$k % count($v)], ['{n}' => $n])]; }
    $v = self::REPLIES[$in]; return [$in, strtr($v[$k % count($v)], ['{n}' => $n, '{l}' => $link !== '' ? $link : self::SITE])];
  }
  /** A product link the Page itself wrote in the post (our own domain only), else ''. Never invented. */
  public static function linkIn(string $postText): string {
    if (!preg_match('#https?://(?:www\.)?dutyfreebd\.com/[^\s<>"\']+#iu', $postText, $m)) return '';
    $u = rtrim($m[0], '.,;:!?)।'); return mb_strlen($u) <= 200 ? $u : '';
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
  /** The one inbox message that follows the public reply, worded for what the person asked. */
  public static function inboxText(string $nm, string $intent, string $link = ''): string {
    $hi = 'আসসালামু আলাইকুম' . ($nm !== '' ? ' ' . $nm : '') . '। ';
    $l = $link !== '' ? $link : self::SITE;
    if ($intent === 'complaint') return $hi . "আপনার অসুবিধার জন্য আন্তরিকভাবে দুঃখিত।\nঅনুগ্রহ করে অর্ডার নম্বরটি এখানে লিখুন। আমাদের প্রতিনিধি বিষয়টি যাচাই করে আপনাকে সহায়তা করবেন। ধন্যবাদ।";
    if ($intent === 'order') return $hi . "আপনার আগ্রহের জন্য ধন্যবাদ।\nকোন পণ্যটি অর্ডার করতে চান, এখানে লিখে জানান। সরাসরি অর্ডার করতে পারেন: " . $l . "\nধন্যবাদ।";
    if ($intent === 'delivery') return $hi . "আপনার কমেন্টের জন্য ধন্যবাদ।\nডেলিভারি সম্পর্কে কী জানতে চান, এখানে লিখুন। আমরা বিস্তারিত জানিয়ে দেব।\nওয়েবসাইট: " . $l;
    return $hi . "আপনার কমেন্টের জন্য ধন্যবাদ।\nকোন পণ্যের দাম বা বিস্তারিত জানতে চান? এখানে লিখে জানান, আমরা সাহায্য করব।\nওয়েবসাইট: " . $l;
  }
  /** One private message from the Page inbox to the commenter (Facebook allows one per comment, within 7 days). Returns a short note for the log. */
  private static function privateMessage(object $c, string $tok, string $nm, string $intent = 'other', string $link = ''): string {
    try {
      $m = self::inboxText($nm, $intent, $link) . ContactNotice::line();
      $r = Http::timeout(15)->withToken($tok)->post(Chat::API . '/' . $c->comment_id . '/private_replies', ['message' => $m]);
      if ($r->ok()) return 'inbox message sent';
      $e1 = mb_substr((string)($r->json('error.message') ?? $r->status()), 0, 70);
      // Fallback: Meta's current Send API way of a private reply to a comment (POST /{page-id}/messages with recipient.comment_id).
      $r2 = Http::timeout(15)->withToken($tok)->post(Chat::API . '/' . $c->page_ref . '/messages', ['recipient' => ['comment_id' => $c->comment_id], 'message' => ['text' => $m], 'messaging_type' => 'RESPONSE']);
      if ($r2->ok()) return 'inbox message sent (send api)';
      return 'inbox message failed: ' . $e1 . ' | send api: ' . mb_substr((string)($r2->json('error.message') ?? $r2->status()), 0, 70);
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
      $mod = self::moderate($msg);
      if ($mod !== null && $mod[0] === 'delete') { self::remove($id, $mod[1]); return; }
      if ($mod !== null) { self::done($id, 'skipped', null, 'review: ' . $mod[1]); return; } // doubtful: kept for staff, not deleted, not answered
      $first = trim((string)preg_split('/\s+/u', trim((string)$c->from_name))[0]); $nm = $first !== '' && mb_strlen($first) <= 20 ? $first : '';
      $pt = self::postText((string)$c->post_id, $tok); $link = self::isSmoke($msg . ' ' . $pt) ? '' : self::linkIn($pt);
      [$intent, $text] = self::replyText($msg, $nm, (string)$c->comment_id, $pt, $link);
      $text .= ContactNotice::line(); // owner 17 Nov 2026: new hotline / WhatsApp line for one month
      $r = Http::timeout(15)->asForm()->post(Chat::API . '/' . $c->comment_id . '/comments?access_token=' . urlencode($tok), ['message' => $text]);
      if ($r->ok()) {
        // Praise gets a thank-you only; every question / order / complaint also gets the one inbox message. A complaint is flagged for the representative.
        $note = $intent === 'praise' ? 'thanks (no inbox message)' : self::privateMessage($c, $tok, $nm, $intent, $link);
        self::done($id, 'replied', $text, ($intent === 'complaint' ? 'COMPLAINT - staff follow-up | ' : $intent . ' | ') . $note);
      }
      else self::done($id, 'failed', $text, 'HTTP ' . $r->status() . ' ' . mb_substr((string)($r->json('error.message') ?? ''), 0, 150));
    } catch (\Throwable $e) { report($e); }
  }
}
