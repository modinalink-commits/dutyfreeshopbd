<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Money moved between own accounts, and supplier payments paid out of an account — both leave a ledger trail in account_entries. */
class Transfers {
  public static function ready(): bool { return Schema::hasTable('account_transfers'); }
  public static function purpose(string $name, bool $isExpense = false): int {
    $id = DB::table('expense_purposes')->whereRaw('lower(name) = ?', [strtolower($name)])->value('id');
    return $id ? (int)$id : (int)DB::table('expense_purposes')->insertGetId(['name' => $name, 'is_expense' => $isExpense, 'created_at' => now(), 'updated_at' => now()]);
  }
  public static function balance(int $accountId): float {
    $a = DB::table('money_accounts')->find($accountId); if (!$a) return 0.0;
    $in = DB::table('account_entries')->where('account_id', $accountId)->where('direction', 'income')->sum('amount');
    $ex = DB::table('account_entries')->where('account_id', $accountId)->where('direction', 'expense')->sum('amount');
    return (float)$a->opening + (float)$in - (float)$ex;
  }
  /** One transfer = one row + an expense entry on the source + an income entry on the destination. */
  public static function make(int $from, int $to, float $amount, string $date, ?string $note, string $by): int {
    return DB::transaction(function () use ($from, $to, $amount, $date, $note, $by) {
      $fa = DB::table('money_accounts')->find($from); $ta = DB::table('money_accounts')->find($to);
      $id = DB::table('account_transfers')->insertGetId(['transfer_date' => $date, 'from_account_id' => $from, 'to_account_id' => $to, 'amount' => $amount, 'note' => $note, 'user_name' => $by, 'created_at' => now(), 'updated_at' => now()]);
      $code = 'TRF-'.str_pad($id, 6, '0', STR_PAD_LEFT); DB::table('account_transfers')->where('id', $id)->update(['code' => $code]);
      $p = self::purpose('Fund Transfer', false); $tail = $note ? ' — '.$note : '';
      DB::table('account_entries')->insert(['reference' => $code.'-OUT', 'direction' => 'expense', 'account_id' => $from, 'purpose_id' => $p, 'amount' => $amount, 'entry_date' => $date, 'comment' => 'Transfer to '.$ta->name.' ('.$code.')'.$tail, 'user_name' => $by, 'created_at' => now(), 'updated_at' => now()]);
      DB::table('account_entries')->insert(['reference' => $code.'-IN', 'direction' => 'income', 'account_id' => $to, 'purpose_id' => $p, 'amount' => $amount, 'entry_date' => $date, 'comment' => 'Transfer from '.$fa->name.' ('.$code.')'.$tail, 'user_name' => $by, 'created_at' => now(), 'updated_at' => now()]);
      return $id;
    });
  }
  public static function void(int $id, string $by): bool {
    return DB::transaction(function () use ($id, $by) {
      $t = DB::table('account_transfers')->lockForUpdate()->find($id); if (!$t || $t->status !== 'done') return false;
      DB::table('account_entries')->whereIn('reference', [$t->code.'-OUT', $t->code.'-IN'])->delete();
      DB::table('account_transfers')->where('id', $id)->update(['status' => 'voided', 'voided_by' => $by, 'voided_at' => now(), 'updated_at' => now()]); return true;
    });
  }
  /** A paid supplier payment taken from a money account -> expense entry (Supplier Bill). Safe to call twice. */
  public static function supplierPaid(int $payId): void {
    if (!Schema::hasColumn('supplier_payments', 'money_account_id')) return;
    $p = DB::table('supplier_payments')->find($payId); if (!$p || $p->status !== 'paid' || !$p->money_account_id) return;
    $ref = 'SUP-'.($p->code ?: $payId); if (DB::table('account_entries')->where('reference', $ref)->exists()) return;
    $s = DB::table('suppliers')->where('id', $p->supplier_id)->value('name');
    DB::table('account_entries')->insert(['reference' => $ref, 'direction' => 'expense', 'account_id' => $p->money_account_id, 'purpose_id' => self::purpose('Supplier Bill', false), 'amount' => $p->amount, 'entry_date' => $p->pay_date, 'comment' => 'Supplier payment '.($p->code ?: $payId).' to '.($s ?: 'supplier').($p->txn_id ? ' · txn '.$p->txn_id : ''), 'user_name' => $p->user_name ?: 'System', 'created_at' => now(), 'updated_at' => now()]);
  }
  public static function supplierUnpaid(int $payId): void {
    $code = DB::table('supplier_payments')->where('id', $payId)->value('code'); DB::table('account_entries')->whereIn('reference', array_filter(['SUP-'.$code, 'SUP-'.$payId]))->delete();
  }
}
