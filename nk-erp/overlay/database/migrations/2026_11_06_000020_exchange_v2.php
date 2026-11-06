<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Brand master: exchange rules. Cross-brand exchange is off until an admin records the agreement.
        Schema::table('brands', function (Blueprint $t) {
            $t->boolean('exchange_allowed')->default(false);
            $t->boolean('exchange_partner')->default(false);
            $t->string('ownership_policy', 20)->default('swap'); // swap | brand_owned | deposit
            $t->string('agreement_ref', 80)->nullable();
            $t->string('deposit_rule', 255)->nullable();
            $t->string('settlement_rule', 255)->nullable();
            $t->string('fee_rule', 255)->nullable();
        });

        Schema::table('cylinder_exchanges', function (Blueprint $t) {
            $t->string('type', 5)->nullable();           // A..E, or MX for mixed lines
            $t->date('exchange_date')->nullable();
            $t->decimal('deposit_adj', 12, 2)->default(0); // + deposit taken, - deposit returned; never revenue
            $t->string('attachment', 255)->nullable();
            $t->string('photo', 255)->nullable();
            $t->string('ack_name', 120)->nullable();      // customer acknowledgment (app)
            $t->string('hold_reason', 255)->nullable();
            $t->string('client_tx', 40)->nullable()->unique();
            $t->foreignId('reversed_by')->nullable()->constrained('users');
            $t->timestamp('reversed_at')->nullable();
            $t->string('reverse_reason', 255)->nullable();
        });
        DB::statement('alter table cylinder_exchanges alter column size_kg drop not null, alter column qty drop not null, alter column given_brand_id drop not null, alter column taken_brand_id drop not null, alter column unit_price drop not null');

        // One exchange can hold several brands and sizes.
        Schema::create('cylinder_exchange_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('exchange_id')->constrained('cylinder_exchanges')->cascadeOnDelete();
            $t->string('type', 2);                         // A..E
            $t->foreignId('in_brand_id')->constrained('brands');
            $t->unsignedSmallInteger('in_size');
            $t->unsignedInteger('in_qty');
            $t->string('in_condition', 10)->default('good'); // good | valve | damaged | unknown
            $t->string('in_ownership', 10)->default('customer'); // customer | company | brand | unknown
            $t->foreignId('out_brand_id')->constrained('brands');
            $t->unsignedSmallInteger('out_size');
            $t->unsignedInteger('out_qty');
            $t->string('out_kind', 6)->default('full');   // full | empty (type E)
            $t->string('out_condition', 10)->default('good');
            $t->decimal('refill_price', 12, 2)->default(0);
            $t->decimal('fee', 12, 2)->default(0);
            $t->decimal('line_total', 12, 2)->default(0);
            $t->timestamps();
        });

        // Cylinders of other brands that pass through our hands, to settle with that brand.
        Schema::create('brand_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('brand_id')->constrained('brands');
            $t->unsignedSmallInteger('size_kg');
            $t->string('kind', 12);                        // exchange | settlement | reversal
            $t->integer('qty_received')->default(0);      // their cylinders that came to us
            $t->integer('qty_delivered')->default(0);     // their cylinders we handed out or returned
            $t->decimal('amount', 12, 2)->default(0);     // + we owe the brand, - the brand owes us
            $t->string('status', 10)->default('open');    // open | settled
            $t->string('reference', 40)->nullable();
            $t->foreignId('exchange_id')->nullable()->constrained('cylinder_exchanges');
            $t->string('agreement_ref', 80)->nullable();
            $t->string('document', 255)->nullable();
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->index(['brand_id', 'size_kg']);
        });

        // Cylinder deposits are a liability to the customer, kept apart from sales.
        Schema::create('cylinder_deposits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->nullable()->constrained('customers');
            $t->string('walkin_name', 120)->nullable();
            $t->foreignId('exchange_id')->nullable()->constrained('cylinder_exchanges');
            $t->decimal('amount', 12, 2);                  // + taken, - returned
            $t->string('method', 10)->default('cash');
            $t->string('reference', 40)->nullable();
            $t->string('note', 255)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
        });

        // Earlier single-line exchanges become one line each.
        foreach (DB::table('cylinder_exchanges')->whereNotNull('given_brand_id')->get() as $x) {
            $own = DB::table('brands')->where('for_sale', true)->value('id');
            $type = $x->given_brand_id == $own ? ($x->taken_brand_id == $own ? 'A' : 'C') : ($x->taken_brand_id == $own ? 'B' : 'D');
            DB::table('cylinder_exchanges')->where('id', $x->id)->update(['type' => $type, 'exchange_date' => substr((string) $x->created_at, 0, 10)]);
            DB::table('cylinder_exchange_lines')->insert(['exchange_id' => $x->id, 'type' => $type, 'in_brand_id' => $x->given_brand_id, 'in_size' => $x->size_kg, 'in_qty' => $x->qty,
                'out_brand_id' => $x->taken_brand_id, 'out_size' => $x->size_kg, 'out_qty' => $x->qty, 'refill_price' => $x->unit_price, 'fee' => $x->charge_unit,
                'line_total' => round($x->qty * ($x->unit_price + $x->charge_unit), 2), 'created_at' => $x->created_at, 'updated_at' => $x->updated_at]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cylinder_deposits');
        Schema::dropIfExists('brand_settlements');
        Schema::dropIfExists('cylinder_exchange_lines');
        Schema::table('cylinder_exchanges', fn (Blueprint $t) => $t->dropColumn(['type', 'exchange_date', 'deposit_adj', 'attachment', 'photo', 'ack_name', 'hold_reason', 'client_tx', 'reversed_by', 'reversed_at', 'reverse_reason']));
        Schema::table('brands', fn (Blueprint $t) => $t->dropColumn(['exchange_allowed', 'exchange_partner', 'ownership_policy', 'agreement_ref', 'deposit_rule', 'settlement_rule', 'fee_rule']));
    }
};
