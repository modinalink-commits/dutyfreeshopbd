<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/**
 * Courier note rule (owner spec): 1) the customer's courier note, else 2) an automatic short "Take Care" note.
 * A customer note is never replaced by a system note. Staff edits are marked "Staff Edited". Every note actually
 * sent to a courier is saved in courier_note_log (source, final note, courier, order, time).
 */
class CourierNote {
  const LONG  = 'Take Care: দ্রুত ও নিরাপদে ডেলিভারি করুন। পণ্য যেন ক্ষতিগ্রস্ত না হয় খেয়াল রাখুন। কল না পেলে WhatsApp করুন।';
  const SHORT = 'Take Care: দ্রুত ও নিরাপদে ডেলিভারি করুন। কল না পেলে WhatsApp করুন।';
  const URGENT = 'Urgent: কাস্টমারের দ্রুত প্রয়োজন। সম্ভব হলে অগ্রাধিকার দিয়ে ডেলিভারি করুন।';
  const COMBINED = 'Take Care: দ্রুত ও সতর্কভাবে ডেলিভারি করুন। পণ্য ক্ষতিগ্রস্ত না হয় খেয়াল রাখুন। কল না পেলে WhatsApp করুন।';
  const FRAGILE = 'Take Care: পণ্যটি সতর্কতার সাথে বহন করুন। ক্ষতি/ভাঙা এড়িয়ে ডেলিভারি করুন।';
  const MAX = 160; // recommended maximum; a courier with a smaller API limit passes its own

  /** The automatic note for an order without a customer note. */
  public static function auto($o, int $max = self::MAX): string {
    $urgent = !empty($o->urgent) || !empty($o->is_urgent);
    $fragile = !empty($o->fragile) || !empty($o->is_fragile);
    $t = ($urgent && $fragile) ? self::COMBINED : ($urgent ? self::URGENT : ($fragile ? self::FRAGILE : self::LONG));
    if (mb_strlen($t) > $max) $t = mb_strlen(self::SHORT) <= $max ? self::SHORT : mb_substr($t, 0, $max);
    return $t;
  }
  /** [note, source] where source is Customer | Staff Edited | Auto. */
  public static function resolve($o, int $max = self::MAX): array {
    $max = max(40, min($max, self::MAX));
    $n = trim((string)($o->note ?? ''));
    if ($n !== '') {
      $src = (string)($o->courier_note_source ?? '') === 'Staff Edited' ? 'Staff Edited' : 'Customer';
      $t = $src === 'Customer' && !preg_match('/^customer note\s*:/i', $n) ? 'Customer Note: '.$n : $n;
      return [mb_substr($t, 0, $max), $src];
    }
    return [self::auto($o, $max), 'Auto'];
  }
  /** Value for the courier API (cut to the courier's own limit) and an audit row. Never throws. */
  public static function send($o, string $courier, int $limit = 200): string {
    [$note, $src] = self::resolve($o, $limit);
    try { DB::table('courier_note_log')->insert(['order_id'=>$o->id ?? null, 'courier'=>$courier, 'source'=>$src, 'note'=>$note, 'sent_at'=>now()]); } catch (\Throwable $e) {}
    return $note;
  }
}
