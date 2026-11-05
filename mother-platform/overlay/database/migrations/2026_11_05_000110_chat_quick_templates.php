<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('chat_quick_replies')) return;
    foreach ([
      ['Order Confirmation', 'আপনার অর্ডারটি কনফার্ম করা হয়েছে। অল্প সময়ের মধ্যেই কুরিয়ারে হস্তান্তর করা হবে। ধন্যবাদ!'],
      ['Courier Update', 'আপনার অর্ডারটি কুরিয়ারে হস্তান্তর করা হয়েছে। ট্র্যাকিং আপডেট পেলেই আপনাকে জানানো হবে।'],
      ['Payment Request', 'অর্ডার কনফার্ম করতে অনুগ্রহ করে পেমেন্ট সম্পন্ন করে Transaction ID / স্ক্রিনশট পাঠান।'],
      ['Delivery Delay', 'দুঃখিত, কুরিয়ার সমস্যার কারণে ডেলিভারিতে একটু দেরি হচ্ছে। আমরা বিষয়টি দেখছি, শীঘ্রই আপডেট জানাব।'],
      ['Out of Stock', 'দুঃখিত, পণ্যটি এই মুহূর্তে স্টকে নেই। নতুন স্টক এলে আপনাকে সবার আগে জানানো হবে।'],
      ['Sourcing Update', 'আপনার চাওয়া পণ্যটি আমরা সংগ্রহের চেষ্টা করছি। নিশ্চিত হওয়ার সাথে সাথেই জানাব।'],
      ['Thank You', 'ধন্যবাদ! DutyFree Shop BD-এর সাথে থাকার জন্য কৃতজ্ঞ। আর কিছু লাগলে জানাবেন।'],
    ] as [$t, $b]) if (!DB::table('chat_quick_replies')->where('title', $t)->exists()) DB::table('chat_quick_replies')->insert(['title' => $t, 'body' => $b, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
  }
  public function down(): void {}
};
