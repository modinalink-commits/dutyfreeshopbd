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
  /** Files a customer may attach to a requisition: extension => accepted real content types. Office files are zip or OLE containers, so those types are accepted for them. Never run, only downloaded by staff or the account itself. */
  const FILE_EXT = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'], 'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2', 'application/vnd.ms-office', 'application/octet-stream'],
    'csv' => ['text/plain', 'text/csv', 'application/csv'], 'txt' => ['text/plain']];
  const FILE_MAX = 5242880; const FILE_COUNT = 6; const TOTAL_LINES = 250;
  public static function fileProblem(string $ext, ?string $mime, int $size): ?string {
    $ext = strtolower($ext); if (!isset(self::FILE_EXT[$ext])) return 'Only Excel, Word, PDF, CSV or image files are accepted.';
    if (!in_array((string)$mime, self::FILE_EXT[$ext], true)) return 'The file content does not match its type.';
    if ($size <= 0 || $size > self::FILE_MAX) return 'Each file must be 5 MB or smaller.';
    return null;
  }

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

  /** Keeps one attached file on the private disk under a random name, linked to the order. Returns [document id or null, stored path or null, problem or null]. */
  public static function storeFile(?\Illuminate\Http\UploadedFile $f, int $orderId, ?int $accountId, string $side = 'account'): array {
    if (!$f || !$f->isValid()) return [null, null, 'The file did not arrive completely.'];
    $ext = strtolower((string)$f->getClientOriginalExtension()); $bad = self::fileProblem($ext, $f->getMimeType(), (int)$f->getSize()); if ($bad) return [null, null, $bad];
    if (DB::table('marine_documents')->where('order_id', $orderId)->count() >= 12) return [null, null, 'This order already has the maximum number of files.'];
    $path = $f->storeAs('marine-orders/' . $orderId, bin2hex(random_bytes(12)) . '.' . $ext, 'local'); if (!$path) return [null, null, 'The file could not be saved.'];
    $name = mb_substr(preg_replace('/[^\w .()\-]+/u', '_', (string)$f->getClientOriginalName()), 0, 190) ?: 'file.' . $ext;
    $id = DB::table('marine_documents')->insertGetId(['order_id' => $orderId, 'account_id' => $accountId, 'name' => $name, 'path' => $path, 'mime' => $f->getMimeType(), 'size' => (int)$f->getSize(), 'by_side' => $side, 'created_at' => now(), 'updated_at' => now()]);
    return [$id, $path, null];
  }
  /** Items read from a file -> order lines. A line is tied to a catalog product only on an exact code or exact name match; otherwise it stays the customer's own wording. Prices never come from the file. */
  /** Category ids a given account must never get: baby goods for everyone; tobacco and alcohol unless the account has bonded approval. [hidden ids, bonded allowed]. Null account = the desk. */
  public static function limits(?object $acc): array {
    $hid = \App\Http\Controllers\MarineController::hiddenIds(); if ($acc !== null) $hid = array_values(array_unique(array_merge($hid, \App\Http\Controllers\MarineController::tobaccoIds())));   // tobacco: admin only
    $ok = $acc === null || (!empty($acc->bonded_ok) && ($acc->status ?? '') === 'active');
    return [$ok ? $hid : array_values(array_unique(array_merge($hid, \App\Http\Controllers\MarineController::bondedIds()))), $ok];
  }
  public static function match(array $items, array $hidden, bool $bonded = false): array {
    $codes = []; $names = []; foreach ($items as $it) { if (!empty($it['code'])) $codes[mb_strtolower($it['code'])] = 1; $names[mb_strtolower($it['name'])] = 1; }
    $base = fn() => DB::table('marine_products as m')->join('products as p', 'p.id', '=', 'm.product_id')->where('m.enabled', true)->where('p.published', true)->whereNotNull('m.usd_price')->when($hidden, fn($w) => $w->where(fn($x) => $x->whereNull('p.category_id')->orWhereNotIn('p.category_id', $hidden)))->when(!$bonded && MarineCats::ready(), fn($w) => $w->where(fn($x) => $x->whereNull('m.mcat')->orWhere('m.mcat', '!=', 'bonded')));
    $bySku = []; $byName = []; $cols = ['p.id', 'p.sku', 'p.name', 'p.size', 'm.usd_price'];
    foreach (array_chunk(array_keys($codes), 200) as $ch) foreach ($base()->whereIn(DB::raw('lower(p.sku)'), array_map('strval', $ch))->get($cols) as $p) $bySku[mb_strtolower((string)$p->sku)] = $p;
    foreach (array_chunk(array_keys($names), 200) as $ch) foreach ($base()->whereIn(DB::raw('lower(p.name)'), array_map('strval', $ch))->get($cols) as $p) $byName[mb_strtolower((string)$p->name)] = $p;
    $out = [];
    foreach ($items as $it) {
      $p = (!empty($it['code']) ? ($bySku[mb_strtolower($it['code'])] ?? null) : null) ?: ($byName[mb_strtolower($it['name'])] ?? null);
      $out[] = $p ? ['product_id' => (int)$p->id, 'sku' => mb_substr((string)$p->sku, 0, 60), 'name' => mb_substr((string)$p->name, 0, 200), 'size' => mb_substr((string)($p->size ?? ''), 0, 80) ?: null, 'qty' => $it['qty'], 'unit' => $it['unit'] ?? null, 'list_usd' => $p->usd_price, 'note' => mb_strtolower((string)$p->name) !== mb_strtolower($it['name']) ? mb_substr('Your wording: ' . $it['name'], 0, 200) : null]
        : ['product_id' => null, 'sku' => !empty($it['code']) ? mb_substr((string)$it['code'], 0, 60) : null, 'name' => $it['name'], 'size' => null, 'qty' => $it['qty'], 'unit' => $it['unit'] ?? null, 'list_usd' => null, 'note' => null];
    }
    return $out;
  }

  // ---------- data ----------
  public static function ready(): bool { try { return Schema::hasTable('marine_orders') && Schema::hasTable('marine_order_lines') && Schema::hasTable('marine_port_calls') && Schema::hasColumn('marine_documents', 'order_id') && MarineAccess::ready(); } catch (\Throwable $e) { return false; } }
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
  public static function products(string $q, array $hidden, int $limit = 12, bool $bonded = false) {
    $b = DB::table('marine_products as m')->join('products as p', 'p.id', '=', 'm.product_id')->where('m.enabled', true)->where('p.published', true)->whereNotNull('m.usd_price');
    if (!$bonded && MarineCats::ready()) $b->where(fn($w) => $w->whereNull('m.mcat')->orWhere('m.mcat', '!=', 'bonded'));
    if ($hidden) $b->where(fn($w) => $w->whereNull('p.category_id')->orWhereNotIn('p.category_id', $hidden));
    if ($q !== '') $b->where(fn($w) => $w->where('p.name', 'ilike', '%' . $q . '%')->orWhere('p.sku', 'ilike', '%' . $q . '%'));
    return $b->orderBy('p.name')->limit($limit);
  }
}
