<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/**
 * Pre-Shipment Validation (Packaging -> Courier). Every check returns a severity:
 *   INFO (nothing to do) · WARNING (may ship, notice shown) · CRITICAL (needs an authorised override with a reason) · BLOCKED (never ships).
 * Hard rules: no stock, invalid phone or missing address are BLOCKED and have no override. Margin below the protected % is CRITICAL (override only for
 * users allowed to override margin). A check that cannot run never blocks a parcel; it reports INFO "not checked".
 */
class ShipCheck {
  const SEV = ['INFO' => 0, 'WARNING' => 1, 'CRITICAL' => 2, 'BLOCKED' => 3];
  private static function c(string $key, string $label, string $sev, string $msg, array $d = []): array { return ['key'=>$key, 'label'=>$label, 'sev'=>$sev, 'msg'=>$msg, 'detail'=>$d]; }

  /** @return array{order:string, checks:array, passed:int, total:int, worst:string, overridable:bool, blocked:bool} */
  public static function run(int $id): array {
    $o = DB::table('orders')->find($id); if (!$o) return ['order'=>(string)$id, 'checks'=>[], 'passed'=>0, 'total'=>0, 'worst'=>'BLOCKED', 'overridable'=>false, 'blocked'=>true];
    $fns = ['stock', 'margin', 'phone', 'address', 'payment', 'ai', 'duplicate', 'note'];
    $out = [];
    foreach ($fns as $f) { try { $r = self::$f($o); } catch (\Throwable $e) { report($e); $r = self::c($f, ucfirst($f), 'INFO', 'Not checked (temporary problem).'); } $out[] = $r; }
    $worst = 'INFO'; $pass = 0;
    foreach ($out as $r) { if (self::SEV[$r['sev']] > self::SEV[$worst]) $worst = $r['sev']; if ($r['sev'] === 'INFO') $pass++; }
    $blocked = $worst === 'BLOCKED';
    return ['order'=>(string)Inv::show($o->invoice), 'checks'=>$out, 'passed'=>$pass, 'total'=>count($out), 'worst'=>$worst, 'overridable'=>!$blocked && $worst === 'CRITICAL', 'blocked'=>$blocked];
  }

