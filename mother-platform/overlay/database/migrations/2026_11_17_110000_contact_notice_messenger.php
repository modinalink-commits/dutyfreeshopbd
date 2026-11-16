<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner 17 Nov 2026: Messenger first-message notice with the new hotline and WhatsApp number, for one month (until 17 Dec 2026). down() switches it off. */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    $text = "আসসালামু আলাইকুম 🌿\nDutyFreeBD-তে আপনাকে স্বাগতম।\n\nআমাদের যোগাযোগের নম্বর আপডেট হয়েছে:\n📞 নতুন হটলাইন: 09644999000\n💬 নতুন WhatsApp: 01871600600\n\nআপনার প্রয়োজনটি এখানে লিখে রাখুন, আমাদের প্রতিনিধি দ্রুত উত্তর দেবেন।\n🛒 অর্ডার করুন: www.dutyfreebd.com";
    foreach (['contact_notice_text'=>$text, 'contact_notice_sms'=>'New Hotline 09644999000 WhatsApp 01871600600', 'contact_notice_until'=>'2026-12-17', 'contact_notice_on'=>'1'] as $k => $v)
      DB::table('settings')->updateOrInsert(['key'=>$k], ['value'=>$v, 'updated_at'=>now(), 'created_at'=>now()]);
  }
  public function down(): void { DB::table('settings')->where('key', 'contact_notice_on')->update(['value'=>'0', 'updated_at'=>now()]); }
};
