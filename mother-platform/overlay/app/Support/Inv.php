<?php
namespace App\Support;
/** Invoice display + lookup helpers. New invoices are exactly 6 digits; older orders may still be stored as INV-<digits>, shown without the prefix. */
class Inv {
  /** What people see: never an "INV-" prefix. */
  public static function show($v): string { return preg_replace('/^\s*INV[-\s]?/i', '', (string)$v); }
  /** Stored forms a typed/scanned invoice number can match (the number itself, or the older INV- form). */
  public static function candidates($q): array {
    $q = strtoupper(preg_replace('/\s+/', '', (string)$q)); $q = preg_replace('/^INV-?/', '', $q); if ($q === '') return [];
    $c = [$q, 'INV-'.$q]; if (ctype_digit($q) && strlen($q) < 6) $c[] = 'INV-'.str_pad($q, 6, '0', STR_PAD_LEFT);
    return array_values(array_unique($c));
  }
  /** Short signed key for customer-facing tracking links (SMS, print, chat): the link works without asking for the phone number. */
  public static function trackKey($inv): string { return substr(hash_hmac('sha256', self::show($inv), (string)config('app.key')), 0, 12); }
  public static function trackUrl($inv, bool $abs = true): string { $i = self::show($inv); $u = '/track?invoice='.rawurlencode($i).'&k='.self::trackKey($i); return $abs ? url($u) : $u; }
  /** Remove the prefix from invoice numbers inside rendered text, but never inside URLs, query strings or identifiers. */
  public static function strip(string $text): string { return preg_replace('/(?<![\w\/=%.-])INV-(\d+)/i', '$1', $text); }
}
