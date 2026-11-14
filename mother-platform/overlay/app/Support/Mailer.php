<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Customer email through the DutyFreeBD mailbox (orders@dutyfreebd.com, Hostinger). Order emails first; nothing is sent while the master switch is off.
 * The mailbox password is kept encrypted with the other integration secrets and is never shown again. Every email is logged in mail_outbox.
 */
class Mailer {
  const KINDS = ['received' => ['New', 'Order received'], 'confirmed' => ['Approved', 'Order confirmed'], 'shipped' => ['Shipment', 'Order shipped'], 'delivered' => ['Delivered', 'Order delivered'], 'cancelled' => ['Cancel', 'Order cancelled'], 'payment' => ['', 'Payment received']];
  public static function set(string $k, string $d = ''): string { return Procure::set('email_' . $k, $d); }
  public static function tableReady(): bool { try { return Schema::hasTable('mail_outbox'); } catch (\Throwable $e) { return false; } }
  public static function user(): string { return self::set('user', 'orders@dutyfreebd.com'); }
  public static function fromName(): string { return self::set('from_name', 'DutyFree BD'); }
  public static function replyTo(): string { return self::set('reply_to', 'support@dutyfreebd.com'); }
  public static function hasPassword(): bool { return (bool)Sms::secret('email_password'); }
  public static function ready(): bool { return self::hasPassword() && self::tableReady(); }
  public static function on(): bool { return self::set('on', '0') === '1' && self::ready(); }
  public static function kindOn(string $k): bool { return self::set('kind_' . $k, '1') === '1'; }
  public static function cap(): int { return max(50, min(1000, (int)self::set('daily_cap', '900'))); }
  public static function sentToday(): int { try { return (int)DB::table('mail_outbox')->where('status', 'sent')->where('sent_at', '>=', now('Asia/Dhaka')->startOfDay()->utc())->count(); } catch (\Throwable $e) { return 0; } }
  public static function valid(?string $e): bool { $e = trim((string)$e); return $e !== '' && strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false; }

