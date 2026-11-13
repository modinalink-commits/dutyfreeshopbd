<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clearance sale: stock that is about to be lost (near expiry, damaged, old stock) may be sold below the protected margin, down to its purchase cost,
 * but only as an approved, logged and self-ending exception. Everything else keeps the normal profit floor.
 * - Only the Super Admin starts/approves/ends a clearance; an Admin can only send a request.
 * - The clearance price may go down to the purchase cost, never below it.
 * - While active: the product's list price is the clearance price, the margin alert and the shipment check accept it (never anything below it).
 * - It ends by itself when the end date passes, the stock is finished, or new stock arrives; the normal price comes back.
 */
class Clearance {
  const REASONS = ['near_expiry' => 'মেয়াদ শেষের পথে / নষ্ট হওয়ার পথে', 'damaged' => 'ড্যামেজ / প্যাকেট নষ্ট', 'old_stock' => 'পুরনো স্টক', 'other' => 'অন্য কারণ'];
  private static ?bool $ready = null; private static ?array $act = null;

  public static function ready(): bool { if (self::$ready === null) { try { self::$ready = Schema::hasTable('product_clearances'); } catch (\Throwable $e) { self::$ready = false; } } return self::$ready; }
  /** The owner-level roles (same rule as the Deployment Center's Super Admin actions): super and admin. */
  public static function isSuper(): bool { try { return in_array(auth()->user()->role ?? '', ['super', 'admin'], true); } catch (\Throwable $e) { return false; } }
  /** @return array<int,object> product id => active clearance row */
  public static function active(): array {
    if (self::$act !== null) return self::$act; if (!self::ready()) return self::$act = [];
    try { $o = []; foreach (DB::table('product_clearances')->where('status', 'active')->get() as $r) $o[(int)$r->product_id] = $r; return self::$act = $o; } catch (\Throwable $e) { return []; }
  }
  public static function on(int $pid): bool { return isset(self::active()[$pid]); }
  /** Clearance unit price that was in force for this product at a moment (order time), or null. */
  public static function priceAt(int $pid, ?string $at): ?float {
    if (!self::ready()) return null;
    try {
      $q = DB::table('product_clearances')->where('product_id', $pid)->whereIn('status', ['active', 'ended'])->whereNotNull('started_at');
      if ($at) $q->where('started_at', '<=', $at)->where(fn($w) => $w->whereNull('ended_at')->orWhere('ended_at', '>=', $at)); else $q->where('status', 'active');
      $v = $q->orderByDesc('id')->value('price'); return $v === null ? null : (float)$v;
    } catch (\Throwable $e) { return null; }
  }
  /** Current FIFO purchase cost of one unit (0 when unknown). */
  public static function cost(int $pid): float { try { return (float)(DB::table('products as p')->where('p.id', $pid)->selectRaw(Costing::fifoSql('p') . ' as c')->value('c') ?? 0); } catch (\Throwable $e) { return 0.0; } }
  private static function who(): string { try { return (string)(auth()->user()->name ?? 'System'); } catch (\Throwable $e) { return 'System'; } }
  private static function flush(int $pid): void { self::$act = null; try { BundlePricing::flush(); BundlePricing::sync([$pid]); } catch (\Throwable $e) {} try { \App\Http\Controllers\StoreController::bust(); } catch (\Throwable $e) {} }
  private static function history(int $pid, float $old, float $new, float $cost, string $note): void {
    try { DB::table('product_price_history')->insert(['product_id' => $pid, 'old_rate' => $old, 'new_rate' => $new, 'purchase_rate' => $cost, 'changed_by' => self::who(), 'source' => 'Clearance', 'note' => mb_substr($note, 0, 250), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }

  /** Create a request (Admin) or start at once (Super Admin). @return array{0:bool,1:string} */
  public static function request(int $pid, float $price, string $reason, ?string $endsOn, ?string $note): array {
    if (!self::ready()) return [false, 'Clearance is not installed yet.'];
    $p = DB::table('products')->where('id', $pid)->first(); if (!$p) return [false, 'Product not found.'];
    if ($price < 1) return [false, 'Enter the clearance price.'];
    if ($price + 0.004 >= (float)$p->price && !self::on($pid)) return [false, 'The clearance price must be lower than the current price (' . number_format((float)$p->price) . ').'];
    if (DB::table('product_clearances')->where('product_id', $pid)->whereIn('status', ['active', 'pending'])->exists()) return [false, 'This product already has an active or pending clearance.'];
    if (!isset(self::REASONS[$reason])) $reason = 'other';
    $cost = self::cost($pid);
    // Owner policy: clearance may go down to the purchase cost, never below it (delivery and packaging are already lost at cost price).
    if ($cost <= 0) return [false, 'এই প্রোডাক্টের কেনা দাম (cost) সিস্টেমে নেই, তাই ক্লিয়ারেন্স দেওয়া যাবে না। আগে Purchase/Stock In করুন।'];
    if ($price + 0.004 < $cost) return [false, 'ক্লিয়ারেন্স দাম কেনা দামের (' . number_format($cost, 2) . ') নিচে হতে পারবে না।'];
    $id = DB::table('product_clearances')->insertGetId(['product_id' => $pid, 'reason' => $reason, 'note' => $note ? mb_substr($note, 0, 300) : null, 'price' => round($price, 2), 'normal_price' => (float)$p->price, 'normal_compare' => $p->compare_price,
      'cost_at_start' => $cost, 'stock_at_start' => $p->stock, 'ends_on' => $endsOn ?: null, 'status' => 'pending', 'requested_by' => self::who(), 'created_at' => now(), 'updated_at' => now()]);
    Procure::audit('clearance.request', 'Clearance requested: ' . $p->name, ['price' => (float)$p->price], ['clearance_price' => $price, 'reason' => $reason, 'ends_on' => $endsOn]);
    if (self::isSuper()) return self::approve($id);
    return [true, 'Request sent. The Super Admin must approve it before the price changes.'];
  }
  public static function approve(int $id): array {
    if (!self::isSuper()) return [false, 'Only the Super Admin can approve a clearance.'];
    return DB::transaction(function () use ($id) {
      $c = DB::table('product_clearances')->where('id', $id)->lockForUpdate()->first(); if (!$c || $c->status !== 'pending') return [false, 'This request is not pending.'];
      $p = DB::table('products')->where('id', $c->product_id)->lockForUpdate()->first(); if (!$p) return [false, 'Product not found.'];
      $old = (float)$p->price; $cmp = ($p->compare_price !== null && (float)$p->compare_price > $old) ? (float)$p->compare_price : $old; // the normal price is shown crossed out
      DB::table('products')->where('id', $p->id)->update(['price' => (float)$c->price, 'compare_price' => $cmp, 'updated_at' => now()]);
      DB::table('product_clearances')->where('id', $id)->update(['status' => 'active', 'normal_price' => $old, 'normal_compare' => $p->compare_price, 'stock_at_start' => $p->stock, 'approved_by' => self::who(), 'started_at' => now(), 'updated_at' => now()]);
      self::history((int)$p->id, $old, (float)$c->price, (float)$c->cost_at_start, 'Clearance started (' . $c->reason . ')');
      Procure::audit('clearance.start', 'Clearance started: ' . $p->name, ['price' => $old], ['price' => (float)$c->price, 'reason' => $c->reason, 'ends_on' => $c->ends_on]);
      self::flush((int)$p->id);
      return [true, 'Clearance is active: ' . $p->name . ' now sells at ' . number_format((float)$c->price) . '.'];
    });
  }
  public static function reject(int $id): array {
    if (!self::isSuper()) return [false, 'Only the Super Admin can reject a request.'];
    $n = DB::table('product_clearances')->where('id', $id)->where('status', 'pending')->update(['status' => 'rejected', 'approved_by' => self::who(), 'ended_at' => now(), 'ended_reason' => 'Rejected', 'updated_at' => now()]);
    if ($n) Procure::audit('clearance.reject', 'Clearance request rejected #' . $id);
    return [$n > 0, $n ? 'Request rejected. Nothing was changed.' : 'This request is not pending.'];
  }
  /** End an active clearance and bring the normal price back (only if the price is still the clearance price). */
  public static function end(int $id, string $why): array {
    return DB::transaction(function () use ($id, $why) {
      $c = DB::table('product_clearances')->where('id', $id)->lockForUpdate()->first(); if (!$c || $c->status !== 'active') return [false, 'This clearance is not active.'];
      $p = DB::table('products')->where('id', $c->product_id)->lockForUpdate()->first();
      if ($p && abs((float)$p->price - (float)$c->price) < 0.005) {
        DB::table('products')->where('id', $p->id)->update(['price' => (float)$c->normal_price, 'compare_price' => $c->normal_compare, 'updated_at' => now()]);
        self::history((int)$p->id, (float)$c->price, (float)$c->normal_price, (float)$c->cost_at_start, 'Clearance ended: ' . $why);
      }
      DB::table('product_clearances')->where('id', $id)->update(['status' => 'ended', 'ended_at' => now(), 'ended_reason' => mb_substr($why, 0, 120), 'updated_at' => now()]);
      Procure::audit('clearance.end', 'Clearance ended: ' . ($p->name ?? ('#' . $c->product_id)), ['price' => (float)$c->price], ['price' => (float)$c->normal_price, 'why' => $why]);
      self::flush((int)$c->product_id);
      return [true, 'Clearance ended. The normal price is back.'];
    });
  }
  /** Scheduler: end by date, by finished stock, or when new stock arrives (new stock must never sell at the clearance price by accident). */
  public static function tick(): void {
    if (!self::ready()) return;
    try {
      $today = now('Asia/Dhaka')->toDateString();
      foreach (DB::table('product_clearances as c')->leftJoin('products as p', 'p.id', '=', 'c.product_id')->where('c.status', 'active')->get(['c.id', 'c.ends_on', 'c.stock_at_start', 'p.stock', 'p.id as pid']) as $r) {
        if (!$r->pid) { self::end((int)$r->id, 'Product no longer exists'); continue; }
        if ($r->ends_on && (string)$r->ends_on < $today) { self::end((int)$r->id, 'End date passed'); continue; }
        if ($r->stock !== null && (int)$r->stock <= 0 && $r->stock_at_start !== null && (int)$r->stock_at_start > 0) { self::end((int)$r->id, 'Stock finished'); continue; }
        if ($r->stock !== null && $r->stock_at_start !== null && (int)$r->stock > (int)$r->stock_at_start) { self::end((int)$r->id, 'New stock received'); continue; }
      }
    } catch (\Throwable $e) { report($e); }
  }
  /** Units sold and loss during a clearance run (cancelled / returned orders are not counted). */
  public static function stats(object $c): array {
    if (!$c->started_at) return ['qty' => 0, 'rev' => 0.0, 'loss' => 0.0];
    try {
      $q = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.product_id', $c->product_id)->where('o.created_at', '>=', $c->started_at)->whereNotIn('o.status', ['Cancel', 'Returned', 'Return Pending', 'Incomplete']);
      if ($c->ended_at) $q->where('o.created_at', '<=', $c->ended_at);
      $r = $q->selectRaw('coalesce(sum(i.qty),0) q, coalesce(sum(i.qty * i.price),0) v')->first();
      $qty = (int)$r->q; $rev = (float)$r->v; return ['qty' => $qty, 'rev' => $rev, 'loss' => max(0.0, round($qty * (float)$c->cost_at_start - $rev, 2))];
    } catch (\Throwable $e) { return ['qty' => 0, 'rev' => 0.0, 'loss' => 0.0]; }
  }
}
