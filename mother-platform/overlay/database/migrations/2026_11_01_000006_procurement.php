<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('suppliers', function (Blueprint $t) {
      foreach (['code'=>60,'contact_person'=>160,'whatsapp'=>40,'email'=>160,'business_name'=>200,'token'=>40] as $c=>$l) if (!Schema::hasColumn('suppliers',$c)) $t->string($c,$l)->nullable();
      if (!Schema::hasColumn('suppliers','opening_balance')) $t->decimal('opening_balance',14,2)->default(0);
      if (!Schema::hasColumn('suppliers','credit_limit')) $t->decimal('credit_limit',14,2)->default(0);
      if (!Schema::hasColumn('suppliers','payment_terms')) $t->integer('payment_terms')->default(0);
      if (!Schema::hasColumn('suppliers','notes')) $t->text('notes')->nullable();
    });
    Schema::table('purchases', function (Blueprint $t) {
      foreach (['supplier_invoice'=>80,'ptype'=>30,'pay_type'=>30,'reference'=>120,'status'=>20,'token'=>40] as $c=>$l) if (!Schema::hasColumn('purchases',$c)) $t->string($c,$l)->nullable();
      if (!Schema::hasColumn('purchases','store_id')) $t->unsignedBigInteger('store_id')->nullable();
      foreach (['subtotal','item_discount','extra_discount','tax','transport','other_cost','prev_due'] as $c) if (!Schema::hasColumn('purchases',$c)) $t->decimal($c,14,2)->default(0);
      if (!Schema::hasColumn('purchases','due_date')) $t->date('due_date')->nullable();
      if (!Schema::hasColumn('purchases','stock_applied')) $t->boolean('stock_applied')->default(false);
      if (!Schema::hasColumn('purchases','approved_by')) $t->string('approved_by')->nullable();
    });
    Schema::table('purchase_items', function (Blueprint $t) {
      foreach (['sku'=>120,'barcode'=>120,'batch'=>80] as $c=>$l) if (!Schema::hasColumn('purchase_items',$c)) $t->string($c,$l)->nullable();
      foreach (['discount','tax','line_total'] as $c) if (!Schema::hasColumn('purchase_items',$c)) $t->decimal($c,14,2)->default(0);
      if (!Schema::hasColumn('purchase_items','expiry')) $t->date('expiry')->nullable();
      if (!Schema::hasColumn('purchase_items','damage_qty')) $t->integer('damage_qty')->default(0);
    });
    Schema::table('damaged_products', function (Blueprint $t) {
      if (!Schema::hasColumn('damaged_products','supplier_id')) $t->unsignedBigInteger('supplier_id')->nullable()->index();
      if (!Schema::hasColumn('damaged_products','purchase_id')) $t->unsignedBigInteger('purchase_id')->nullable();
      foreach (['batch'=>80,'reason_type'=>40,'responsibility'=>20,'photo'=>255,'code'=>30] as $c=>$l) if (!Schema::hasColumn('damaged_products',$c)) $t->string($c,$l)->nullable();
      if (!Schema::hasColumn('damaged_products','unit_cost')) $t->decimal('unit_cost',14,2)->default(0);
      if (!Schema::hasColumn('damaged_products','value')) $t->decimal('value',14,2)->default(0);
      if (!Schema::hasColumn('damaged_products','dmg_date')) $t->date('dmg_date')->nullable();
      if (!Schema::hasColumn('damaged_products','note')) $t->text('note')->nullable();
      if (!Schema::hasColumn('damaged_products','store_id')) $t->unsignedBigInteger('store_id')->nullable();
    });
    if (!Schema::hasColumn('products','damage_stock')) Schema::table('products', function (Blueprint $t) { $t->integer('damage_stock')->default(0); });
    if (!Schema::hasTable('supplier_payments')) Schema::create('supplier_payments', function (Blueprint $t) {
      $t->id(); $t->string('code',30)->nullable()->unique(); $t->unsignedBigInteger('supplier_id')->index(); $t->unsignedBigInteger('purchase_id')->nullable()->index();
      $t->date('pay_date'); $t->decimal('amount',14,2); $t->string('method',30)->default('Cash'); $t->string('account',160)->nullable(); $t->string('txn_id',120)->nullable(); $t->string('reference',120)->nullable();
      $t->text('note')->nullable(); $t->string('proof',255)->nullable(); $t->string('status',20)->default('paid'); $t->unsignedBigInteger('user_id')->nullable(); $t->string('user_name')->nullable(); $t->string('approved_by')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('payment_allocations')) Schema::create('payment_allocations', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('payment_id')->index(); $t->unsignedBigInteger('purchase_id')->index(); $t->decimal('amount',14,2); $t->timestamps();
    });
    if (!Schema::hasTable('purchase_returns')) Schema::create('purchase_returns', function (Blueprint $t) {
      $t->id(); $t->string('code',30)->nullable()->unique(); $t->unsignedBigInteger('supplier_id')->index(); $t->unsignedBigInteger('purchase_id')->nullable()->index(); $t->unsignedBigInteger('product_id')->nullable();
      $t->string('name'); $t->integer('qty'); $t->decimal('rate',14,2)->default(0); $t->decimal('value',14,2)->default(0); $t->string('reason',160)->nullable(); $t->date('return_date'); $t->text('note')->nullable();
      $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('supplier_notes')) Schema::create('supplier_notes', function (Blueprint $t) {
      $t->id(); $t->string('code',30)->nullable()->unique(); $t->string('kind',10); $t->unsignedBigInteger('supplier_id')->index(); $t->unsignedBigInteger('purchase_id')->nullable();
      $t->string('reason',60)->nullable(); $t->decimal('amount',14,2); $t->date('note_date'); $t->text('note')->nullable(); $t->unsignedBigInteger('user_id')->nullable(); $t->timestamps();
    });
    // existing data: suppliers get codes + public tokens; old purchases are treated as received, with their old "paid" amount turned into a payment so the ledger balances
    foreach (DB::table('suppliers')->orderBy('id')->get() as $s) DB::table('suppliers')->where('id',$s->id)->update(['code'=>$s->code ?: 'SUP-'.str_pad($s->id,5,'0',STR_PAD_LEFT), 'token'=>$s->token ?: bin2hex(random_bytes(10))]);
    foreach (DB::table('purchases')->whereNull('status')->orderBy('id')->get() as $p) {
      DB::table('purchases')->where('id',$p->id)->update(['status'=>'received','stock_applied'=>true,'subtotal'=>$p->total,'token'=>bin2hex(random_bytes(10)),'ptype'=>'Local','pay_type'=>$p->paid >= $p->total && $p->total > 0 ? 'Cash' : 'Credit']);
      if ((float)$p->paid > 0) { $pid = DB::table('supplier_payments')->insertGetId(['supplier_id'=>$p->supplier_id,'purchase_id'=>$p->id,'pay_date'=>$p->purchase_date,'amount'=>$p->paid,'method'=>'Other','note'=>'Imported from old invoice','status'=>'paid','created_at'=>now(),'updated_at'=>now()]);
        DB::table('supplier_payments')->where('id',$pid)->update(['code'=>'PAY-'.str_pad($pid,6,'0',STR_PAD_LEFT)]);
        DB::table('payment_allocations')->insert(['payment_id'=>$pid,'purchase_id'=>$p->id,'amount'=>$p->paid,'created_at'=>now(),'updated_at'=>now()]); }
    }
    foreach (DB::table('damaged_products')->whereNull('dmg_date')->get() as $d) DB::table('damaged_products')->where('id',$d->id)->update(['dmg_date'=>substr((string)$d->created_at,0,10),'responsibility'=>'company','reason_type'=>'Other','note'=>$d->reason]);
    $defaults = ['proc_sms_purchase'=>'0','proc_sms_payment'=>'1','proc_sms_due'=>'0','proc_sms_return'=>'0','proc_sms_damage'=>'0','proc_sms_statement'=>'0','proc_due_offsets'=>'-3,0,3','proc_approval'=>'0','proc_approval_limit'=>'100000','proc_pay_approval_limit'=>'100000'];
    foreach ($defaults as $k=>$v) if (!DB::table('settings')->where('key',$k)->exists()) DB::table('settings')->insert(['key'=>$k,'value'=>$v,'created_at'=>now(),'updated_at'=>now()]);
  }
  public function down(): void {}
};