  private static function mailer() {
    config(['mail.mailers.dfb' => ['transport' => 'smtp', 'scheme' => 'smtps', 'host' => self::set('host', 'smtp.hostinger.com'), 'port' => (int)self::set('port', '465'), 'encryption' => 'ssl', 'username' => self::user(), 'password' => (string)Sms::secret('email_password'), 'timeout' => 20]]);
    try { app('mail.manager')->purge('dfb'); } catch (\Throwable $e) {}
    return Mail::mailer('dfb');
  }
  /** Plain-text version of an email (mail filters trust a message more when it carries both forms). */
  public static function plain(string $html): string {
    $t = preg_replace(['#<(style|script|title)[^>]*>.*?</\1>#si', '#<div style="display:none[^>]*>.*?</div>#si'], '', $html);
    $t = preg_replace_callback('#<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>#si', fn($m) => trim(strip_tags($m[2])) . ' (' . $m[1] . ')', $t);
    $t = preg_replace(['#<br\s*/?>#i', '#</(p|div|tr|h1|h2|table)>#i', '#</td>#i'], ["\n", "\n", ' '], $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    return trim(preg_replace(["/[ \t]+/u", "/\n[ \t]+/u", "/\n{3,}/u"], [' ', "\n", "\n\n"], $t));
  }
  /** Sends one email now (HTML + plain text). $unsub: stop link for offer emails, also sent as the List-Unsubscribe header. Returns [ok, error]. */
  /** Logo for emails: always PNG/JPG (WebP is not shown by every mail app). */
  public static function logo(): string { $l = Cache::get('store.settings2')['logo_url'] ?? null; if (!is_string($l) || !preg_match('/\.(png|jpe?g)(\?.*)?$/i', $l)) $l = '/email-logo.png'; return self::abs($l); }
  public static function deliver(string $to, string $subject, string $html, ?string $unsub = null): array {
    if (!self::hasPassword()) return [false, 'The mailbox password is not saved yet'];
    if (!self::valid($to)) return [false, 'Not a valid email address'];
    try {
      $from = self::user(); $name = self::fromName(); $rt = self::replyTo(); $text = self::plain($html);
      self::mailer()->send([], [], function ($m) use ($to, $subject, $from, $name, $rt, $html, $text, $unsub) {
        $m->to($to)->subject($subject)->from($from, $name)->html($html)->text($text); if (self::valid($rt)) $m->replyTo($rt);
        if ($unsub) { try { $h = $m->getHeaders(); $h->addTextHeader('List-Unsubscribe', '<' . $unsub . '>'); $h->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click'); } catch (\Throwable $e) {} }
      });
      return [true, null];
    } catch (\Throwable $e) { return [false, mb_substr($e->getMessage(), 0, 400)]; }
  }
  /** Queues an email (duplicate-protected by ref). Null when nothing was queued. */
  public static function enqueue(string $to, string $subject, string $html, string $type, ?string $ref = null, ?int $orderId = null): ?int {
    if (!self::tableReady() || !self::valid($to)) return null;
    try {
      if ($ref !== null && DB::table('mail_outbox')->where('ref', $ref)->exists()) return null;
      return (int)DB::table('mail_outbox')->insertGetId(['to' => trim($to), 'subject' => mb_substr($subject, 0, 250), 'type' => $type, 'ref' => $ref, 'order_id' => $orderId, 'status' => 'queued', 'html' => $html, 'created_at' => now(), 'updated_at' => now()]);
    } catch (\Throwable $e) { return null; }
  }
  /** Sends a few queued emails per worker tick, inside the daily cap. Order emails go before any other type. */
  public static function pump(int $n = 8): void {
    if (!self::on() || !Cache::add('email.pump', 1, 20)) return;
    try {
      $left = self::cap() - self::sentToday(); if ($left <= 0) return;
      $rows = DB::table('mail_outbox')->where('status', 'queued')->where('created_at', '>=', now()->subDays(2))->orderByRaw("case when type like 'order_%' then 0 else 1 end")->orderBy('id')->limit(min($n, $left))->get();
      foreach ($rows as $r) {
        if (!DB::table('mail_outbox')->where('id', $r->id)->where('status', 'queued')->update(['status' => 'sending', 'updated_at' => now()])) continue;
        [$ok, $err] = self::deliver($r->to, $r->subject, $r->html, str_starts_with((string)$r->type, 'mk_') ? self::unsubUrl($r->to) : null);
        DB::table('mail_outbox')->where('id', $r->id)->update(['status' => $ok ? 'sent' : 'failed', 'error' => $err, 'sent_at' => $ok ? now() : null, 'updated_at' => now()]);
      }
    } catch (\Throwable $e) { report($e); } finally { Cache::forget('email.pump'); }
  }

  /** Everything the order template needs, from the order itself (prices and totals are the stored ones, nothing is recalculated). */
  public static function orderData(object $o, string $kind): array {
    $items = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $o->id)->orderBy('i.id')->get(['i.name', 'i.qty', 'i.price', 'p.image']);
    $base = rtrim((string)config('app.url'), '/'); if ($base === '' || str_contains($base, 'localhost')) $base = 'https://dutyfreebd.com';
    $img = fn($u) => !$u ? null : (preg_match('#^https?://#', (string)$u) ? (string)$u : $base . '/' . ltrim((string)$u, '/'));
    $logo = self::logo();
    $inv = (string)$o->invoice; $invoiceUrl = null; try { $invoiceUrl = Chat::invoiceUrl($inv); } catch (\Throwable $e) {}
    $t = [
      'received' => ['আপনার অর্ডারটি আমরা পেয়েছি', 'ধন্যবাদ! আপনার অর্ডার আমরা পেয়েছি। আমাদের টিম যাচাই করে খুব শিগগিরই কনফার্ম করবে।', 'অর্ডার দেখুন', $invoiceUrl ?: $base . '/track?invoice=' . rawurlencode($inv)],
      'confirmed' => ['আপনার অর্ডার কনফার্ম হয়েছে', 'সুখবর! আপনার অর্ডার কনফার্ম হয়েছে এবং এখন প্যাকেজিংয়ের জন্য প্রস্তুত হচ্ছে।', 'ইনভয়েস দেখুন', $invoiceUrl ?: $base . '/track?invoice=' . rawurlencode($inv)],
      'shipped' => ['আপনার অর্ডার রওনা দিয়েছে', 'আপনার পার্সেল কুরিয়ারে হস্তান্তর করা হয়েছে। নিচের বাটনে পার্সেলের বর্তমান অবস্থা দেখতে পারবেন।', 'পার্সেল ট্র্যাক করুন', $base . '/track?invoice=' . rawurlencode($inv)],
      'delivered' => ['আপনার অর্ডার পৌঁছে গেছে', 'আপনার পার্সেল ডেলিভারি সম্পন্ন হয়েছে। DutyFree BD থেকে কেনাকাটার জন্য আন্তরিক ধন্যবাদ।', 'আবার কেনাকাটা করুন', $base . '/'],
      'payment' => ['আপনার পেমেন্ট পেয়েছি', 'আপনার অর্ডারের পেমেন্ট আমরা পেয়েছি এবং যাচাই সম্পন্ন হয়েছে। ধন্যবাদ।', 'ইনভয়েস দেখুন', $invoiceUrl ?: $base . '/track?invoice=' . rawurlencode($inv)],
      'cancelled' => ['আপনার অর্ডার বাতিল হয়েছে', 'আপনার অর্ডারটি বাতিল করা হয়েছে। এটি ভুল মনে হলে বা আবার অর্ডার করতে চাইলে আমাদের সাথে যোগাযোগ করুন।', 'আবার অর্ডার করুন', $base . '/'],
    ][$kind];
    return ['o' => $o, 'kind' => $kind, 'items' => $items->map(fn($i) => (object)['name' => $i->name, 'qty' => (int)$i->qty, 'price' => (float)$i->price, 'image' => $img($i->image)]), 'logo' => $logo, 'base' => $base, 'title' => $t[0], 'intro' => $t[1], 'cta' => $t[2], 'url' => $t[3],
      'subject' => $t[0] . ' · ' . $inv, 'hotline' => Biz::hotline(), 'wa' => Biz::waUrl(), 'hours' => Biz::hours()];
  }
  public static function orderHtml(object $o, string $kind): array { $d = self::orderData($o, $kind); return [$d['subject'], view('emails.order', $d)->render()]; }

  /** Follows order status changes with its own cursor and queues the matching email. Starts from "now" the first time, so old orders never get an email. */
  public static function orderTick(): void {
    if (!self::on() || !Cache::add('email.order.tick', 1, 25)) return;
    try {
      $cur = (int)self::set('order_cursor', '-1');
      if ($cur < 0) { Procure::put('email_order_cursor', (string)(int)DB::table('order_status_logs')->max('id')); return; }
      $logs = DB::table('order_status_logs')->where('id', '>', $cur)->orderBy('id')->limit(80)->get(); if ($logs->isEmpty()) return;
      $by = []; foreach (self::KINDS as $k => [$st]) if ($st !== '') $by[$st] = $k;
      foreach ($logs as $l) {
        $cur = (int)$l->id; $paid = str_starts_with((string)$l->note, 'Payment Verified');
        if (!$paid && $l->from_status === $l->to_status) continue; $kind = $paid ? 'payment' : ($by[(string)$l->to_status] ?? null); if (!$kind || !self::kindOn($kind)) continue;
        $o = DB::table('orders')->find($l->order_id); if (!$o) continue;
        $to = trim((string)($o->email ?? '')); if (!self::valid($to)) { try { $to = trim((string)DB::table('customers')->where('phone', $o->phone)->value('email')); } catch (\Throwable $e) { $to = ''; } }
        if (!self::valid($to)) continue;
        try { [$sub, $html] = self::orderHtml($o, $kind); self::enqueue($to, $sub, $html, 'order_' . $kind, 'o' . $o->id . ':' . $kind . ($paid ? ':' . $l->id : ''), (int)$o->id); } catch (\Throwable $e) { report($e); }
      }
      Procure::put('email_order_cursor', (string)$cur);
    } catch (\Throwable $e) { report($e); }
  }
  // ---------------------------------------------------------------- marketing (offer) email
  public static function mkReady(): bool { try { return Schema::hasTable('mail_list') && Schema::hasTable('mail_optouts'); } catch (\Throwable $e) { return false; } }
  public static function mkOn(): bool { return self::set('mk_on', '0') === '1' && self::on() && self::mkReady(); }
  public static function mkCap(): int { return max(0, min(900, (int)self::set('mk_cap', '500'))); }
  public static function mkGap(): int { return max(1, min(60, (int)self::set('mk_gap', '7'))); }
  public static function mkToday(): int { try { return (int)DB::table('mail_outbox')->where('type', 'like', 'mk_%')->whereIn('status', ['queued', 'sending', 'sent'])->where('created_at', '>=', now('Asia/Dhaka')->startOfDay()->utc())->count(); } catch (\Throwable $e) { return 0; } }
  public static function base(): string { $b = rtrim((string)config('app.url'), '/'); return ($b === '' || str_contains($b, 'localhost')) ? 'https://dutyfreebd.com' : $b; }
  public static function token(string $email): string { $email = mb_strtolower(trim($email)); return rtrim(strtr(base64_encode($email), '+/', '-_'), '=') . '.' . substr(hash_hmac('sha256', $email, (string)config('app.key')), 0, 24); }
  public static function emailFromToken(string $t): ?string {
    $p = explode('.', $t, 2); if (count($p) !== 2) return null; $e = base64_decode(strtr($p[0], '-_', '+/'), true); if (!$e || !self::valid($e)) return null;
    return hash_equals(self::token($e), $t) ? mb_strtolower($e) : null;
  }
  public static function unsubUrl(string $email): string { return self::base() . '/email/unsubscribe/' . self::token($email); }
  public static function optedOut(string $email): bool { try { return DB::table('mail_optouts')->where('email', mb_strtolower(trim($email)))->exists(); } catch (\Throwable $e) { return false; } }
  private static function abs(?string $u): ?string { if (!$u) return null; return preg_match('#^https?://#', $u) ? $u : self::base() . '/' . ltrim($u, '/'); }

  /** Products for the offer email, all from the live catalogue: the Best Seller pack offers first, then the in-stock products with the biggest real saving. */
  public static function offerProducts(int $n = 6): array {
    return Cache::remember('email.offer.products.' . $n, 300, fn() => self::offerProductsNow($n)); // one catalogue read per 5 minutes, however many emails are built
  }
  private static function offerProductsNow(int $n): array {
    $out = []; $seen = [];
    try { foreach (\App\Http\Controllers\StoreController::bestSellers() as $p) { $t = BundlePricing::total((int)$p->id, 2); if (!$t || (int)$p->stock < 2 || $t >= (float)$p->price * 2) continue;
      $out[] = ['name' => $p->name, 'img' => self::abs($p->bs_image ?: $p->image), 'price' => (float)$t, 'was' => (float)$p->price * 2, 'tag' => '২ কার্টন', 'url' => self::base() . '/p/m' . $p->id]; $seen[(int)$p->id] = 1; } } catch (\Throwable $e) {}
    try { $rows = StockGuard::listable(DB::table('products')->where('published', true))->whereNotNull('image')->where('image', '!=', '')->where('compare_price', '>', 0)->whereColumn('compare_price', '>', 'price')->where(fn($w) => $w->whereNull('stock')->orWhere('stock', '>', 0))
        ->orderByRaw('(compare_price - price) / compare_price desc')->limit(40)->get(['id', 'name', 'image', 'price', 'compare_price', 'stock', 'category_id']);
      foreach ($rows->shuffle()->take(40) as $p) { if (count($out) >= $n) break; if (isset($seen[(int)$p->id])) continue; try { if (!StockGuard::sellable($p)) continue; } catch (\Throwable $e) {}
        $out[] = ['name' => $p->name, 'img' => self::abs($p->image), 'price' => (float)$p->price, 'was' => (float)$p->compare_price, 'tag' => null, 'url' => self::base() . '/p/m' . $p->id]; } } catch (\Throwable $e) {}
    return $out;
  }
  /** [subject, html] of an offer email. $msg: optional campaign line (the approved SMS text of that campaign, link removed); $cta: [label, url]. */
  public static function offerHtml(string $email, ?string $name = null, ?string $msg = null, ?array $cta = null, ?string $title = null): array {
    $items = self::offerProducts(); $title = $title ?: 'আপনার জন্য আজকের অফার';
    $subject = $title . ' · DutyFree BD';
        return [$subject, view('emails.offer', ['items' => $items, 'title' => $title, 'name' => $name, 'msg' => $msg, 'cta' => $cta ?: ['সব অফার দেখুন', self::base() . '/'], 'logo' => self::logo(), 'base' => self::base(), 'unsub' => self::unsubUrl($email), 'subject' => $subject, 'hotline' => Biz::hotline(), 'wa' => Biz::waUrl()])->render()];
  }
  /** A campaign SMS that was just queued (Back in Store, Best Seller, refill reminder...) also goes by email to the same customer when we have the address. */
  public static function fromSms(string $num, string $body, string $event, ?string $ref = null): void {
    try {
      if (!self::mkOn()) return; $p = substr(preg_replace('/\D/', '', $num), -11); if (strlen($p) < 10) return;
      $c = DB::table('customers')->whereRaw('right(phone, 11) = ?', [$p])->whereNotNull('email')->where('email', '!=', '')->first(['name', 'email']); if (!$c || !self::valid($c->email) || self::optedOut($c->email)) return;
      $url = null; if (preg_match('#(https?://\S+|dutyfreebd\.com/\S+)#u', $body, $m)) $url = preg_match('#^https?://#', $m[1]) ? $m[1] : 'https://' . $m[1];
      $msg = trim(preg_replace(['#(https?://\S+|dutyfreebd\.com/\S+)#u', '/অর্ডার\s*:\s*$/um', '/DutyFreeBD\s*$/u'], ['', '', ''], $body)); $msg = trim(preg_replace('/\s+/u', ' ', $msg), " :।\n") . '।';
      $titles = ['pub_back' => 'আপনার পছন্দের পণ্য আবার স্টকে', 'bs_once' => 'Best Seller অফার: ২ কার্টনে বিশেষ দাম', 'bs_remind' => 'আবার অর্ডারের সময় হলো?'];
      [$sub, $html] = self::offerHtml($c->email, $c->name, $msg, $url ? ['এখনই অর্ডার করুন', $url] : null, $titles[$event] ?? null);
      self::enqueue($c->email, $sub, $html, 'mk_' . mb_substr($event, 0, 30), 'mk:sms:' . ($ref ?: md5($event . $p . $body)));
    } catch (\Throwable $e) { report($e); }
  }
  /** Daily offer email, paced through the day (never one big burst): the owner's extra list first, then customers with an address, longest-inactive first. Nobody gets one more often than the gap. */
  public static function offerTick(): void {
    if (!self::mkOn() || !Cache::add('email.offer.tick', 1, 600)) return;
    try {
      $h = (int)now('Asia/Dhaka')->format('G'); if ($h < 10 || $h >= 21) return;
      $left = self::mkCap() - self::mkToday(); if ($left <= 0) return; $n = min($left, 50); $gap = self::mkGap(); $day = now('Asia/Dhaka')->format('Ymd');
      $free = 'not exists (select 1 from mail_optouts x where x.email = lower(%1$s)) and not exists (select 1 from mail_outbox o where o."to" = %1$s and o.type like \'mk_%%\' and o.created_at > now() - (%2$d * interval \'1 day\'))';
      $rows = DB::select('select l.email, l.name from mail_list l where ' . sprintf($free, 'l.email', $gap) . ' order by l.id limit ' . (int)$n);
      if (count($rows) < $n) { $more = DB::select("select c.email, c.name from customers c where c.email is not null and c.email <> '' and coalesce(c.blocked, false) = false and " . sprintf($free, 'c.email', $gap) . ' order by (select max(o2.created_at) from orders o2 where o2.phone = c.phone) asc nulls first limit ' . (int)($n - count($rows))); $rows = array_merge($rows, $more); }
      $done = [];
      foreach ($rows as $r) { $e = mb_strtolower(trim((string)$r->email)); if (isset($done[$e]) || !self::valid($e)) continue; $done[$e] = 1;
        [$sub, $html] = self::offerHtml($e, $r->name ?? null); self::enqueue($e, $sub, $html, 'mk_offer', 'mk:offer:' . $day . ':' . $e); }
    } catch (\Throwable $e) { report($e); }
  }
  public static function tick(): void { self::orderTick(); self::offerTick(); self::pump(); }
}
