<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sms_templates')) Schema::create('sms_templates', function (Blueprint $t) {
      $t->id(); $t->string('name', 120); $t->string('category', 40)->default('Custom'); $t->text('body'); $t->boolean('active')->default(true); $t->timestamps();
    });
    if (!Schema::hasTable('sms_campaigns')) Schema::create('sms_campaigns', function (Blueprint $t) {
      $t->id(); $t->string('name', 160); $t->string('subject', 160)->nullable(); $t->string('kind', 20)->default('bulk');
      $t->json('segments')->nullable(); $t->json('filters')->nullable(); $t->json('customer_ids')->nullable(); $t->json('vars')->nullable();
      $t->unsignedBigInteger('template_id')->nullable(); $t->text('body'); $t->string('sender_id', 40)->nullable();
      $t->string('status', 20)->default('draft')->index(); $t->timestamp('schedule_at')->nullable()->index(); $t->string('repeat', 12)->default('none'); $t->unsignedInteger('run')->default(1);
      $t->boolean('allow_dup')->default(false); $t->unsignedInteger('total')->default(0); $t->decimal('cost', 12, 2)->default(0);
      $t->string('created_by', 80)->nullable(); $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
    });
    Schema::table('sms_logs', function (Blueprint $t) {
      if (!Schema::hasColumn('sms_logs', 'campaign_id')) $t->unsignedBigInteger('campaign_id')->nullable()->index();
      if (!Schema::hasColumn('sms_logs', 'run')) $t->unsignedInteger('run')->default(1);
      if (!Schema::hasColumn('sms_logs', 'customer_id')) $t->unsignedBigInteger('customer_id')->nullable()->index();
      if (!Schema::hasColumn('sms_logs', 'customer_name')) $t->string('customer_name', 120)->nullable();
      if (!Schema::hasColumn('sms_logs', 'type')) $t->string('type', 16)->default('transactional')->index();
      if (!Schema::hasColumn('sms_logs', 'sender_id')) $t->string('sender_id', 40)->nullable();
      if (!Schema::hasColumn('sms_logs', 'parts')) $t->unsignedSmallInteger('parts')->default(1);
      if (!Schema::hasColumn('sms_logs', 'ref')) $t->string('ref', 80)->nullable()->index();
      if (!Schema::hasColumn('sms_logs', 'cost')) $t->decimal('cost', 8, 2)->default(0);
    });
    if (!Schema::hasTable('sms_optouts')) Schema::create('sms_optouts', function (Blueprint $t) {
      $t->id(); $t->string('phone', 20)->unique(); $t->string('reason', 160)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('sms_automations')) Schema::create('sms_automations', function (Blueprint $t) {
      $t->id(); $t->string('trigger', 40)->unique(); $t->string('grp', 20); $t->string('name', 120); $t->text('body'); $t->unsignedInteger('param')->nullable(); $t->boolean('active')->default(false); $t->timestamps();
    });
    $now = now();
    if (!DB::table('sms_automations')->exists()) {
      $A = [
        ['welcome', 'Customer', 'New customer registration', 'প্রিয় {customer_name}, DutyFreeBD-তে স্বাগতম! অরিজিনাল পণ্যে বিশেষ অফার পেতে আমাদের সাথেই থাকুন।', null],
        ['inactive', 'Customer', 'Customer inactive for X days', 'প্রিয় {customer_name}, অনেকদিন আপনাকে দেখছি না! আপনার জন্য বিশেষ অফার অপেক্ষা করছে। dutyfreeshopbd.com', 90],
        ['order_confirmed', 'Order', 'Order confirmed', 'প্রিয় {customer_name}, আপনার অর্ডার {order_id} কনফার্ম হয়েছে। মোট: Tk {total}। DutyFreeBD', null],
        ['order_processing', 'Order', 'Order processing / packaging', 'প্রিয় {customer_name}, আপনার অর্ডার {order_id} প্যাক করা হচ্ছে।', null],
        ['order_shipped', 'Delivery', 'Shipment / courier assigned', 'প্রিয় {customer_name}, আপনার অর্ডার {order_id} কুরিয়ারে দেওয়া হয়েছে। Tracking: {tracking_id}', null],
        ['order_delivered', 'Delivery', 'Delivered', 'প্রিয় {customer_name}, আপনার অর্ডার {order_id} ডেলিভারি সম্পন্ন হয়েছে। কেনাকাটার জন্য ধন্যবাদ! DutyFreeBD', null],
        ['order_cancelled', 'Order', 'Cancelled', 'প্রিয় {customer_name}, আপনার অর্ডার {order_id} বাতিল করা হয়েছে। প্রয়োজনে আমাদের সাথে যোগাযোগ করুন।', null],
        ['order_returned', 'Order', 'Returned', 'প্রিয় {customer_name}, আপনার অর্ডার {order_id} রিটার্ন গ্রহণ করা হয়েছে।', null],
        ['payment_verified', 'Payment', 'Payment verified (bKash / Nagad / Bank)', 'প্রিয় {customer_name}, অর্ডার {order_id}-এর পেমেন্ট যাচাই হয়েছে। ধন্যবাদ! DutyFreeBD', null],
        ['incomplete_recovery', 'Recovery', 'Incomplete order recovery', 'প্রিয় {customer_name}, আপনার অর্ডারটি সম্পন্ন হয়নি। অর্ডারটি শেষ করতে এখানে ক্লিক করুন: {checkout_link}', 60],
      ];
      foreach ($A as [$k, $g, $n, $b, $p]) DB::table('sms_automations')->insert(['trigger'=>$k,'grp'=>$g,'name'=>$n,'body'=>$b,'param'=>$p,'active'=>false,'created_at'=>$now,'updated_at'=>$now]);
    }
    if (!DB::table('sms_templates')->exists()) {
      $T = [
        ['Offer — discount with coupon', 'Offer', 'DutyFreeBD: আপনার জন্য {discount}% বিশেষ ছাড়। Coupon: {coupon_code}. অফারটি {expiry_date} পর্যন্ত প্রযোজ্য। dutyfreeshopbd.com'],
        ['Welcome offer', 'Promotional', 'প্রিয় {customer_name}, DutyFreeBD-তে স্বাগতম! প্রথম অর্ডারে Coupon {coupon_code} ব্যবহার করুন।'],
        ['We miss you', 'Follow-up', 'প্রিয় {customer_name}, অনেকদিন আপনাকে দেখছি না! আপনার জন্য রয়েছে বিশেষ ছাড়। dutyfreeshopbd.com'],
        ['Membership', 'Membership', 'প্রিয় {customer_name}, আপনার {membership} সদস্যপদের বিশেষ সুবিধা উপভোগ করুন। DutyFreeBD'],
      ];
      foreach ($T as [$n, $c, $b]) DB::table('sms_templates')->insert(['name'=>$n,'category'=>$c,'body'=>$b,'active'=>true,'created_at'=>$now,'updated_at'=>$now]);
    }
    foreach (['sms_unit_cost' => '0.50', 'sms_vip_spend' => '50000', 'sms_high_spend' => '20000', 'sms_low_balance' => '100'] as $k => $v)
      if (!DB::table('settings')->where('key', $k)->exists()) DB::table('settings')->insert(['key'=>$k,'value'=>$v,'created_at'=>$now,'updated_at'=>$now]);
  }
  public function down(): void {}
};
