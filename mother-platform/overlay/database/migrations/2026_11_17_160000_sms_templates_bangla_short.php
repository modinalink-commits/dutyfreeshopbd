<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner 17 Nov 2026: all SMS templates short and in Bangla. Stored texts are changed ONLY when they still hold the
 * old default (a text the owner or staff rewrote is left alone). Every old value is kept in settings.sms_tpl_backup_20261117.
 * Sent messages and logs are not touched.
 */
return new class extends Migration {
  const KEY = 'sms_tpl_backup_20261117';
  private function settings(): array {
    return [
      'wb_sms_text'  => ['আমরা আপনাকে মিস করছি! আপনার পরবর্তী অর্ডারে বিশেষ {pct}% ডিসকাউন্ট উপভোগ করুন।{code} অর্ডার করুন: {link}', 'DutyFreeBD পরের অর্ডারে {pct}% ছাড়!{code} অর্ডার: {link}'],
      'wb_sms_final' => ['আপনার জন্য আমাদের বিশেষ Welcome Back Offer — পরবর্তী অর্ডারে {pct}% ডিসকাউন্ট। আবার আমাদের সাথে অর্ডার করুন।{code} {link}', 'DutyFreeBD শেষ সুযোগ: পরের অর্ডারে {pct}% ছাড়!{code} {link}'],
      'cc_noanswer_sms' => ['আসসালামু আলাইকুম। DutyFreeBD থেকে আপনাকে কল দিয়েছিলাম, কিন্তু পাইনি। আপনার সাথে কথা বলা দরকার। সুবিধামতো {hotline} নম্বরে কল দিন।', 'DutyFreeBD থেকে কল দিয়েছিলাম, পাইনি। কল দিন: {hotline}'],
      'pubsms_text' => ["{product} আবার স্টকে এসেছে। এখনই অর্ডার করুন: {link}\nDutyFreeBD", 'DutyFreeBD {product} আবার স্টকে এসেছে। অর্ডার: {link}'],
      'tpl_purchase' => ['প্রিয় {supplier_name}, Purchase Invoice {invoice_no} তৈরি হয়েছে। Total: ৳{total}. Paid: ৳{paid}. Due: ৳{due}. Invoice: {invoice_link}', 'প্রিয় {supplier_name}, ক্রয় ইনভয়েস {invoice_no} তৈরি হয়েছে। মোট ৳{total}, পরিশোধ ৳{paid}, বকেয়া ৳{due}। {invoice_link}'],
      'tpl_payment' => ['প্রিয় {supplier_name}, আজ ৳{amount} পেমেন্ট করা হয়েছে। আপনার বর্তমান বকেয়া ৳{due}। Invoice/Statement: {invoice_link}', 'প্রিয় {supplier_name}, আজ ৳{amount} পরিশোধ করা হয়েছে। বকেয়া ৳{due}। {invoice_link}'],
      'tpl_return' => ['DutyFreeBD: {invoice_no} এর পণ্য ফেরত (৳{amount}) হিসাবে সমন্বয় করা হয়েছে। বর্তমান বকেয়া ৳{due}। Statement: {statement_link}', 'DutyFreeBD {invoice_no}-এর ফেরত ৳{amount} সমন্বয় হয়েছে। বকেয়া ৳{due}। {statement_link}'],
      'tpl_damage' => ['DutyFreeBD: ড্যামেজ ক্লেইম ৳{amount} আপনার হিসাবে সমন্বয় করা হয়েছে। বর্তমান বকেয়া ৳{due}। Statement: {statement_link}', 'DutyFreeBD ড্যামেজ ক্লেইম ৳{amount} সমন্বয় হয়েছে। বকেয়া ৳{due}। {statement_link}'],
      'tpl_statement' => ['DutyFreeBD: আপনার Statement তৈরি হয়েছে। বর্তমান পাওনা ৳{due}। দেখুন: {statement_link}', 'DutyFreeBD আপনার হিসাব বিবরণী তৈরি। পাওনা ৳{due}। দেখুন: {statement_link}'],
    ];
  }
  private function autos(): array {
    return [
      'welcome' => ['প্রিয় {customer_name}, DutyFreeBD-তে স্বাগতম! অরিজিনাল পণ্যে বিশেষ অফার পেতে আমাদের সাথেই থাকুন।', 'DutyFreeBD-তে স্বাগতম, {customer_name}! অরিজিনাল পণ্যের অফার পেতে সাথে থাকুন।'],
      'inactive' => ['প্রিয় {customer_name}, অনেকদিন আপনাকে দেখছি না! আপনার জন্য বিশেষ অফার অপেক্ষা করছে। dutyfreeshopbd.com', 'DutyFreeBD {customer_name}, আপনার জন্য বিশেষ অফার আছে। dutyfreebd.com'],
      'order_confirmed' => ['প্রিয় {customer_name}, আপনার অর্ডার {order_id} কনফার্ম হয়েছে। মোট: Tk {total}। DutyFreeBD', 'DutyFreeBD আপনার অর্ডার {order_id} নিশ্চিত হয়েছে। মোট ৳{total}।'],
      'order_processing' => ['প্রিয় {customer_name}, আপনার অর্ডার {order_id} প্যাক করা হচ্ছে।', 'DutyFreeBD আপনার অর্ডার {order_id} প্যাক করা হচ্ছে।'],
      'order_shipped' => ['প্রিয় {customer_name}, আপনার অর্ডার {order_id} কুরিয়ারে দেওয়া হয়েছে। Tracking: {tracking_id}', 'DutyFreeBD আপনার অর্ডার {order_id} কুরিয়ারে দেওয়া হয়েছে। Tracking: {tracking_id}'],
      'order_delivered' => ['প্রিয় {customer_name}, আপনার অর্ডার {order_id} ডেলিভারি সম্পন্ন হয়েছে। কেনাকাটার জন্য ধন্যবাদ! DutyFreeBD', 'DutyFreeBD আপনার অর্ডার {order_id} ডেলিভারি হয়েছে। ধন্যবাদ!'],
      'order_cancelled' => ['প্রিয় {customer_name}, আপনার অর্ডার {order_id} বাতিল করা হয়েছে। প্রয়োজনে আমাদের সাথে যোগাযোগ করুন।', 'DutyFreeBD আপনার অর্ডার {order_id} বাতিল হয়েছে। সহায়তা: 09644999000'],
      'order_returned' => ['প্রিয় {customer_name}, আপনার অর্ডার {order_id} রিটার্ন গ্রহণ করা হয়েছে।', 'DutyFreeBD আপনার অর্ডার {order_id}-এর রিটার্ন গ্রহণ করা হয়েছে।'],
      'payment_verified' => ['প্রিয় {customer_name}, অর্ডার {order_id}-এর পেমেন্ট যাচাই হয়েছে। ধন্যবাদ! DutyFreeBD', 'DutyFreeBD অর্ডার {order_id}-এর পেমেন্ট পেয়েছি। ধন্যবাদ।'],
      'incomplete_recovery' => ['প্রিয় {customer_name}, আপনার অর্ডারটি সম্পন্ন হয়নি। অর্ডারটি শেষ করতে এখানে ক্লিক করুন: {checkout_link}', 'DutyFreeBD আপনার অর্ডার সম্পন্ন হয়নি। শেষ করুন: {checkout_link}'],
    ];
  }
  private function library(): array {
    return [
      ['DutyFreeBD: আপনার জন্য {discount}% বিশেষ ছাড়। Coupon: {coupon_code}. অফারটি {expiry_date} পর্যন্ত প্রযোজ্য। dutyfreeshopbd.com', 'DutyFreeBD আপনার জন্য {discount}% ছাড়। কোড: {coupon_code}, {expiry_date} পর্যন্ত। dutyfreebd.com'],
      ['প্রিয় {customer_name}, DutyFreeBD-তে স্বাগতম! প্রথম অর্ডারে Coupon {coupon_code} ব্যবহার করুন।', 'DutyFreeBD-তে স্বাগতম, {customer_name}! প্রথম অর্ডারে কোড {coupon_code} ব্যবহার করুন।'],
      ['প্রিয় {customer_name}, অনেকদিন আপনাকে দেখছি না! আপনার জন্য রয়েছে বিশেষ ছাড়। dutyfreeshopbd.com', 'DutyFreeBD {customer_name}, আপনার জন্য বিশেষ ছাড় আছে। dutyfreebd.com'],
      ['প্রিয় {customer_name}, আপনার {membership} সদস্যপদের বিশেষ সুবিধা উপভোগ করুন। DutyFreeBD', 'DutyFreeBD {customer_name}, আপনার {membership} সদস্যপদের সুবিধা নিন।'],
    ];
  }
  private function same(?string $a, string $b): bool { $n = fn($s) => trim(preg_replace('/\s+/u', ' ', str_replace("\r", '', (string)$s))); return $n($a) === $n($b); }

  public function up(): void {
    $bak = ['settings'=>[], 'automations'=>[], 'library'=>[], 'voice'=>[]];
    foreach ($this->settings() as $k => [$old, $new]) {
      $cur = DB::table('settings')->where('key', $k)->value('value'); if ($cur === null) continue;
      $bak['settings'][$k] = $cur;
      if ($this->same($cur, $old)) DB::table('settings')->where('key', $k)->update(['value'=>$new, 'updated_at'=>now()]);
    }
    if (Schema::hasTable('sms_automations')) foreach ($this->autos() as $t => [$old, $new]) {
      $a = DB::table('sms_automations')->where('trigger', $t)->first(); if (!$a) continue;
      $bak['automations'][$t] = $a->body;
      if ($this->same($a->body, $old)) DB::table('sms_automations')->where('id', $a->id)->update(['body'=>$new, 'updated_at'=>now()]);
    }
    if (Schema::hasTable('sms_templates')) foreach (DB::table('sms_templates')->get() as $t) {
      $bak['library'][$t->id] = $t->body;
      foreach ($this->library() as [$old, $new]) if ($this->same($t->body, $old)) DB::table('sms_templates')->where('id', $t->id)->update(['body'=>$new, 'updated_at'=>now()]);
    }
    if (Schema::hasTable('voice_campaigns') && Schema::hasColumn('voice_campaigns', 'sms_text')) {
      $old = 'DutyFreeBD: আপনার সর্বশেষ অর্ডার করা {product} এর উপর {pct}% বিশেষ ডিসকাউন্ট রয়েছে। অফারটি {date} পর্যন্ত কার্যকর।';
      foreach (DB::table('voice_campaigns')->whereNotNull('sms_text')->get(['id', 'sms_text']) as $c) { $bak['voice'][$c->id] = $c->sms_text; if ($this->same($c->sms_text, $old)) DB::table('voice_campaigns')->where('id', $c->id)->update(['sms_text'=>null]); }
    }
    DB::table('settings')->updateOrInsert(['key'=>self::KEY], ['value'=>json_encode($bak, JSON_UNESCAPED_UNICODE), 'updated_at'=>now(), 'created_at'=>now()]);
    try { \Illuminate\Support\Facades\Cache::forget('store.settings2'); } catch (\Throwable $e) {}
  }
  public function down(): void {
    $bak = json_decode((string)DB::table('settings')->where('key', self::KEY)->value('value'), true); if (!is_array($bak)) return;
    foreach (($bak['settings'] ?? []) as $k => $v) DB::table('settings')->where('key', $k)->update(['value'=>$v, 'updated_at'=>now()]);
    if (Schema::hasTable('sms_automations')) foreach (($bak['automations'] ?? []) as $t => $v) DB::table('sms_automations')->where('trigger', $t)->update(['body'=>$v, 'updated_at'=>now()]);
    if (Schema::hasTable('sms_templates')) foreach (($bak['library'] ?? []) as $id => $v) DB::table('sms_templates')->where('id', (int)$id)->update(['body'=>$v, 'updated_at'=>now()]);
    if (Schema::hasTable('voice_campaigns')) foreach (($bak['voice'] ?? []) as $id => $v) DB::table('voice_campaigns')->where('id', (int)$id)->update(['sms_text'=>$v]);
    DB::table('settings')->where('key', self::KEY)->delete();
  }
};
