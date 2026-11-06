<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/**
 * Courier money in the Income book, fully automatic.
 * - The moment the courier reports a parcel Delivered with COD collected, deliver() books Income = COD collected minus the courier charge
 *   (reference DEL-<invoice>) into that courier's account ("<Courier> Courier"). Money the courier has not paid out yet is shown per courier by receivable().
 * - When a courier payout invoice is later imported, payout() only books the difference against what was already booked (reference ADJ-...).
 * The Profit/Loss report takes revenue from orders and courier fees from orders, never from these income rows, so profit is not counted twice.
 */
class CourierBook {
  public static function account(?string $courier = null): int {
    // Owner rule (6 Nov 2026): courier money is deposited into the BRAC Bank account, so it is booked there directly.
    try {
      $bid = DB::table('money_accounts')->where('active', true)->whereRaw("name ilike '%brac%'")->orderByRaw("case when type = 'bank' then 0 else 1 end")->orderBy('id')->value('id');
      if ($bid) return (int)$bid;
    } catch (\Throwable $e) { report($e); }
    $name = trim((string)($courier ?: 'Pathao')).' Courier';
    $id = DB::table('money_accounts')->where('name', $name)->value('id');
    return $id ? (int)$id : (int)DB::table('money_accounts')->insertGetId(['name'=>$name, 'type'=>'courier', 'opening'=>0, 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
  }
  /** Delivered COD parcel: book (COD collected - courier charge) as income once. Returns the amount booked. */
  public static function deliver(int $orderId): float {
    $o = DB::table('orders')->find($orderId); if (!$o || ($o->courier_stage ?? '') !== 'Delivered') return 0.0;
    $cod = (float)($o->collected_amount ?? $o->courier_cod ?? 0); if ($cod <= 0) return 0.0;
    $ref = 'DEL-'.$o->invoice; if (DB::table('account_entries')->where('reference', $ref)->exists()) return 0.0;
    if ($o->consignment_id && DB::table('account_entries')->where('reference', 'like', 'PAY-%-'.$o->consignment_id)->exists()) return 0.0; // payout already booked
    [$fee, $basis] = self::fee($o, $cod); $amt = round($cod - $fee, 2); if ($amt <= 0) return 0.0;
    $d = $o->courier_delivered_at ? \Carbon\Carbon::parse($o->courier_delivered_at)->setTimezone('Asia/Dhaka')->toDateString() : now('Asia/Dhaka')->toDateString();
    DB::table('account_entries')->insert(['reference'=>$ref, 'invoice'=>(string)Inv::show($o->invoice), 'direction'=>'income', 'account_id'=>self::account($o->courier), 'amount'=>$amt, 'comment'=>'COD delivered ৳'.number_format($cod).' − courier charge ৳'.number_format($fee, 2).' ('.$basis.') — order '.Inv::show($o->invoice).self::track($o), 'entry_date'=>$d, 'user_name'=>'System (Courier delivered)', 'created_at'=>now(), 'updated_at'=>now()]);
    return $amt;
  }
  /** Courier charge actually deducted from the COD. Order: real payout-invoice line > delivery fee + COD fee (+ discount) from the courier payload > delivery fee + learned COD rate. Returns [fee, basis]. */
  public static function fee(object $o, float $cod): array {
    try { $l = $o->consignment_id ? DB::table('pathao_invoice_lines')->where('consignment_id', $o->consignment_id)->orderByDesc('id')->first() : null; } catch (\Throwable $e) { $l = null; }
    if ($l && (float)$l->fee > 0) return [round((float)$l->fee, 2), 'Pathao invoice'];
    if (($o->courier ?: 'Pathao') === 'Pathao') {   // Owner rule (6 Nov 2026): Pathao charge is fixed at the panel's "Charge" (৳90) until the payout invoice brings the exact figure
      $flat = (float)(Procure::set('pathao_flat_charge', '90') ?? 90); if ($flat > 0) return [round($flat, 2), 'Pathao fixed charge'];
    }
    $del = max(0.0, (float)($o->courier_fee ?? 0)); $cf = null; $disc = 0.0;
    $pj = json_decode((string)($o->pathao_raw_payload ?? ''), true);
    if (is_array($pj)) { foreach (['cod_fee', 'cod_charge', 'cod'] as $k) if (isset($pj[$k]) && is_numeric($pj[$k])) { $cf = (float)$pj[$k]; break; } foreach (['discount', 'discount_amount', 'promo_discount'] as $k) if (isset($pj[$k]) && is_numeric($pj[$k])) { $disc = (float)$pj[$k]; break; } }
    if ($cf === null && $disc > 0) return [max(0.0, round($del - $disc, 2)), 'delivery fee − discount'];
    if ($cf !== null) return [max(0.0, round($del + $cf - $disc, 2)), 'delivery + COD fee'];
    $rate = self::codRate();
    if ($rate > 0 && ($o->courier ?: 'Pathao') === 'Pathao') return [round($del + $cod * $rate, 2), 'delivery + COD fee '.round($rate * 100, 2).'%'];
    return [$del, 'delivery fee only'];
  }
  /** COD fee share learned from imported Pathao invoice lines (sum cod_fee / sum collected). 0 when no data. */
  public static function codRate(): float {
    return (float)\Illuminate\Support\Facades\Cache::remember('courier.cod.rate', 3600, function () {
      try { $r = DB::table('pathao_invoice_lines')->where('collected', '>', 0)->selectRaw('coalesce(sum(cod_fee),0) c, coalesce(sum(collected),0) s')->first(); return $r && $r->s > 0 ? min(0.05, max(0, round($r->c / $r->s, 4))) : 0; } catch (\Throwable $e) { return 0; }
    });
  }
  /** Income-list helpers: split the " — Track: <url>" suffix from a comment. Safe for any value. */
  public static function trackUrl($v): ?string { return is_string($v) && preg_match('~ — Track: (https://\S+)~u', $v, $m) ? $m[1] : null; }
  public static function trackText($v) { return is_string($v) ? trim((string)preg_replace('~ — Track: \S+~u', '', $v)) : $v; }
  /** Public courier tracking link for the Income comment. */
  public static function track(object $o): string {
    $c = trim((string)($o->consignment_id ?? '')); if ($c === '') return '';
    $cr = $o->courier ?: 'Pathao';
    if ($cr === 'Pathao') return ' — Track: https://merchant.pathao.com/tracking?consignment_id='.rawurlencode($c).'&phone='.preg_replace('/\D/', '', (string)($o->phone ?? ''));
    if ($cr === 'Steadfast') return ' — Track: https://steadfast.com.bd/t/'.rawurlencode((string)($o->tracking_code ?? $c));
    return ' — Consignment '.$c;
  }
  /** Payout invoice line: if delivery already booked this order, book only the difference; otherwise book the payout. Safe to call twice. */
  public static function payout(object $row, ?object $o = null): float {
    $amt = round((float)$row->paid_out, 2); if ($amt <= 0) return 0.0;
    $ref = 'PAY-'.$row->invoice_no.'-'.($row->consignment_id ?: $row->id);
    if (DB::table('account_entries')->where('reference', $ref)->exists()) return 0.0;
    $d = $row->line_date ? \Carbon\Carbon::parse($row->line_date)->toDateString() : now('Asia/Dhaka')->toDateString();
    $del = $o ? DB::table('account_entries')->where('reference', 'DEL-'.$o->invoice)->first() : null;
    if ($del) {
      $diff = round($amt - (float)$del->amount, 2); if (abs($diff) < 0.5) return 0.0;
      DB::table('account_entries')->insert(['reference'=>$ref, 'invoice'=>$o ? (string)Inv::show($o->invoice) : null, 'direction'=>$diff > 0 ? 'income' : 'expense', 'account_id'=>$del->account_id, 'amount'=>abs($diff), 'comment'=>'Adjustment: actual payout ৳'.number_format($amt, 2).' vs booked ৳'.number_format((float)$del->amount, 2).', invoice '.$row->invoice_no.' — order '.Inv::show($o->invoice), 'entry_date'=>$d, 'user_name'=>'System (Pathao invoice)', 'created_at'=>now(), 'updated_at'=>now()]);
      return $diff;
    }
    DB::table('account_entries')->insert(['reference'=>$ref, 'invoice'=>$o ? (string)Inv::show($o->invoice) : null, 'direction'=>'income', 'account_id'=>self::account($o->courier ?? null), 'amount'=>$amt, 'comment'=>'Pathao payout, invoice '.$row->invoice_no.($o ? ' — order '.Inv::show($o->invoice) : '').' (charges already deducted)', 'entry_date'=>$d, 'user_name'=>'System (Pathao invoice)', 'created_at'=>now(), 'updated_at'=>now()]);
    return $amt;
  }
  /** Delivered parcels whose money the courier has not paid yet, per courier: [courier => [parcels, amount]]. */
  public static function receivable(): array {
    $rows = DB::table('orders')->where('courier_stage', 'Delivered')->whereNull('pathao_settled_at')->whereNotNull('consignment_id')->where('courier_delivered_at', '>=', '2026-11-01')
      ->groupBy('courier')->selectRaw("coalesce(courier,'Pathao') c, count(*) n, coalesce(sum(coalesce(collected_amount, courier_cod, total)),0) s")->get();
    $o = []; foreach ($rows as $r) $o[$r->c] = [(int)$r->n, (float)$r->s]; return $o;
  }
}
