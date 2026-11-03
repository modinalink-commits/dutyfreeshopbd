<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** The one and only invoice generator. Every order source (website, admin, incomplete checkout, AI, ...) takes the next number from the same DB sequence. Status never matters; numbers are never reused. */
class InvoiceNumber {
  const MIN = 100000, MAX = 999999;
  /** Next number: exactly 6 digits (100000-999999), one global sequence. Never rolls over into 7 digits: when the range is used up this throws until a business decision is made. */
  public static function next(): string {
    for ($i = 0; $i < 20; $i++) {
      try { $n = (int)DB::selectOne("select nextval('invoice_seq') n")->n; }
      catch (\Throwable $e) { throw new \RuntimeException('Invoice numbers are used up (6 digits, 100000-999999). A business decision is needed before going further.'); }
      if ($n < self::MIN) { DB::statement("select setval('invoice_seq', ?)", [self::MIN - 1]); continue; }
      if ($n > self::MAX) throw new \RuntimeException('Invoice numbers are used up (6 digits, 100000-999999). A business decision is needed before going further.');
      $inv = (string)$n;
      if (!DB::table('orders')->whereIn('invoice', [$inv, 'INV-'.$inv])->exists() && !DB::table('incomplete_orders')->whereIn('invoice', [$inv, 'INV-'.$inv])->exists()) return $inv;
    }
    throw new \RuntimeException('Could not allocate an invoice number');
  }
  /** How many numbers are left (for a dashboard warning). */
  public static function remaining(): ?int {
    try { return self::MAX - (int)DB::selectOne("select last_value v from invoice_seq")->v; } catch (\Throwable $e) { return null; }
  }
  /** Invoice already reserved by the open incomplete checkout this order is converting (token, id or phone), if any and still unused. */
  public static function reserved(?string $token = null, $incId = null, ?string $phone = null): ?string {
    $q = DB::table('incomplete_orders')->whereIn('status', ['open','recovered'])->whereNotNull('invoice');
    if ($incId) $q->where('id', (int)$incId); elseif ($token) $q->where('token', $token); elseif ($phone) $q->where('phone', $phone); else return null;
    $inv = $q->orderByDesc('id')->value('invoice');
    return $inv && !DB::table('orders')->where('invoice', $inv)->exists() ? $inv : null;
  }
}
