<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
/**
 * Owner rule (6 Nov 2026): when one customer (same phone) places 2+ orders on the same day, those orders need a MANUAL permission before they can go on
 * to Approved / Packaging / WFP / Shipment, even if AI Verify approved them and even if staff moved them out of AI Approve. The gate is enforced on the server
 * (single, quick and bulk status change); staff open the order list, check with the customer, and press "Allow" (logged with name and time).
 */
class DupGuard {
  /** Owner rule (8 Nov 2026): an earlier order of the same customer still in Packaging / WFP / Shipment / Late Delivery makes a NEW order need manual permission too (shown as a flag on New, never auto-approved). */
  const INFLIGHT = ['Packaging', 'WFP', 'Shipment', 'Late Delivery'];
  /** Owner rule (8 Nov 2026): if the customer's last order was returned / partly delivered, the next order is also manual-only with a warning. */
  const RETURNISH = ['Return Pending', 'Returned', 'Partial Delivered'];
  const GATED = ['Approved', 'Packaging', 'WFP', 'Shipment'];
  public static function ready(): bool { static $r = null; if ($r === null) { try { $r = Schema::hasColumn('orders', 'dup_allowed_at'); } catch (\Throwable $e) { $r = false; } } return $r; }
  /** Other same-day, non-cancelled orders of the same phone. Returns [['id'=>..,'invoice'=>..], ...] */
  public static function others(object $o): array {
    $ph = trim((string)($o->phone ?? '')); if ($ph === '' || empty($o->created_at)) return [];
    $d = Carbon::parse($o->created_at)->timezone('Asia/Dhaka'); $a = $d->copy()->startOfDay()->timezone(config('app.timezone', 'UTC')); $b = $d->copy()->endOfDay()->timezone(config('app.timezone', 'UTC'));
    $last = DB::table('orders')->where('phone', $ph)->where('id', '<', $o->id)->whereNotIn('status', ['Cancel', 'Incomplete'])->orderByDesc('id')->first(['id', 'invoice', 'status']);
    $ret = ($last && in_array($last->status, self::RETURNISH, true)) ? $last->id : 0; // the customer's LAST order came back / is incomplete
    return DB::table('orders')->where('phone', $ph)->where('id', '<>', $o->id)->whereNotIn('status', ['Cancel', 'Incomplete'])->where(fn($w) => $w->whereBetween('created_at', [$a, $b])->orWhere(fn($x) => $x->where('id', '<', $o->id)->whereIn('status', self::INFLIGHT))->orWhere('id', $ret))->orderBy('id')->get(['id', 'invoice'])->map(fn($x) => ['id' => (int)$x->id, 'invoice' => (string)Inv::show($x->invoice)])->all();
  }
  /** Message when this status change must wait for a manual permission, else null. */
  public static function block(object $o, string $to): ?string {
    if (!self::ready() || $o->status === $to || !in_array($to, self::GATED, true) || !empty($o->dup_allowed_at)) return null;
    $x = self::others($o); if (!$x) return null;
    return 'Duplicate order: this customer has ' . (count($x) + 1) . ' orders today, or an earlier one still in Packaging/Shipment or returned/partly delivered (' . implode(', ', array_map(fn($y) => '#' . $y['invoice'], $x)) . '). Check with the customer, then press "Allow" on this order in the list. It cannot go to ' . $to . ' without that manual permission.';
  }
  /** For the order list: [orderId => ['n'=>count, 'inv'=>[...], 'ok'=>bool, 'by'=>name]] for a page of orders that are not yet past the gate. */
  public static function forPage(array $orders): array {
    if (!self::ready()) return [];
    $out = []; $need = [];
    foreach ($orders as $o) { if (in_array($o->status, ['Cancel', 'Incomplete', 'Delivered', 'Returned'], true)) continue; $ph = trim((string)($o->phone ?? '')); if ($ph === '' || empty($o->created_at)) continue; $need[] = [$o, $ph]; }
    if (!$need) return [];
    // One query for the whole page (was two per row): every live order of the phones shown, then the same rule as others() applied in memory.
    $by = DB::table('orders')->whereIn('phone', array_values(array_unique(array_column($need, 1))))->whereNotIn('status', ['Cancel', 'Incomplete'])->orderBy('id')->get(['id', 'phone', 'invoice', 'status', 'created_at'])->groupBy('phone');
    foreach ($need as [$o, $ph]) {
      $list = $by[$ph] ?? collect(); if ($list->isEmpty()) continue;
      $day = Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->toDateString(); $oid = (int)$o->id;
      $last = null; foreach ($list as $r) { if ((int)$r->id < $oid) $last = $r; }
      $ret = ($last && in_array($last->status, self::RETURNISH, true)) ? (int)$last->id : 0;
      $x = [];
      foreach ($list as $r) { $rid = (int)$r->id; if ($rid === $oid) continue;
        if (Carbon::parse($r->created_at)->timezone('Asia/Dhaka')->toDateString() === $day || ($rid < $oid && in_array($r->status, self::INFLIGHT, true)) || $rid === $ret) $x[] = (string)Inv::show($r->invoice); }
      if ($x) $out[$oid] = ['n' => count($x) + 1, 'inv' => $x, 'ok' => !empty($o->dup_allowed_at), 'by' => (string)($o->dup_allowed_by ?? '')];
    }
    return $out;
  }
}
