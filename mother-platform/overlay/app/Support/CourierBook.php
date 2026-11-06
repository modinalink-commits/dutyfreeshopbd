<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/**
 * Courier money in the Income book.
 * - Delivered but not yet paid out by the courier = RECEIVABLE per courier (see receivable()); it is NOT income and touches no account balance.
 * - When the courier really pays (Pathao invoice line with a payout), the payout is booked once as Income in the "Pathao Courier" account
 *   (reference PAY-<invoice>-<consignment>). Courier fees are already deducted in that payout, and the Profit/Loss report takes revenue from orders and
 *   courier fees from the orders, never from these income rows, so profit is not counted twice.
 */
class CourierBook {
  const ACCOUNT = 'Pathao Courier';
  public static function account(): int {
    $id = DB::table('money_accounts')->where('name', self::ACCOUNT)->value('id');
    return $id ? (int)$id : (int)DB::table('money_accounts')->insertGetId(['name'=>self::ACCOUNT, 'type'=>'courier', 'opening'=>0, 'active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
  }
  /** Book one invoice line's payout as income; safe to call twice. */
  public static function payout(object $row, ?object $o = null): float {
    $amt = round((float)$row->paid_out, 2); if ($amt <= 0) return 0.0;
    $ref = 'PAY-'.$row->invoice_no.'-'.($row->consignment_id ?: $row->id);
    if (DB::table('account_entries')->where('reference', $ref)->exists()) return 0.0;
    $d = $row->line_date ? \Carbon\Carbon::parse($row->line_date)->toDateString() : now('Asia/Dhaka')->toDateString();
    DB::table('account_entries')->insert(['reference'=>$ref, 'direction'=>'income', 'account_id'=>self::account(), 'amount'=>$amt, 'comment'=>'Pathao payout, invoice '.$row->invoice_no.($o ? ' — order '.Inv::show($o->invoice) : '').' (delivery and COD charges already deducted)', 'entry_date'=>$d, 'user_name'=>'System (Pathao invoice)', 'created_at'=>now(), 'updated_at'=>now()]);
    return $amt;
  }
  /** Delivered parcels whose money the courier has not paid yet, per courier: [courier => [parcels, amount]]. */
  public static function receivable(): array {
    $rows = DB::table('orders')->where('courier_stage', 'Delivered')->whereNull('pathao_settled_at')->whereNotNull('consignment_id')
      ->groupBy('courier')->selectRaw("coalesce(courier,'Pathao') c, count(*) n, coalesce(sum(coalesce(collected_amount, courier_cod, total)),0) s")->get();
    $o = []; foreach ($rows as $r) $o[$r->c] = [(int)$r->n, (float)$r->s]; return $o;
  }
}
