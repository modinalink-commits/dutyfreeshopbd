<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner: one account per bank. Merge duplicate money_accounts (short seeded names vs imported long names) into one: every reference is repointed, opening balances added, the duplicate deleted. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('money_accounts')) return;
    // [duplicate name => [kept name, final display name]]
    $map = [
      'brac bank' => ['brac bank limited', 'Brac Bank Limited'],
      'city bank' => ['the city bank limited', 'The City Bank Limited'],
      'eastern bank' => ['eastern bank limited', 'Eastern Bank Limited'],
      'islami bank' => ['islami bank limited', 'Islami Bank Limited'],
      'mutual trust bank' => ['mutual trust bank plc', 'Mutual Trust Bank PLC'],
      'nagod personal' => ['nagad personal', 'Nagad Personal'],
      'bkash personal' => ['bkash personal', 'bKash Personal'],
    ];
    $cols = DB::select("select table_name t, column_name c from information_schema.columns where table_schema = current_schema() and table_name <> 'money_accounts' and column_name in ('account_id','money_account_id','from_account','to_account','from_account_id','to_account_id','paid_from_account_id','payment_account_id') and data_type in ('integer','bigint','smallint')");
    DB::transaction(function () use ($map, $cols) {
      $all = DB::table('money_accounts')->get();
      $by = [];
      foreach ($all as $a) $by[mb_strtolower(trim($a->name))][] = $a;
      foreach ($map as $dupName => [$keepName, $final]) {
        $dups = $by[$dupName] ?? []; $keeps = $by[$keepName] ?? [];
        // bKash/Nagad: both spellings share one lower-case key, so the group holds both rows
        if ($dupName === $keepName) { $rows = $dups; if (count($rows) < 2) { if ($rows) DB::table('money_accounts')->where('id', $rows[0]->id)->update(['name' => $final]); continue; } $keep = array_shift($rows); $dups = $rows; }
        elseif (!$keeps && $dups) { DB::table('money_accounts')->where('id', $dups[0]->id)->update(['name' => $final]); continue; }
        elseif (!$dups) { continue; }
        else $keep = $keeps[0];
        foreach ($dups as $d) {
          if ($d->id == $keep->id) continue;
          foreach ($cols as $x) DB::table($x->t)->where($x->c, $d->id)->update([$x->c => $keep->id]);
          if (isset($d->opening) && (float)$d->opening != 0) DB::table('money_accounts')->where('id', $keep->id)->increment('opening', (float)$d->opening);
          DB::table('money_accounts')->where('id', $d->id)->delete();
        }
        DB::table('money_accounts')->where('id', $keep->id)->update(['name' => $final, 'updated_at' => now()]);
      }
    });
    // nagod/nagad spelled differently: fold them too
    $n = DB::table('money_accounts')->whereRaw("lower(name) in ('nagod personal','nagad personal')")->orderBy('id')->get();
    if ($n->count() > 1) DB::transaction(function () use ($n, $cols) {
      $keep = $n[0];
      foreach ($n->slice(1) as $d) {
        foreach ($cols as $x) DB::table($x->t)->where($x->c, $d->id)->update([$x->c => $keep->id]);
        if (isset($d->opening) && (float)$d->opening != 0) DB::table('money_accounts')->where('id', $keep->id)->increment('opening', (float)$d->opening);
        DB::table('money_accounts')->where('id', $d->id)->delete();
      }
      DB::table('money_accounts')->where('id', $keep->id)->update(['name' => 'Nagad Personal']);
    });
  }
  public function down(): void {}
};
