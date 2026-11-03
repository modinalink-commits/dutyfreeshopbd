<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Public "how to delete your data" page (required by Facebook Login). Created once; editable later in Website > Pages. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('pages') || DB::table('pages')->where('slug', 'data-deletion')->exists()) return;
    $body = <<<'H'
<p>আপনি চাইলে DutyFree Shop BD-তে থাকা আপনার অ্যাকাউন্ট ও ব্যক্তিগত তথ্য মুছে ফেলার অনুরোধ করতে পারেন। (You can ask us to delete your account and personal data at any time.)</p>
<h2>How to request deletion / কীভাবে অনুরোধ করবেন</h2>
<ul>
<li>Call or WhatsApp: [[whatsapp]] (or [[call]])</li>
<li>Email: [[email]]</li>
</ul>
<p>অনুরোধে আপনার নাম, অ্যাকাউন্টের ইমেইল বা মোবাইল নম্বর এবং "ডেটা মুছে দিন" কথাটি জানান। আপনার পরিচয় নিশ্চিত করার জন্য আমরা অ্যাকাউন্টের তথ্য মিলিয়ে দেখতে পারি।</p>
<h2>What we delete / কী মুছে ফেলা হবে</h2>
<p>অ্যাকাউন্ট, প্রোফাইল, সংরক্ষিত ঠিকানা, উইশলিস্ট, Google বা Facebook লগইন সংযোগ এবং আমাদের কাছে থাকা অন্যান্য ব্যক্তিগত তথ্য মুছে ফেলা হবে। Facebook বা Google দিয়ে লগইন করে থাকলে আমরা ওই প্ল্যাটফর্ম থেকে কেবল নাম, ইমেইল ও প্রোফাইল ছবি পেয়েছি।</p>
<h2>What we may keep / কী রাখতে হতে পারে</h2>
<p>আইন, কর ও হিসাবের প্রয়োজনে অর্ডার ও ইনভয়েসের রেকর্ড আমাদের রাখতে হতে পারে। প্রয়োজন শেষ হলে সেগুলোও সুরক্ষিতভাবে মুছে ফেলা বা ব্যক্তিপরিচয় সরিয়ে রাখা হয়।</p>
<h2>Timeline / সময়</h2>
<p>অনুরোধ পাওয়ার পর যত দ্রুত সম্ভব, সাধারণত ৭ কর্মদিবসের মধ্যে আমরা আপনাকে নিশ্চিত করব।</p>
<p>Facebook দিয়ে লগইন করা কাস্টমাররা Facebook-এর Settings &rarr; Apps and Websites থেকেও এই সাইটের অ্যাক্সেস সরিয়ে দিতে পারেন।</p>
H;
    $now = now();
    DB::table('pages')->insert(['slug'=>'data-deletion','title'=>'Data Deletion Instructions','body'=>$body,'meta_title'=>'Data Deletion Instructions | DutyFree Shop BD','meta_description'=>'How to request deletion of your DutyFree Shop BD account and personal data.','active'=>true,'created_at'=>$now,'updated_at'=>$now]);
  }
  public function down(): void {}
};
