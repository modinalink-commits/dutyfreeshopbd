<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Invoice numbers: 6 digits (100000-999999) from one global sequence. The sequence can never pass 999999 (no silent 7 digits); incomplete checkouts get the same uniqueness as orders. */
return new class extends Migration {
  public function up(): void {
    if (DB::getDriverName() !== 'pgsql' || DB::connection()->pretending()) return;
    $cur = (int)DB::selectOne("select last_value v from invoice_seq")->v;
    if ($cur < 99999) DB::statement("select setval('invoice_seq', 99999)");
    if ($cur < 999999) DB::statement("ALTER SEQUENCE invoice_seq MAXVALUE 999999 NO CYCLE");
    $d = (int)DB::selectOne("select count(*) n from (select invoice from incomplete_orders where invoice is not null group by invoice having count(*)>1) x")->n;
    if ($d === 0) DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS incomplete_orders_invoice_uq ON incomplete_orders (invoice) WHERE invoice IS NOT NULL");
  }
  public function down(): void {}
};
