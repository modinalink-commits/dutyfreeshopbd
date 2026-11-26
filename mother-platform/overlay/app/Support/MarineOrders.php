<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marine Supply: requisition -> USD quotation -> purchase order -> delivery, crew personal orders, and port calls (owner brief 26 Nov 2026).
 * Who may do what (checked on the server on every request, never by hiding a button):
 *   - Shipping owner: raises requisitions for its fleet, accepts quotations (places purchase orders), sees everything for the IMO numbers verified on its account.
 *   - Local agency: the same for the vessels it is verified as appointed for; it may declare a new vessel, which stays "not verified" until the desk confirms.
 *   - Captain / authorized officer: raises requisitions for the vessel and follows their status; a purchase order is placed by the owner, the agency or the desk.
 *   - Crew member: personal orders only, and sees only their own.
 * Prices are always set by the desk in the quotation; a catalog price is only a list price. No payment is taken and no stock is moved here yet.
 */
class MarineOrders
{
  const STATUS = ['requested' => 'Requested', 'quoted' => 'Quoted', 'ordered' => 'Purchase order', 'preparing' => 'Preparing', 'delivered' => 'Delivered', 'declined' => 'Declined', 'cancelled' => 'Cancelled'];
  const OPEN = ['requested', 'quoted', 'ordered', 'preparing'];
  /** What the desk may move an order to from each status (quoting has its own form). */
  const NEXT = ['requested' => ['cancelled'], 'quoted' => ['ordered', 'cancelled'], 'ordered' => ['preparing', 'delivered', 'cancelled'], 'preparing' => ['delivered', 'cancelled'], 'delivered' => [], 'declined' => [], 'cancelled' => []];
  const PLACES = ['alongside' => 'Alongside (jetty or terminal)', 'anchorage' => 'At anchorage', 'unknown' => 'Not known yet'];
  const CALL = ['expected' => 'Expected', 'arrived' => 'In port', 'sailed' => 'Sailed', 'cancelled' => 'Cancelled'];
  const PORTS = ['chattogram' => 'Chattogram', 'mongla' => 'Mongla', 'payra' => 'Payra'];
  const MAX_LINES = 80;

  // ---------- pure rules (covered by the test script) ----------
  public static function number(int $id, ?int $year = null): string { return 'RQ-' . substr((string)($year ?: (int)date('Y')), -2) . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }
  public static function isCrew(object $a): bool { return $a->type === 'vessel' && ($a->vessel_role ?? 'crew') !== 'captain'; }
  /** IMO numbers an account may act for: verified fleet / appointed vessels, or a captain's own vessel. A crew member has none. */
  public static function scope(object $a, array $verifiedImos): array {
    if ($a->type === 'vessel') return (!self::isCrew($a) && !empty($a->imo)) ? [(string)$a->imo] : [];
    return array_values(array_unique(array_map('strval', $verifiedImos)));
  }
  public static function canRaise(object $a, string $kind): bool { if (($a->status ?? '') !== 'active') return false; return $kind === 'personal' ? $a->type === 'vessel' : !self::isCrew($a); }
  public static function canSee(object $a, object $o, array $scope): bool {
    if ((int)$o->account_id === (int)$a->id) return true;
    return $o->kind === 'vessel' && !self::isCrew($a) && !empty($o->imo) && in_array((string)$o->imo, $scope, true);
  }
  public static function expired(object $o, ?string $today = null): bool { return !empty($o->valid_until) && substr((string)$o->valid_until, 0, 10) < ($today ?: now('Asia/Dhaka')->toDateString()); }
  /** Accepting a quotation = placing the purchase order. Personal: the crew member. Vessel: an owner or agency that may see it. Never a captain, never an expired quotation. */
  public static function canAccept(object $a, object $o, array $scope, ?string $today = null): bool {
    if ($o->status !== 'quoted' || self::expired($o, $today) || ($a->status ?? '') !== 'active') return false;
    if ($o->kind === 'personal') return (int)$o->account_id === (int)$a->id;
    return in_array($a->type, ['owner', 'agency'], true) && self::canSee($a, $o, $scope);
  }
  /** The person who raised it may withdraw it before a purchase order exists. */
  public static function canWithdraw(object $a, object $o): bool { return (int)$o->account_id === (int)$a->id && in_array($o->status, ['requested', 'quoted'], true); }
  public static function canMove(string $from, string $to): bool { return in_array($to, self::NEXT[$from] ?? [], true); }
  /** [subtotal, total] in USD from priced, available lines plus the delivery charge. */
  public static function totals(array $lines, float $delivery): array {
    $s = 0.0; foreach ($lines as $l) if (!empty($l['available']) && isset($l['unit_usd']) && $l['unit_usd'] !== null) $s += round((float)$l['qty'] * (float)$l['unit_usd'], 2);
    $s = round($s, 2); return [$s, round($s + max(0.0, $delivery), 2)];
  }
  /** Lowest USD unit price that keeps the protected margin: cost in BDT x (1 + margin %) / BDT per USD. 0 when the cost or the rate is unknown. */
  public static function floorUsd(float $costBdt, float $marginPct, float $rate): float { return ($costBdt > 0 && $rate > 0) ? ceil($costBdt * (1 + $marginPct / 100) / $rate * 100 - 1e-9) / 100 : 0.0; }
  public static function cleanImo(?string $v): ?string { $d = preg_replace('/\D/', '', (string)$v); return strlen($d) === 7 ? $d : null; }
  public static function qty($v): ?float { if (!is_numeric($v)) return null; $q = round((float)$v, 2); return ($q > 0 && $q <= 100000) ? $q : null; }