  private static function stock(object $o): array {
    if ($o->stock_deducted_at !== null || $o->duplicate_of_order_id !== null) return self::c('stock', 'Stock', 'INFO', 'Stock already handled.');
    $rows = DB::table('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')->where('i.order_id', $o->id)->whereNotNull('p.stock')
      ->groupBy('p.id', 'p.name', 'p.stock')->selectRaw('p.name, p.stock, sum(i.qty) need')->get();
    $short = []; $low = [];
    foreach ($rows as $r) { $need = (int)$r->need; $have = max(0, (int)$r->stock); if ($need > $have) $short[] = ['product'=>$r->name, 'required'=>$need, 'available'=>$have, 'short'=>$need - $have]; elseif ($have - $need <= 2) $low[] = $r->name; }
    if ($short) return self::c('stock', 'Stock', 'BLOCKED', 'Insufficient stock — No Stock = No Shipment. Stock In the product first.', $short);
    if ($low) return self::c('stock', 'Stock', 'WARNING', 'Stock is enough but almost finished: '.implode(', ', array_slice($low, 0, 3)).'.');
    return self::c('stock', 'Stock', 'INFO', 'Required quantity is available.');
  }
  private static function margin(object $o): array {
    if ($o->stock_deducted_at !== null || $o->duplicate_of_order_id !== null || !Costing::ready()) return self::c('margin', 'Margin', 'INFO', 'Not applicable.');
    $mode = Costing::shipMode(); if ($mode === 'off') return self::c('margin', 'Margin', 'INFO', 'Profit protection at shipment is switched off.');
    $bad = Costing::shipViolations((int)$o->id);
    if (!$bad) return self::c('margin', 'Margin', 'INFO', 'Margin is at or above the protected '.ProfitFloor::pct($o->created_at ?? null).'%.');
    $d = array_map(fn($x) => ['product'=>$x['name'], 'qty'=>$x['qty'], 'fifo_cost'=>round((float)$x['cost'], 2), 'revenue'=>round((float)$x['rev'], 2), 'required_min'=>round((float)$x['min'], 2), 'short'=>round((float)$x['short'], 2)], array_slice($bad, 0, 6));
    return self::c('margin', 'Margin', $mode === 'warn' ? 'WARNING' : 'CRITICAL', ($mode === 'warn' ? 'Margin warning' : 'Critical margin issue').' — below the protected '.ProfitFloor::pct($o->created_at ?? null).'% profit on the actual batch cost.', $d);
  }
  private static function phone(object $o): array {
    $d = preg_replace('/\D/', '', (string)$o->phone); $d = substr($d, -11);
    return preg_match('/^01[3-9]\d{8}$/', $d) ? self::c('phone', 'Phone', 'INFO', 'Valid Bangladesh mobile number.') : self::c('phone', 'Phone', 'BLOCKED', 'Mobile number is missing or not a valid Bangladesh number — the courier cannot call the customer.');
  }
  private static function address(object $o): array {
    $a = trim((string)$o->address);
    if (mb_strlen($a) < 8) return self::c('address', 'Address', 'BLOCKED', 'Delivery address is missing or too short.');
    if (trim((string)$o->district) === '') return self::c('address', 'Address', 'WARNING', 'District is not set; the courier may need to confirm the area.');
    return self::c('address', 'Address', 'INFO', 'Address and district present.');
  }
  private static function payment(object $o): array {
    $m = strtolower((string)($o->payment_method ?? '')); $cod = $m === '' || str_contains($m, 'cod') || str_contains($m, 'cash');
    if ($cod) return self::c('payment', 'Payment', 'INFO', 'Cash on Delivery — courier collects ৳'.number_format((float)$o->total).'.');
    $ok = ($o->payment_status ?? '') === 'Paid' || ($o->pay_status ?? '') === 'Verified';
    return $ok ? self::c('payment', 'Payment', 'INFO', 'Online payment verified.') : self::c('payment', 'Payment', 'WARNING', 'Online payment is not verified yet ('.($o->payment_method).').');
  }
  private static function ai(object $o): array {
    if (!DB::getSchemaBuilder()->hasTable('order_ai_verifications')) return self::c('ai', 'AI Verification', 'INFO', 'Not available.');
    $v = DB::table('order_ai_verifications')->where('order_id', $o->id)->first();
    if (!$v) return self::c('ai', 'AI Verification', 'WARNING', 'No AI verification result for this order.');
    $s = strtolower((string)($v->override_status ?: $v->verification_status));
    if ($s === 'verified') return self::c('ai', 'AI Verification', 'INFO', 'AI verified.');
    if (str_contains($s, 'high') || $s === 'failed') return self::c('ai', 'AI Verification', 'CRITICAL', 'AI result: '.($v->override_status ?: $v->verification_status).'. Check the reasons before sending.');
    return self::c('ai', 'AI Verification', 'WARNING', 'AI result: '.($v->override_status ?: $v->verification_status).'.');
  }
  private static function duplicate(object $o): array {
    $x = DupGuard::others($o);
    if (!$x) return self::c('duplicate', 'Duplicate order', 'INFO', 'No similar order today.');
    return !empty($o->dup_allowed_at) ? self::c('duplicate', 'Duplicate order', 'INFO', 'Duplicate was reviewed and allowed.') : self::c('duplicate', 'Duplicate order', 'WARNING', 'Same phone has '.count($x).' other order(s) today: '.implode(', ', array_map(fn($y) => '#'.$y['invoice'], $x)).'.');
  }
  private static function note(object $o): array {
    $n = trim((string)$o->note);
    return $n !== '' ? self::c('note', 'Courier note', 'INFO', 'Courier note: '.mb_substr($n, 0, 90)) : self::c('note', 'Courier note', 'INFO', 'No customer note; the courier gets the order without a note.');
  }
}
