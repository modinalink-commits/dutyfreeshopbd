<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
/** Official contact details become the single source of truth (Site settings > Business contact). */
return new class extends Migration {
  public function up(): void {
    if (DB::connection()->pretending()) return;
    $v = ['call_number' => '09613828081', 'contact_mobile' => '01871600600', 'whatsapp_number' => '01872404040', 'contact_email' => 'info@dutyfreebd.com', 'contact_address' => 'Al Sabka, Deira, Dubai, United Arab Emirates', 'business_hours' => '10:00 AM – 10:00 PM'];
    foreach ($v as $k => $val) {
      if (DB::table('settings')->where('key', $k)->exists()) DB::table('settings')->where('key', $k)->update(['value' => $val, 'updated_at' => now()]);
      else DB::table('settings')->insert(['key' => $k, 'value' => $val, 'created_at' => now(), 'updated_at' => now()]);
    }
    // duplicate editable copies are retired
    DB::table('settings')->whereIn('key', ['pp_call', 'pay_wa'])->delete();
    foreach (['store.settings2', 'ai.facts', 'store.home6', 'store.home4', 'store.home5'] as $c) { try { Cache::forget($c); } catch (\Throwable $e) {} }
  }
  public function down(): void {}
};
