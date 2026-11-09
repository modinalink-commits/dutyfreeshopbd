<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Owner: one account per wallet. "Bkash Parsonal" / "bKash Personal" and "Nagad personal" / "Nagod Parsonal" (typos) are merged into bKash Personal and Nagad Personal. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('money_accounts')) return;
    $cols = DB::select("select table_name t, column_name c from information_schema.columns where table_schema = current_schema() and table_name <> 'money_accounts' and column_name in ('account_id','money_account_id','from_account','to_account','from_account_id','to_account_id','paid_from_account_id','payment_account_id') and data_type in ('integer','bigint','smallint')");
    DB::transaction(function () use ($cols) {
      foreach (['bkash' => 'bKash Personal', 'nagad' => 'Nagad Personal'] as $key => $final) {
        $rows = DB::table('money_accounts')->get()->filter(function ($a) use ($key) {
          $n = str_replace(['nagod', 'parsonal'], ['nagad', 'personal'], mb_strtolower(trim($a->name)));
          return $n === $key.' personal';
        })->sortBy('id')->values();
        if (!$rows->count()) continue;
        $keep = $rows[0];
        foreach ($rows->slice(1) as $d) {
          foreach ($cols as $x) DB::table($x->t)->where($x->c, $d->id)->update([$x->c => $keep->id]);
          if (isset($d->opening) && (float)$d->opening != 0) DB::table('money_accounts')->where('id', $keep->id)->increment('opening', (float)$d->opening);
          DB::table('money_accounts')->where('id', $d->id)->delete();
        }
        DB::table('money_accounts')->where('id', $keep->id)->update(['name' => $final, 'updated_at' => now()]);
      }
    });
  }
  public function down(): void {}
};
