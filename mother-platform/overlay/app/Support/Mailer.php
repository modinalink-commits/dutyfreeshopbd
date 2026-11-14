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
  /** Sends one email now. Returns [ok, error]. */
  public static function deliver(string $to, string $subject, string $html): array {
    if (!self::hasPassword()) return [false, 'The mailbox password is not saved yet'];
    if (!self::valid($to)) return [false, 'Not a valid email address'];
    try {
      $from = self::user(); $name = self::fromName(); $rt = self::replyTo();
      self::mailer()->html($html, function ($m) use ($to, $subject, $from, $name, $rt) { $m->to($to)->subject($subject)->from($from, $name); if (self::valid($rt)) $m->replyTo($rt); });
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
        [$ok, $err] = self::deliver($r->to, $r->subject, $r->html);
        DB::table('mail_outbox')->where('id', $r->id)->update(['status' => $ok ? 'sent' : 'failed', 'error' => $err, 'sent_at' => $ok ? now() : null, 'updated_at' => now()]);
      }
    } catch (\Throwable $e) { report($e); } finally { Cache::forget('email.pump'); }
  }

  /** Everything the order template needs, from the order itself (prices and totals are the stored ones, nothing is recalculated). */
  public static function orderData(object $o, string $kind): array {
    $items = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $o->id)->orderBy('i.id')->get(['i.name', 'i.qty', 'i.price', 'p.image']);
    $base = rtrim((string)config('app.url'), '/'); if ($base === '' || str_contains($base, 'localhost')) $base = 'https://dutyfreebd.com';
    $img = fn($u) => !$u ? null : (preg_match('#^https?://#', (string)$u) ? (string)$u : $base . '/' . ltrim((string)$u, '/'));
    $logo = Cache::get('store.settings2')['logo_url'] ?? null; if (is_array($logo)) $logo = null; $logo = $img($logo ?: '/logo.webp');
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
  public static function tick(): void { self::orderTick(); self::pump(); }
}
