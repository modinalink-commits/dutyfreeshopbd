<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/**
 * Owner 17 Nov 2026: for one month, every Messenger customer is told the new hotline and WhatsApp number once,
 * right after their first message in that period. Settings: contact_notice_on (1/0), contact_notice_until (date), contact_notice_text.
 * The notice is an automatic message: it is stored in the conversation, but it never clears "Awaiting Reply" and never moves the conversation.
 */
class ContactNotice {
  const BY = 'Contact notice (auto)';
  private static function cfg(string $k): string { try { return (string)DB::table('settings')->where('key', $k)->value('value'); } catch (\Throwable $e) { return ''; } }
  public static function active(): bool {
    if (self::cfg('contact_notice_on') !== '1' || trim(self::cfg('contact_notice_text')) === '') return false;
    $u = self::cfg('contact_notice_until');
    try { return $u !== '' && now()->lte(\Illuminate\Support\Carbon::parse($u, 'Asia/Dhaka')->endOfDay()); } catch (\Throwable $e) { return false; }
  }
  /** Last line of the order SMS: while the notice period runs it names the new hotline and WhatsApp number (setting contact_notice_sms); afterwards the usual "Help: hotline". */
  public static function smsLine(): string {
    $x = self::active() ? trim(self::cfg('contact_notice_sms')) : '';
    return $x !== '' ? $x : 'Help: '.Biz::hotline();
  }
  /** Appended to other customer SMS while the notice period runs (" New Hotline … WhatsApp …"); empty afterwards. */
  public static function tail(): string { $x = self::active() ? trim(self::cfg('contact_notice_sms')) : ''; return $x !== '' ? ' '.$x : ''; }
  /** WhatsApp order templates end with "ধন্যবাদ, {brand} টিম": during the notice period the brand value also carries the two numbers (no template change, so no new Meta review). */
  public static function brand(string $b): string { $x = self::active() ? trim(self::cfg('contact_notice_wa')) : ''; return $x !== '' ? $b.' ('.$x.')' : $b; }
  /** Send the notice to this Messenger conversation if it has not had it yet. Safe to call on every incoming message. */
  public static function maybe(int $tid): void {
    try {
      if (!self::active()) return;
      $t = DB::table('chat_threads')->find($tid);
      if (!$t || $t->channel !== 'facebook') return;
      if (!Cache::add('contact_notice.'.$tid, 1, 600)) return; // two messages at the same moment: only one notice
      if (DB::table('chat_messages')->where('thread_id', $tid)->where('user_name', self::BY)->where('status', 'sent')->exists()) return;
      $body = trim(self::cfg('contact_notice_text'));
      $prev = Chat::$sender; Chat::$sender = ['type'=>'system'];
      try { [$ok, $err] = Chat::deliver($t, $body); } finally { Chat::$sender = $prev; }
      DB::table('chat_messages')->insert(['thread_id'=>$tid, 'dir'=>'out', 'kind'=>'text', 'body'=>mb_substr($body, 0, 4000), 'user_name'=>self::BY, 'is_ai'=>true, // marked as not-a-person so it never pauses the AI or counts as a staff answer
        'status'=>$ok ? 'sent' : 'failed', 'error'=>$ok ? null : mb_substr((string)$err, 0, 250), 'created_at'=>now(), 'updated_at'=>now()]);
      if (!$ok) Cache::forget('contact_notice.'.$tid); // not delivered: the customer's next message tries again
    } catch (\Throwable $e) { report($e); }
  }
}
