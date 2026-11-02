<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    if (DB::getDriverName() !== 'pgsql' || DB::connection()->pretending()) return;
    // The first run measured the start from an old imported INV-9999xx number. Continue the real series (INV-<order id>) instead.
    $max = (int)DB::selectOne("select coalesce(max(id),0) m from orders")->m;
    $cur = (int)DB::selectOne("select last_value v from invoice_seq")->v;
    if ($cur > $max + 5000) {
      DB::statement("select setval('invoice_seq', ?)", [$max]);
      foreach (DB::table('incomplete_orders')->whereIn('status', ['open', 'recovered'])->where('invoice', '>=', 'INV-900000')->orderBy('created_at')->orderBy('id')->pluck('id') as $id)
        DB::table('incomplete_orders')->where('id', $id)->update(['invoice' => \App\Support\InvoiceNumber::next()]);
    }
  }
  public function down(): void {}
};
