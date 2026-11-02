<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** The one and only invoice generator. Every order source (website, admin, incomplete checkout, AI, ...) takes the next number from the same DB sequence. Status never matters; numbers are never reused. */
class InvoiceNumber {
  public static function next(): string {
    for ($i = 0; $i < 20; $i++) {
      $n = (int)DB::selectOne("select nextval('invoice_seq') n")->n; $inv = 'INV-'.str_pad((string)$n, 6, '0', STR_PAD_LEFT);
      if (!DB::table('orders')->where('invoice', $inv)->exists() && !DB::table('incomplete_orders')->where('invoice', $inv)->exists()) return $inv;
    }
    throw new \RuntimeException('Could not allocate an invoice number');
  }
  /** Invoice already reserved by the open incomplete checkout this order is converting (token, id or phone), if any and still unused. */
  public static function reserved(?string $token = null, $incId = null, ?string $phone = null): ?string {
    $q = DB::table('incomplete_orders')->whereIn('status', ['open','recovered'])->whereNotNull('invoice');
    if ($incId) $q->where('id', (int)$incId); elseif ($token) $q->where('token', $token); elseif ($phone) $q->where('phone', $phone); else return null;
    $inv = $q->orderByDesc('id')->value('invoice');
    return $inv && !DB::table('orders')->where('invoice', $inv)->exists() ? $inv : null;
  }
}
