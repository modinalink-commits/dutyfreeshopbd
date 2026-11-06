<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI Verify: a decision-support layer. It checks one order against customer history, fraud signals, payment, stock, pricing and
 * communication, stores an explainable result (reason codes + every check) and NEVER cancels, blocks, refunds or blacklists anything.
 */
class AiVerify {
  const VERSION = 'v1.0';
  const LABEL = ['verified' => 'AI Verified', 'review' => 'Review Required', 'high' => 'High Risk', 'failed' => 'Verification Failed', 'pending' => 'Pending Verification'];
  const CANCEL = ['Cancel', 'Cancelled', 'Canceled'];
  const RET = ['Returned', 'Return'];
  const GROUPS = ['Customer', 'Fraud', 'Order', 'Payment', 'Courier', 'Stock', 'Pricing', 'History', 'Communication'];

  public static function tVerified(): int { return max(1, min(100, (int)Procure::set('ai_verify_t_verified', '85'))); }
  public static function tReview(): int { return max(0, min(self::tVerified() - 1, (int)Procure::set('ai_verify_t_review', '65'))); }
  private static function has(string $t, string $c): bool { static $m = []; $k = $t . '.' . $c; if (!isset($m[$k])) { try { $m[$k] = Schema::hasColumn($t, $c); } catch (\Throwable $e) { $m[$k] = false; } } return $m[$k]; }
  private static function tbl(string $t): bool { static $m = []; if (!isset($m[$t])) { try { $m[$t] = Schema::hasTable($t); } catch (\Throwable $e) { $m[$t] = false; } } return $m[$t]; }
  private static function tk($v): string { return '৳' . number_format((float)$v); }
  private static function tokens(string $s): array { return array_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s)) ?: [], fn($w) => mb_strlen($w) > 1)); }
  public static function sim(string $a, string $b): int { $x = self::tokens($a); $y = self::tokens($b); if (!$x || !$y) return 0; $i = count(array_intersect($x, $y)); $u = count(array_unique(array_merge($x, $y))); return $u ? (int)round($i / $u * 100) : 0; }
  public static function tier(int $delivered, ?string $stored): string { if ($stored && in_array($stored, ['New', 'Regular', 'Silver', 'Gold', 'Platinum', 'Diamond', 'VIP'], true)) return $stored; return $delivered >= 50 ? 'VIP' : ($delivered >= 20 ? 'Diamond' : ($delivered >= 10 ? 'Platinum' : ($delivered >= 6 ? 'Gold' : ($delivered >= 3 ? 'Silver' : ($delivered >= 1 ? 'Regular' : 'New'))))); }

  private static function fingerprint($o, $items): string { return md5(json_encode([$o->customer_name, $o->phone, $o->address, $o->district ?? '', $o->total, $o->paid_total ?? 0, $o->discount ?? 0, $o->status, $o->payment_method ?? '', $o->courier ?? '', collect($items)->map(fn($i) => [$i->product_id, $i->qty, $i->price])->all()])); }

  /** Latest stored result (with checks) or null. */
  public static function latest(int $orderId): ?array {
    $v = DB::table('order_ai_verifications')->where('order_id', $orderId)->first(); if (!$v) return null;
    $h = DB::table('order_ai_verification_history')->where('order_id', $orderId)->orderByDesc('id')->first();
    $checks = $h ? DB::table('order_ai_verification_checks')->where('history_id', $h->id)->orderBy('id')->get()->map(fn($c) => ['g' => $c->grp, 'c' => $c->code, 'r' => $c->result, 't' => $c->title, 'd' => $c->detail, 'p' => (int)$c->points])->all() : [];
    return self::pack($v, $checks);
  }
  private static function pack($v, array $checks): array {
    $eff = $v->override_status ?: $v->verification_status;
    $hist = DB::table('order_ai_verification_history')->where('order_id', $v->order_id)->orderByDesc('id')->limit(15)->get()->map(fn($h) => ['no' => $h->run_no, 'prev' => $h->prev_status ? (self::LABEL[$h->prev_status] ?? $h->prev_status) : '-', 'status' => self::LABEL[$h->status] ?? $h->status, 'score' => $h->score, 'by' => $h->triggered_by . ($h->user_name ? ' / ' . $h->user_name : ''), 'note' => $h->note, 'at' => \Carbon\Carbon::parse($h->created_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A')])->all();
    $reasons = array_values(array_filter($checks, fn($c) => in_array($c['r'], ['review', 'fail'], true)));
    return ['order_id' => (int)$v->order_id, 'status' => $eff, 'label' => self::LABEL[$eff] ?? $eff, 'ai_status' => $v->verification_status, 'ai_label' => self::LABEL[$v->verification_status] ?? $v->verification_status, 'score' => (int)$v->verification_score, 'risk' => $v->risk_level, 'reliability' => $v->customer_reliability, 'fraud' => $v->fraud_score,
      'summary' => json_decode($v->summary ?? '[]', true) ?: [], 'codes' => json_decode($v->reason_codes ?? '[]', true) ?: [], 'checks' => $checks, 'reasons' => $reasons, 'history' => $hist, 'version' => $v->rule_version, 'runs' => (int)$v->run_count,
      'at' => $v->verified_at ? \Carbon\Carbon::parse($v->verified_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') : '', 'by' => $v->triggered_by,
      'override' => $v->override_status ? ['status' => $v->override_status, 'label' => self::LABEL[$v->override_status] ?? $v->override_status, 'reason' => $v->override_reason, 'by' => $v->override_by, 'at' => $v->override_at ? \Carbon\Carbon::parse($v->override_at)->timezone('Asia/Dhaka')->format('d M Y, h:i A') : ''] : null,
      'threshold' => ['verified' => self::tVerified(), 'review' => self::tReview()]];
  }

  /** Run all checks, store the snapshot/history/checks, return the packed result. $live=false never calls the external fraud APIs (used for automatic runs). */
  public static function run(int $orderId, string $by = 'manual', bool $live = true, bool $force = true): ?array {
    $o = DB::table('orders')->find($orderId); if (!$o) return null;
    $items = DB::table('order_items')->where('order_id', $orderId)->get();
    $fp = self::fingerprint($o, $items);
    $prev = DB::table('order_ai_verifications')->where('order_id', $orderId)->first();
    if (!$force && $prev && $prev->fingerprint === $fp) return self::latest($orderId);

    $phone = FraudCheck::phone((string)$o->phone); $now = now(); $created = $o->created_at;
    $checks = []; $ded = 0; $bonus = 0; $crit = []; $sum = []; $codes = [];
    $add = function (string $g, string $code, string $r, string $t, string $d, int $pts = 0, bool $c = false) use (&$checks, &$ded, &$crit, &$codes) {
      $checks[] = ['g' => $g, 'c' => $code, 'r' => $r, 't' => $t, 'd' => $d, 'p' => $pts]; if ($pts > 0) $ded += $pts; if ($pts < 0) $GLOBALS['__aiv_bonus'] = ($GLOBALS['__aiv_bonus'] ?? 0) - $pts;
      if ($c && $r !== 'pass') $crit[] = $code; if ($r !== 'pass' && $r !== 'info') $codes[] = $code; elseif ($r === 'pass') $codes[] = $code; };
    $GLOBALS['__aiv_bonus'] = 0;

    // ---- Data completeness ----
    $miss = []; if (trim((string)$o->customer_name) === '') $miss[] = 'Customer name'; if (mb_strlen(trim((string)$o->address)) < 8) $miss[] = 'Address'; if (!count($items)) $miss[] = 'Products'; if ((float)$o->total <= 0) $miss[] = 'Amount';
    $soft = []; if (trim((string)($o->payment_method ?? '')) === '') $soft[] = 'Payment method'; if (trim((string)($o->district ?? '')) === '') $soft[] = 'District/Area';
    if ($miss) $add('Order', 'DATA_INCOMPLETE', 'fail', 'Order data completeness', 'Missing critical data: ' . implode(', ', $miss), 40, true);
    elseif ($soft) $add('Order', 'DATA_PARTIAL', 'review', 'Order data completeness', 'Missing: ' . implode(', ', $soft), 4);
    else $add('Order', 'DATA_COMPLETE', 'pass', 'Order data completeness', 'Name, phone, address, products, amount, payment and delivery area are all present');

    // ---- Mobile ----
    $validPhone = (bool)preg_match('/^01[3-9]\d{8}$/', $phone);
    if ($validPhone) $add('Customer', 'PHONE_VALID', 'pass', 'Mobile number format', "Valid Bangladesh mobile (01XXXXXXXXX): $phone");
    else $add('Customer', 'INVALID_MOBILE', 'fail', 'Mobile number format', 'Not a valid 11-digit Bangladesh mobile starting with 01', 60, true);

    // ---- History for this phone ----
    $same = fn() => DB::table('orders')->whereRaw('right(phone,11) = ?', [$phone])->where('id', '<>', $o->id);
    $h = $validPhone ? $same()->selectRaw("count(*) as n, count(*) filter (where status='Delivered') as d, count(*) filter (where status in ('Cancel','Cancelled','Canceled')) as c, count(*) filter (where status in ('Returned','Return')) as r, count(*) filter (where status='Partial Delivered') as pd, coalesce(avg(total) filter (where status not in ('Cancel','Cancelled','Canceled','Returned','Return','Incomplete')),0) as avgv, count(*) filter (where status in ('Cancel','Cancelled','Canceled') and created_at > now() - interval '30 days') as c30")->first() : null;
    $n = (int)($h->n ?? 0); $d = (int)($h->d ?? 0); $c = (int)($h->c ?? 0); $r = (int)($h->r ?? 0); $pd = (int)($h->pd ?? 0); $pend = max(0, $n - $d - $c - $r - $pd); $avg = (float)($h->avgv ?? 0);
    $stored = ($validPhone && self::has('orders', 'customer_category')) ? $same()->whereNotNull('customer_category')->orderByDesc('id')->value('customer_category') : null;
    $tier = self::tier($d, $stored);
    if ($n > 0) {
      $add('History', $d >= 3 ? 'LONG_CUSTOMER_HISTORY' : 'PHONE_MATCH', 'pass', 'Previous orders', "$n previous order(s) on this phone: $d delivered, $c cancelled, $r returned" . ($pd ? ", $pd partial" : '') . ", $pend in progress", $d >= 3 ? -4 : 0);
    } else $add('History', 'NEW_CUSTOMER', 'info', 'Previous orders', 'First order on this phone number: no history to compare', 3);
    $fin = $d + $pd + $c + $r; $rel = $fin > 0 ? (int)round(($d + $pd * 0.5) / $fin * 100) : null;
    if ($rel === null) $add('Customer', 'RELIABILITY_UNKNOWN', 'info', 'Customer reliability', 'No finished orders yet (new customer)');
    elseif ($rel >= 80) $add('Customer', 'RELIABILITY_GOOD', 'pass', 'Customer reliability', "$rel% (delivered $d, cancelled $c, returned $r)");
    elseif ($rel >= 60) $add('Customer', 'RELIABILITY_LOW', 'review', 'Customer reliability', "$rel% (delivered $d, cancelled $c, returned $r)", 10);
    else $add('Customer', 'RELIABILITY_POOR', 'review', 'Customer reliability', "$rel% (delivered $d, cancelled $c, returned $r)", 22);
    $add('Customer', 'TIER', 'pass', 'Customer tier', $tier . ' customer (supports confidence only, never overrides fraud evidence)', in_array($tier, ['Gold', 'Platinum', 'Diamond', 'VIP'], true) ? -3 : 0);

    // ---- Name / address consistency ----
    $names = $validPhone ? $same()->whereNotNull('customer_name')->distinct()->pluck('customer_name')->all() : [];
    if ($names) {
      $best = 0; foreach ($names as $nm) { similar_text(mb_strtolower(trim((string)$nm)), mb_strtolower(trim((string)$o->customer_name)), $pc); $best = max($best, (int)round($pc)); }
      if ($best >= 60) $add('Customer', 'NAME_MATCH', 'pass', 'Name match', "Name matches a previous order on this phone ($best%)");
      else $add('Customer', 'NAME_CHANGED', 'review', 'Name match', 'A different name than previous orders on this phone (a signal only, not fraud by itself): ' . implode(', ', array_slice($names, 0, 3)), 5);
    }
    $pa = $validPhone ? $same()->whereNotNull('address')->orderByRaw("case when status in ('Delivered','Partial Delivered') then 0 else 1 end")->orderByDesc('id')->limit(6)->get(['address', 'status']) : collect();
    if ($pa->count()) {
      $bs = 0; foreach ($pa as $p) $bs = max($bs, self::sim((string)$o->address, (string)$p->address));
      if ($bs >= 70) { $add('Customer', 'ADDRESS_MATCH', 'pass', 'Address consistency', "Address match: High ($bs%), similar to a previous address"); $sum['address'] = 'High'; }
      elseif ($bs >= 40) { $add('Customer', 'ADDRESS_PARTIAL', 'review', 'Address consistency', "Address match: Medium ($bs%)", 4); $sum['address'] = 'Medium'; }
      else { $add('Customer', 'ADDRESS_CHANGED', 'review', 'Address consistency', "Address match: Low ($bs%), a new delivery address (a signal only)", 8); $sum['address'] = 'Low'; }
    } else { $add('Customer', 'ADDRESS_NEW', 'info', 'Address consistency', 'No previous address to compare'); $sum['address'] = 'New'; }

    // ---- Fraud ----
    $fraudScore = null; $fraudRisk = 'Unknown';
    if ($validPhone) {
      if ($fc && ($fc['ok'] ?? false) && ($fc['total_parcel'] ?? 0) > 0) {
        $lv = (string)($fc['risk_level'] ?? 'unknown'); $sr = (float)($fc['success_rate'] ?? 0); $fraudScore = (int)round(100 - $sr); $tp = (int)$fc['total_parcel'];
        $txt = "Courier network: $tp parcels, success " . round($sr) . '%, return ' . round((float)($fc['return_rate'] ?? 0)) . '%. Fraud score ' . $fraudScore . '/100';
        if (in_array($lv, ['very_low', 'low'], true)) { $fraudRisk = 'Low'; $add('Fraud', 'FRAUD_LOW', 'pass', 'Fraud check', $txt . ' - Low risk'); }
        elseif ($lv === 'medium') { $fraudRisk = 'Medium'; $add('Fraud', 'FRAUD_SCORE_MEDIUM', 'review', 'Fraud check', $txt . ' - Medium risk', 14); }
        elseif ($lv === 'high') { $fraudRisk = 'High'; $add('Fraud', 'FRAUD_SCORE_HIGH', 'fail', 'Fraud check', $txt . ' - High risk', 32, true); }
        else { $fraudRisk = 'Very high'; $add('Fraud', 'FRAUD_SCORE_HIGH', 'fail', 'Fraud check', $txt . ' - Very high risk', 48, true); $crit[] = 'FRAUD_VERY_HIGH'; }
        $sum['courier_rate'] = (int)round($sr);
      } elseif ($fc && ($fc['ok'] ?? false)) { $fraudScore = 0; $fraudRisk = 'Low'; $add('Fraud', 'FRAUD_NO_HISTORY', 'pass', 'Fraud check', 'No courier history on this number in the connected networks (nothing negative found)'); }
      else $add('Fraud', 'FRAUD_UNAVAILABLE', 'info', 'Fraud check', $live ? 'Courier fraud network could not be reached' : 'No recent fraud check stored; press Re-Verify to run the live courier check', 2);
    }
    if ($n >= 3 && ($c + $r) / max(1, $n) >= 0.4) $add('Fraud', 'CANCEL_RATE_HIGH', 'review', 'Cancel / return pattern', "$c cancelled + $r returned out of $n previous orders", 12);
    elseif ((int)($h->c30 ?? 0) >= 2) $add('Fraud', 'CANCEL_RATE_HIGH', 'review', 'Cancel / return pattern', (int)$h->c30 . ' cancelled orders in the last 30 days', 12);
    elseif ($r >= 2) $add('Fraud', 'RETURN_RATE_HIGH', 'review', 'Cancel / return pattern', "$r previous returned orders", 12);
    else $add('Fraud', 'PATTERN_OK', 'pass', 'Cancel / return pattern', 'No repeated cancellations or returns');

    // ---- Duplicate ----
    $sig = $items->map(fn($i) => ((int)$i->product_id) . 'x' . (int)$i->qty)->sort()->implode(',');
    $dupe = null;
    if ($validPhone) {
      $cands = $same()->whereNotIn('status', array_merge(self::CANCEL, ['Incomplete']))->whereBetween('created_at', [\Carbon\Carbon::parse($created)->subDay(), \Carbon\Carbon::parse($created)->addDay()])->get(['id', 'invoice', 'total']);
      foreach ($cands as $cd) { $s2 = DB::table('order_items')->where('order_id', $cd->id)->get()->map(fn($i) => ((int)$i->product_id) . 'x' . (int)$i->qty)->sort()->implode(','); if ($s2 === $sig || abs((float)$cd->total - (float)$o->total) < 1) { $dupe = $cd; break; } }
    }
    if ($dupe) $add('Order', 'DUPLICATE_ORDER', 'review', 'Duplicate order', 'Possible duplicate of ' . $dupe->invoice . ' (same phone, within 24h, same products or amount). Flagged only; nothing is cancelled.', 25, true);
    else $add('Order', 'NO_DUPLICATE', 'pass', 'Duplicate order', 'No duplicate found for this phone in the last 24 hours');

    // ---- Amount / product risk ----
    $total = (float)$o->total; $qtySum = (int)$items->sum('qty');
    if ($avg > 0 && $total > $avg * 3) $add('Order', 'AMOUNT_HIGH', 'review', 'Order amount', 'Order ' . self::tk($total) . ' is ' . round($total / $avg, 1) . '× this customer\'s average (' . self::tk($avg) . ')', $total > $avg * 4 ? 15 : 10);
    elseif ($avg > 0) $add('Order', 'AMOUNT_NORMAL', 'pass', 'Order amount', 'Order ' . self::tk($total) . ' vs customer average ' . self::tk($avg));
    else $add('Order', 'AMOUNT_NO_BASE', 'info', 'Order amount', 'Order ' . self::tk($total) . ' (no earlier order to compare)');
    $hv = $items->filter(fn($i) => (float)$i->price * (int)$i->qty >= 10000)->count();
    if ($qtySum >= 10 || $hv) $add('Order', 'PRODUCT_RISK', 'review', 'Product risk', ($qtySum >= 10 ? "Large quantity ($qtySum units). " : '') . ($hv ? "$hv high-value line(s) (৳10,000+). " : '') . 'This raises review priority only.', 4);
    else $add('Order', 'PRODUCT_NORMAL', 'pass', 'Product risk', "$qtySum unit(s), no unusually high-value line");

    // ---- Payment ----
    $method = trim((string)($o->payment_method ?? '')); $isCod = $method === '' || stripos($method, 'cash') !== false || stripos($method, 'cod') !== false;
    $paid = (float)($o->paid_total ?? 0); $due = max(0, $total - $paid); $pst = (string)($o->payment_status ?? $o->pay_status ?? '');
    $pp = (self::tbl('order_payments')) ? DB::table('order_payments')->where('order_id', $o->id)->selectRaw("count(*) filter (where status='pending') as p, count(*) filter (where status='rejected') as rj, count(*) filter (where status='verified') as v")->first() : null;
    if ($paid > $total + 1) $add('Payment', 'PAYMENT_MISMATCH', 'fail', 'Payment', 'Paid ' . self::tk($paid) . ' is more than the order total ' . self::tk($total), 35, true);
    elseif ($pp && (int)$pp->p > 0) $add('Payment', 'PAYMENT_REVIEW', 'review', 'Payment', 'Payment verification is still pending (' . (int)$pp->p . ' payment record). Payment Review Required.', 15, true);
    elseif (!$isCod && $due > 1 && in_array($o->status, ['Approved', 'Packaging', 'Shipment', 'Partial Delivered', 'Delivered'], true)) $add('Payment', 'PAYMENT_REVIEW', 'review', 'Payment', "$method order is " . $o->status . ' but ' . self::tk($due) . ' is still unpaid. Payment Review Required.', 15, true);
    elseif ($isCod) $add('Payment', 'PAYMENT_COD_OK', 'pass', 'Payment', 'Cash on delivery: COD amount ' . self::tk($due) . ($total >= 20000 ? ' (large COD amount)' : ''), $total >= 20000 ? 5 : 0);
    else $add('Payment', 'PAYMENT_OK', 'pass', 'Payment', "$method: paid " . self::tk($paid) . ', due ' . self::tk($due) . ($pst ? " ($pst)" : ''));
    $sum['payment'] = $isCod ? 'COD' : ($due <= 1 ? 'Paid' : 'Due ' . self::tk($due)); $sum['due'] = $due;

    // ---- Courier history ----
    $fc = null;
    if ($validPhone) { try { $fc = $live ? FraudCheck::check($phone) : null; if (!$live) { $row = DB::table('fraud_checks')->where('phone', $phone)->where('created_at', '>', now()->subDay())->orderByDesc('id')->first(); $fc = $row && $row->data ? json_decode($row->data, true) : null; } } catch (\Throwable $e) { $fc = null; } }
    if ($validPhone && self::has('orders', 'consignment_id')) {
      $cr = $same()->whereNotNull('consignment_id')->selectRaw("count(*) as t, count(*) filter (where status in ('Delivered','Partial Delivered')) as ok, count(*) filter (where status in ('Returned','Return')" . (self::has('orders', 'courier_stage') ? " or courier_stage in ('Returned','Failed Delivery')" : '') . ") as bad")->first();
      $t = (int)($cr->ok ?? 0) + (int)($cr->bad ?? 0);
      if ($t >= 2) { $rate = (int)round((int)$cr->ok / $t * 100); $sum['courier_rate'] = $sum['courier_rate'] ?? $rate;
        if ($rate >= 80) $add('Courier', 'COURIER_OK', 'pass', 'Courier history', "Own courier success rate $rate% ($cr->ok delivered, $cr->bad returned/failed)");
        else $add('Courier', 'COURIER_LOW', 'review', 'Courier history', "Own courier success rate $rate% ($cr->ok delivered, $cr->bad returned/failed)", 10); }
      elseif ($fc && ($fc['ok'] ?? false) && (int)($fc['total_parcel'] ?? 0) > 0) { $sr = (int)round((float)($fc['success_rate'] ?? 0)); $tp = (int)$fc['total_parcel'];
        $add('Courier', $sr >= 80 ? 'COURIER_OK' : 'COURIER_LOW', $sr >= 80 ? 'pass' : 'review', 'Courier history', "Courier network: $tp parcels on this number, success $sr%, return " . round((float)($fc['return_rate'] ?? 0)) . '%', $sr >= 80 ? 0 : 10); }
      else $add('Courier', 'COURIER_NONE', 'info', 'Courier history', 'No earlier courier parcels found for this number (own orders or the courier network)');
    }

    // ---- Parcels still with the courier (earlier orders of this phone) ----
    if ($validPhone) {
      try {
        $q = $same()->where('id', '!=', $o->id)->where('status', 'Shipment');
        $tr = $q->orderByDesc('id')->limit(5)->get(['id', 'invoice']);
        if ($tr->count()) {
          $pn = []; foreach ($tr as $t0) foreach (DB::table('order_items')->where('order_id', $t0->id)->limit(3)->pluck('name') as $nm) $pn[] = $nm;
          $add('Courier', 'COURIER_IN_TRANSIT', 'review', 'Parcel already with courier', $tr->count() . ' earlier order(s) of this customer are still with the courier (' . $tr->pluck('invoice')->implode(', ') . ')' . ($pn ? ': ' . implode(', ', array_slice(array_unique($pn), 0, 3)) : '') . '. Check before shipping another parcel.', 10);
        }
      } catch (\Throwable $e) {}
    }

    // ---- Stock ----
    $short = []; $pids = $items->pluck('product_id')->filter()->unique()->values()->all();
    $stk = $pids ? DB::table('products')->whereIn('id', $pids)->pluck('stock', 'id')->all() : [];
    $need = []; foreach ($items as $i) if ($i->product_id) $need[(int)$i->product_id] = ($need[(int)$i->product_id] ?? 0) + (int)$i->qty;
    $deducted = self::has('orders', 'stock_deducted_at') && !empty($o->stock_deducted_at);
    foreach ($need as $pid => $q) if ((int)($stk[$pid] ?? 0) < $q) $short[] = (string)(optional($items->firstWhere('product_id', $pid))->name) . ' (need ' . $q . ', have ' . (int)($stk[$pid] ?? 0) . ')';
    if ($deducted) { $add('Stock', 'STOCK_ALLOCATED', 'pass', 'Stock', 'Stock is already deducted for this order'); $sum['stock'] = 'Allocated'; }
    elseif ($short) { $add('Stock', 'STOCK_SHORTAGE', 'review', 'Stock', 'Not enough stock: ' . implode('; ', array_slice($short, 0, 3)) . '. No stock means no shipment.', 15, true); $sum['stock'] = 'Short'; }
    else { $add('Stock', 'STOCK_OK', 'pass', 'Stock', 'Required quantity is available'); $sum['stock'] = 'Available'; }

    // ---- Pricing / margin ----
    $below = []; $marginTxt = 'Safe';
    try {
      $cost = $pids ? ProfitFloor::costs($pids, $need) : []; $sub = (float)$items->sum(fn($i) => (float)$i->price * (int)$i->qty); $disc = (float)($o->discount ?? 0);
      foreach ($items as $i) { $pid = (int)$i->product_id; $cr0 = $cost[$pid] ?? null; if (!$cr0 || (float)$cr0->cost <= 0) continue; $gross = (float)$i->price * (int)$i->qty; $net = $gross - ($sub > 0 ? $disc * $gross / $sub : 0); $fl = ProfitFloor::floor((float)$cr0->cost, (string)$created) * (int)$i->qty; if ($net + 0.01 < $fl) $below[] = $i->name; }
    } catch (\Throwable $e) {}
    $pct = ProfitFloor::pct((string)$created);
    if ($below) { $marginTxt = 'Below minimum'; $add('Pricing', 'LOW_MARGIN', 'review', 'Pricing & margin', 'Below protected margin (' . $pct . '%): ' . implode(', ', array_slice($below, 0, 3)) . '. A pricing issue, not a customer-fraud signal.', 12); }
    else $add('Pricing', 'MARGIN_OK', 'pass', 'Pricing & margin', "Above the $pct% protected margin");
    $sum['margin'] = $marginTxt;
    $dsub = (float)$items->sum(fn($i) => (float)$i->price * (int)$i->qty); $dsc = (float)($o->discount ?? 0); $dty = (string)($o->discount_type ?? ''); $cp = (string)($o->coupon ?? '');
    $cpn = ($cp !== '' && $validPhone) ? $same()->where('coupon', $cp)->where('created_at', '>', now()->subDays(30))->count() : 0;
    if ($dsc > 0 && (stripos($dty, 'manual') !== false || $dsc > $dsub * 0.15 || $cpn >= 3)) $add('Pricing', 'DISCOUNT_REVIEW', 'review', 'Discount check', 'Pricing Review: ' . (stripos($dty, 'manual') !== false ? 'manual discount override. ' : '') . ($dsc > $dsub * 0.15 ? 'discount ' . self::tk($dsc) . ' is above 15% of the order. ' : '') . ($cpn >= 3 ? "coupon $cp used by this phone $cpn times in 30 days." : ''), 8);
    else $add('Pricing', 'DISCOUNT_OK', 'pass', 'Discount check', $dsc > 0 ? 'Discount ' . self::tk($dsc) . ' is within normal limits' : 'No discount applied');

    // ---- Communication ----
    $call = null;
    if ($validPhone && self::tbl('call_logs')) { try { $call = DB::table('call_logs')->whereRaw('right(phone,11) = ?', [$phone])->where('status', 'answered')->where(function ($q) use ($o, $created) { $q->where('order_id', $o->id)->orWhere('started_at', '>=', \Carbon\Carbon::parse($created)->subHours(2)); })->orderByDesc('id')->first(); } catch (\Throwable $e) { $call = null; } }
    if ($call) $add('Communication', 'CALL_CONFIRMED', 'pass', 'Call verification', 'Call answered by the customer' . ($call->user_name ? ' (staff: ' . $call->user_name . ')' : '') . ($call->started_at ? ', ' . \Carbon\Carbon::parse($call->started_at)->timezone('Asia/Dhaka')->format('d M, h:i A') : '') . '. Strong supporting evidence.', -8);
    else $add('Communication', 'NO_CALL', 'info', 'Call verification', 'Confirmation call pending: call the customer to confirm this order. An answered call here turns this green (-8 risk).');

    // ---- Status consistency ----
    if (in_array($o->status, ['Shipment', 'Delivered'], true) && !$deducted && $short) $add('Order', 'STATUS_STOCK', 'review', 'Order status consistency', 'Order is ' . $o->status . ' but stock is short', 10);
    else $add('Order', 'STATUS_OK', 'pass', 'Order status consistency', 'Order status (' . $o->status . ') agrees with payment and stock');

    // ---- Score & final status ----
    $bonus = (int)($GLOBALS['__aiv_bonus'] ?? 0); unset($GLOBALS['__aiv_bonus']);
    $score = max(0, min(100, 100 - $ded + $bonus)); $codesU = array_values(array_unique($codes));
    $incomplete = collect($checks)->contains(fn($x) => in_array($x['c'], ['DATA_INCOMPLETE', 'INVALID_MOBILE'], true));
    if ($incomplete) $status = 'failed'; elseif ($score >= self::tVerified()) $status = 'verified'; elseif ($score >= self::tReview()) $status = 'review'; else $status = 'high';
    if ($status === 'verified' && $crit) $status = 'review';
    if (in_array('FRAUD_VERY_HIGH', $crit, true) && $status !== 'failed') $status = 'high';
    $risk = $status === 'verified' ? 'Low' : ($status === 'review' ? 'Medium' : 'High');
    $sum += ['tier' => $tier, 'prev_orders' => $n, 'phone_match' => $n > 0 ? 'Yes' : 'New', 'fraud_risk' => $fraudRisk, 'name_match' => $names ? 'see Customer' : '-'];
    $sum['critical'] = array_values(array_unique($crit));

    // ---- Store ----
    $by = $by ?: 'manual'; $user = auth()->user()->name ?? null; $runNo = ($prev->run_count ?? 0) + 1; $prevStatus = $prev ? ($prev->override_status ?: $prev->verification_status) : null;
    $snap = ['order' => ['id' => $o->id, 'invoice' => $o->invoice, 'status' => $o->status, 'total' => $total, 'paid' => $paid, 'discount' => $dsc, 'payment_method' => $method, 'district' => $o->district ?? null, 'items' => $items->map(fn($i) => ['pid' => $i->product_id, 'name' => $i->name, 'qty' => (int)$i->qty, 'price' => (float)$i->price])->all()],
      'customer' => ['previous_orders' => $n, 'delivered' => $d, 'cancelled' => $c, 'returned' => $r, 'reliability' => $rel, 'tier' => $tier], 'fraud' => ['score' => $fraudScore, 'risk' => $fraudRisk], 'thresholds' => ['verified' => self::tVerified(), 'review' => self::tReview()]];
    DB::transaction(function () use ($o, $prev, $status, $score, $risk, $rel, $fraudScore, $codesU, $sum, $fp, $by, $runNo, $prevStatus, $snap, $checks, $user, $now) {
      $row = ['customer_id' => $o->customer_id ?? null, 'verification_score' => $score, 'verification_status' => $status, 'risk_level' => $risk, 'customer_reliability' => $rel, 'fraud_score' => $fraudScore, 'reason_codes' => json_encode($codesU), 'summary' => json_encode($sum, JSON_UNESCAPED_UNICODE), 'rule_version' => self::VERSION, 'fingerprint' => $fp, 'verified_at' => $now, 'triggered_by' => $by, 'run_count' => $runNo, 'updated_at' => $now];
      if ($prev) { if ($prev->fingerprint !== $fp) $row += ['override_status' => null, 'override_reason' => null, 'override_by' => null, 'override_at' => null]; DB::table('order_ai_verifications')->where('id', $prev->id)->update($row); }
      else DB::table('order_ai_verifications')->insert($row + ['order_id' => $o->id, 'created_at' => $now]);
      $hid = DB::table('order_ai_verification_history')->insertGetId(['order_id' => $o->id, 'run_no' => $runNo, 'prev_status' => $prevStatus, 'status' => $status, 'score' => $score, 'risk_level' => $risk, 'customer_reliability' => $rel, 'fraud_score' => $fraudScore, 'reason_codes' => json_encode($codesU), 'snapshot' => json_encode($snap, JSON_UNESCAPED_UNICODE), 'rule_version' => self::VERSION, 'triggered_by' => $by, 'user_name' => $user, 'created_at' => $now]);
      DB::table('order_ai_verification_checks')->insert(array_map(fn($x) => ['history_id' => $hid, 'order_id' => $o->id, 'grp' => $x['g'], 'code' => $x['c'], 'result' => $x['r'], 'title' => $x['t'], 'detail' => $x['d'], 'points' => $x['p']], $checks));
    });
    Procure::audit('AI Verify run', 'Order #' . $o->id, $prevStatus, ['status' => $status, 'score' => $score, 'by' => $by, 'critical' => $sum['critical']]);
    return self::latest((int)$o->id);
  }

  /** Staff override of the AI status only (never touches the operational order status). */
  public static function override(int $orderId, string $status, string $reason): ?array {
    $v = DB::table('order_ai_verifications')->where('order_id', $orderId)->first(); if (!$v || !isset(self::LABEL[$status])) return null;
    $old = $v->override_status ?: $v->verification_status; $now = now(); $u = auth()->user()->name ?? 'staff';
    DB::table('order_ai_verifications')->where('id', $v->id)->update(['override_status' => $status === $v->verification_status ? null : $status, 'override_reason' => mb_substr($reason, 0, 300), 'override_by' => $u, 'override_at' => $now, 'updated_at' => $now]);
    DB::table('order_ai_verification_history')->insert(['order_id' => $orderId, 'run_no' => (int)$v->run_count, 'prev_status' => $old, 'status' => $status, 'score' => $v->verification_score, 'risk_level' => $v->risk_level, 'customer_reliability' => $v->customer_reliability, 'fraud_score' => $v->fraud_score, 'reason_codes' => $v->reason_codes, 'snapshot' => json_encode(['override' => true]), 'rule_version' => $v->rule_version, 'triggered_by' => 'staff override', 'user_name' => $u, 'note' => mb_substr($reason, 0, 300), 'created_at' => $now]);
    Procure::audit('AI Verify override', 'Order #' . $orderId, $old, ['status' => $status, 'reason' => $reason]);
    return self::latest($orderId);
  }
}
