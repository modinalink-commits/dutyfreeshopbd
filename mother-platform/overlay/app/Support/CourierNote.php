<?php
namespace App\Support;

/**
 * Courier note automation (owner spec 2026-11-06). Priority: 1) the customer's courier note (orders.note) is never replaced;
 * 2) no customer note -> a short automatic "Take Care" note (urgent / fragile variants when those flags exist); staff may edit the final text before sending.
 * Auto notes stay within 160 characters; anything the courier API receives is capped at 200.
 */
class CourierNote {
  const DEFAULT = 'Take Care: দ্রুত ও নিরাপদে ডেলিভারি করুন। কল না পেলে WhatsApp করুন।';
  const URGENT = 'Urgent: কাস্টমারের দ্রুত প্রয়োজন। সম্ভব হলে অগ্রাধিকার দিয়ে ডেলিভারি করুন।';
  const FRAGILE = 'Take Care: পণ্যটি সতর্কতার সাথে বহন করুন। ক্ষতি/ভাঙা এড়িয়ে ডেলিভারি করুন।';
  const BOTH = 'Take Care: দ্রুত ও সতর্কভাবে ডেলিভারি করুন। পণ্য ক্ষতিগ্রস্ত না হয় খেয়াল রাখুন। কল না পেলে WhatsApp করুন।';
  const MAX_AUTO = 160; const MAX_API = 200;

  /** @return array{urgent:bool, fragile:bool, high_value:bool} flags from the order (columns when present, else keywords in the note). */
  public static function flags(object $o): array {
    $n = mb_strtolower((string)($o->note ?? ''));
    $urgent = !empty($o->urgent) || (bool)preg_match('/urgent|জরুরি|জরুরী|আর্জেন্ট/u', $n);
    $fragile = !empty($o->fragile) || (bool)preg_match('/fragile|ভঙ্গুর|ভাঙ্গা|ভাঙা/u', $n);
    return ['urgent'=>$urgent, 'fragile'=>$fragile, 'high_value'=>(float)($o->total ?? 0) >= 20000];
  }
  /** @return array{text:string, source:string} source = Customer | Auto */
  public static function build(object $o): array {
    $c = trim((string)($o->note ?? ''));
    if ($c !== '') return ['text'=>mb_substr(preg_replace('/^\s*(customer note|note)\s*:\s*/iu', '', $c), 0, self::MAX_API), 'source'=>'Customer'];
    $f = self::flags($o);
    $t = ($f['urgent'] && $f['fragile']) ? self::BOTH : ($f['urgent'] ? self::URGENT : ($f['fragile'] ? self::FRAGILE : self::DEFAULT));
    return ['text'=>mb_strlen($t) > self::MAX_AUTO ? self::DEFAULT : $t, 'source'=>'Auto'];
  }
  /** Final text + source after an optional staff edit from the validation popup. */
  public static function resolve(object $o, ?string $edited): array {
    $b = self::build($o); $e = trim((string)$edited);
    if ($e !== '' && $e !== $b['text']) return ['text'=>mb_substr($e, 0, self::MAX_API), 'source'=>'Staff Edited'];
    return $b;
  }
}
