<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
return new class extends Migration {
  public function up(): void {
    if (DB::getDriverName() !== 'pgsql' || DB::connection()->pretending()) return; // --pretend (deploy safety check) cannot read results
    if (!Schema::hasColumn('incomplete_orders', 'invoice')) Schema::table('incomplete_orders', fn(Blueprint $t) => $t->string('invoice', 30)->nullable()->index());
    // Audit (read-only): duplicates and non-standard series in existing orders.
    $dups = (int)DB::selectOne("select count(*) n from (select invoice from orders group by invoice having count(*)>1) x")->n;
    $nonstd = (int)DB::selectOne("select count(*) n from orders where invoice !~ '^INV-[0-9]{6}$'")->n;
    $empty = (int)DB::selectOne("select count(*) n from orders where invoice is null or invoice = ''")->n;
    DB::table('settings')->updateOrInsert(['key' => 'invoice_audit'], ['value' => json_encode(['at' => now()->toDateTimeString(), 'duplicates' => $dups, 'non_standard' => $nonstd, 'missing' => $empty]), 'updated_at' => now(), 'created_at' => now()]);
    // Sequence starts after the highest normal INV number / order id (old imported numbers with 7+ digits are ignored).
    $max = (int)DB::selectOne("select greatest(coalesce((select max(substring(invoice from 5)::bigint) from orders where invoice ~ '^INV-[0-9]{1,6}$'),0), coalesce((select max(id) from orders),0)) m")->m;
    DB::unprepared("DO \$\$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_class WHERE relname='invoice_seq') THEN CREATE SEQUENCE invoice_seq START ".($max + 1)."; END IF; END \$\$");
    // Existing open checkouts get their invoice from the same sequence, oldest first.
    foreach (DB::table('incomplete_orders')->whereNull('invoice')->whereIn('status', ['open', 'recovered'])->orderBy('created_at')->orderBy('id')->pluck('id') as $id)
      DB::table('incomplete_orders')->where('id', $id)->update(['invoice' => \App\Support\InvoiceNumber::next()]);
    // DB-level uniqueness, only when history is clean (history is never rewritten).
    if ($dups === 0) DB::unprepared("CREATE UNIQUE INDEX IF NOT EXISTS orders_invoice_unique ON orders (invoice)");
  }
  public function down(): void {}
};
