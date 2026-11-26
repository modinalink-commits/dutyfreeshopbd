<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
/** Messenger AI told customers the hotline was the WhatsApp number. Fix the owner-saved knowledge base copy (the built-in default is fixed in the file). */
return new class extends Migration {
  public function up(): void {
    $row = DB::table('integration_secrets')->where('key', 'ai_kb')->first();
    if (!$row || !$row->value) return;
    try { $t = Crypt::decryptString($row->value); } catch (\Throwable $e) { return; }
    DB::table('integration_secrets')->updateOrInsert(['key' => 'ai_kb_backup_contact'], ['value' => $row->value, 'updated_at' => now(), 'created_at' => now()]);
    $n = str_replace(['01872404040', '01872-404040', '09613828081'], ['01871600600', '01871-600600', '09644999000'], $t);
    $rule = "\n\nনম্বরের নিয়ম: হটলাইন 09644999000 শুধু ফোন কলের জন্য, এটিকে কখনো হোয়াটসঅ্যাপ নম্বর বলবেন না। হোয়াটসঅ্যাপ ও মোবাইল নম্বর 01871600600।";
    if (mb_strpos($n, 'নম্বরের নিয়ম: হটলাইন') === false) $n .= $rule;
    DB::table('integration_secrets')->where('key', 'ai_kb')->update(['value' => Crypt::encryptString($n), 'updated_at' => now()]);
  }
  public function down(): void {}
};
