<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner: "Brac Bank Limited" is the real Brac account. Recurring masters (and their unpaid items) pointed at the duplicate "Brac Bank"; repoint them so auto-deduct and Pay use the real account. Nothing is deleted. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('recurring_expenses')) return;
    $new = DB::table('money_accounts')->whereRaw("lower(name) = 'brac bank limited'")->value('id');
    $old = DB::table('money_accounts')->whereRaw("lower(name) = 'brac bank'")->value('id');
    if (!$new || !$old) return;
    DB::table('recurring_expenses')->where('account_id', $old)->update(['account_id' => $new]);
    if (Schema::hasTable('recurring_expense_instances')) DB::table('recurring_expense_instances')->where('account_id', $old)->where('payment_status', '!=', 'paid')->update(['account_id' => $new]);
  }
  public function down(): void {}
};
