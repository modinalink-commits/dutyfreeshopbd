<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner 17 Nov 2026: during the one-month notice the WhatsApp order messages (approved / shipped / delivered) also name the new hotline and WhatsApp number, inside the brand value. down() removes the line. */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    DB::table('settings')->updateOrInsert(['key'=>'contact_notice_wa'], ['value'=>'নতুন হটলাইন 09644999000, WhatsApp 01871600600', 'updated_at'=>now(), 'created_at'=>now()]);
  }
  public function down(): void { DB::table('settings')->where('key', 'contact_notice_wa')->delete(); }
};