  // ---------- data ----------
  public static function ready(): bool { try { return Schema::hasTable('marine_orders') && Schema::hasTable('marine_order_lines') && Schema::hasTable('marine_port_calls') && MarineAccess::ready(); } catch (\Throwable $e) { return false; } }
  public static function vessels(object $a, bool $verifiedOnly = true): array {
    if ($a->type === 'vessel') return $a->imo ? [(object)['imo' => (string)$a->imo, 'name' => $a->vessel, 'verified' => true]] : [];
    return DB::table('marine_account_vessels')->where('account_id', $a->id)->when($verifiedOnly, fn($w) => $w->where('verified', true))->orderBy('id')->get(['imo', 'name', 'verified'])->all();
  }
  public static function scopeOf(object $a): array { return self::scope($a, array_map(fn($v) => $v->imo, self::vessels($a))); }
  /** Orders this account may see: its own, plus vessel orders for the IMO numbers in its scope. */
  public static function visible(object $a) {
    $scope = self::scopeOf($a);
    return DB::table('marine_orders')->where(function ($w) use ($a, $scope) { $w->where('account_id', $a->id); if ($scope && !self::isCrew($a)) $w->orWhere(fn($x) => $x->where('kind', 'vessel')->whereIn('imo', $scope)); });
  }
  public static function callsVisible(object $a) {
    $scope = self::scopeOf($a);
    return DB::table('marine_port_calls')->where(function ($w) use ($a, $scope) { $w->where('account_id', $a->id); if ($scope) $w->orWhereIn('imo', $scope); });
  }
  public static function openCount(): int { try { return self::ready() ? (int)DB::table('marine_orders')->where('status', 'requested')->count() : 0; } catch (\Throwable $e) { return 0; } }
  /** Products a signed-in account may put on a requisition: on the marine channel, published, priced; bonded goods included (they are shown only after login), baby goods never. */
  public static function products(string $q, array $hidden, int $limit = 12) {
    $b = DB::table('marine_products as m')->join('products as p', 'p.id', '=', 'm.product_id')->where('m.enabled', true)->where('p.published', true)->whereNotNull('m.usd_price');
    if ($hidden) $b->where(fn($w) => $w->whereNull('p.category_id')->orWhereNotIn('p.category_id', $hidden));
    if ($q !== '') $b->where(fn($w) => $w->where('p.name', 'ilike', '%' . $q . '%')->orWhere('p.sku', 'ilike', '%' . $q . '%'));
    return $b->orderBy('p.name')->limit($limit);
  }
}
