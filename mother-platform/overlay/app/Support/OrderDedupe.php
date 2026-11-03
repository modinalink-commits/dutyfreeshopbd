<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/**
 * Duplicate-order protection (call INSIDE the order transaction).
 *  1. one checkout token (idempotency key) = one order, enforced by a UNIQUE index;
 *  2. the same order content (phone + products + quantities + total + address + payment) within WINDOW seconds returns the existing order;
 *  3. a per-phone advisory lock makes concurrent submits wait for each other, so only the first one creates.
 * A genuine repeat order later is never blocked: the token only counts while it is fresh (TOKEN_TTL), the fingerprint only for WINDOW seconds.
 */
class OrderDedupe {
  const WINDOW = 90;      // seconds: identical content = same click
  const TOKEN_TTL = 900;  // seconds: a token reused for a new order after this long gets a fresh key
  public static function token(?string $t): ?string { $t = trim((string)$t); return preg_match('/^[A-Za-z0-9_\-]{16,60}$/', $t) ? $t : null; }
  /** items: list of ['id'|'product_id' => .., 'qty' => ..] */
  public static function fingerprint(string $phone, array $items, $total, string $address, string $pay = 'cod'): string {
    $p = []; foreach ($items as $i) { $id = (int)($i['id'] ?? $i['product_id'] ?? 0); $p[] = ($id > 0 ? 'p'.$id : 'n'.crc32((string)($i['name'] ?? ''))).':'.(int)($i['qty'] ?? 1); }
    sort($p);
    $a = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($address));
    return hash('sha256', preg_replace('/\D/', '', $phone).'|'.implode(',', $p).'|'.number_format((float)$total, 2, '.', '').'|'.$a.'|'.$pay);
  }
  public static function lock(string $phone): void {
    $k = (int)sprintf('%u', crc32('order-phone:'.preg_replace('/\D/', '', $phone)));
    DB::select('select pg_advisory_xact_lock(?)', [$k]);
  }
  /** Returns [existingOrderRow|null, idempotencyKeyToStore|null]. Must run after lock(). */
  public static function resolve(?string $token, string $fp): array {
    $tok = self::token($token); $key = $tok;
    if ($tok) {
      $ex = DB::table('orders')->where('idempotency_key', $tok)->first();
      if ($ex) {
        if (\Illuminate\Support\Carbon::parse($ex->created_at)->gt(now()->subSeconds(self::TOKEN_TTL))) return [$ex, null];
        $key = $tok.'_'.bin2hex(random_bytes(4));
      }
    }
    $ex = DB::table('orders')->where('order_fingerprint', $fp)->where('created_at', '>', now()->subSeconds(self::WINDOW))->whereNull('duplicate_of_order_id')->orderBy('id')->first();
    return [$ex ?: null, $key];
  }
  /** "Possible Duplicate" hints for a page of orders: same phone + same total within 15 minutes of an EARLIER non-cancelled order (and same fingerprint when both have one). One query, no per-row lookups. Returns [orderId => ['id'=>originalId, 'invoice'=>originalInvoice]]. Review only: nothing is changed automatically. */
  public static function possible(array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = DB::select("select distinct on (o.id) o.id as oid, p.id as pid, p.invoice as pinv from orders o join orders p on p.phone = o.phone and p.id < o.id and p.total = o.total and p.status <> 'Cancel' and p.duplicate_of_order_id is null and p.created_at between o.created_at - interval '15 minutes' and o.created_at and (o.order_fingerprint is null or p.order_fingerprint is null or o.order_fingerprint = p.order_fingerprint) where o.id in ($in) and o.status <> 'Cancel' and o.duplicate_of_order_id is null order by o.id, p.id", $ids);
    $out = []; foreach ($rows as $r) $out[(int)$r->oid] = ['id'=>(int)$r->pid, 'invoice'=>(string)$r->pinv];
    return $out;
  }
}
