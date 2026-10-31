<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('payment_methods')) {
      Schema::create('payment_methods', function (Blueprint $t) {
        $t->id();
        $t->string('kind', 12);            // bank, bkash, nagad
        $t->string('title');               // bank name or wallet name
        $t->string('account_name')->nullable();
        $t->string('account_no')->nullable();
        $t->string('branch')->nullable();
        $t->string('routing')->nullable();
        $t->string('swift')->nullable();
        $t->string('logo')->nullable();
        $t->text('instruction')->nullable();
        $t->boolean('active')->default(true);
        $t->integer('position')->default(0);
        $t->timestamps();
      });
    }
    Schema::table('orders', function (Blueprint $t) {
      foreach (['pay_txn','pay_account','pay_proof','pay_status','pay_by'] as $c) if (!Schema::hasColumn('orders', $c)) $t->string($c)->nullable();
      if (!Schema::hasColumn('orders','pay_at')) $t->timestamp('pay_at')->nullable();
    });
    // First-time seed. The account data is read from a private server variable so it never sits in the public repository.
    if (DB::table('payment_methods')->count() === 0 && ($b = getenv('PAYMENT_SEED'))) {
      $rows = json_decode((string)base64_decode($b), true);
      if (is_array($rows)) foreach ($rows as $i => $r) DB::table('payment_methods')->insert(array_merge($r, ['active'=>true,'position'=>$i+1,'created_at'=>now(),'updated_at'=>now()]));
    }
  }
  public function down(): void { Schema::dropIfExists('payment_methods'); }
};
