<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner 21 Nov 2026: SMS campaigns stop; every automatic customer activity runs 10:00-22:00 Dhaka time (from 21 Nov morning 10:00).
 *  Old values are kept in settings key `automation_hours_backup_1121` (JSON) so down() can restore them. */
return new class extends Migration {
  const SET = ['wb_on' => '0', 'wb_quiet_from' => '22', 'wb_quiet_to' => '10', 'bscamp_remind_on' => '0', 'vc_ocv_start' => '10:00', 'vc_ocv_end' => '22:00'];
  public function up(): void {
    $bk = ['settings' => [], 'sms' => [], 'voice' => []];
    foreach (array_keys(self::SET) as $k) $bk['settings'][$k] = DB::table('settings')->where('key', $k)->value('value');
    if (Schema::hasTable('sms_campaigns')) { $bk['sms'] = DB::table('sms_campaigns')->whereIn('status', ['scheduled', 'sending'])->pluck('status', 'id')->all(); DB::table('sms_campaigns')->whereIn('status', ['scheduled', 'sending'])->update(['status' => 'paused', 'updated_at' => now()]); }
    if (Schema::hasTable('voice_campaigns')) { $bk['voice'] = DB::table('voice_campaigns')->get(['id', 'call_start_time', 'call_end_time'])->map(fn($r) => (array)$r)->all(); DB::table('voice_campaigns')->update(['call_start_time' => '10:00', 'call_end_time' => '22:00', 'updated_at' => now()]); }
    foreach (self::SET as $k => $v) DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]);
    DB::table('settings')->updateOrInsert(['key' => 'automation_hours_backup_1121'], ['value' => json_encode($bk), 'updated_at' => now(), 'created_at' => now()]);
  }
  public function down(): void {
    $bk = json_decode((string)DB::table('settings')->where('key', 'automation_hours_backup_1121')->value('value'), true); if (!$bk) return;
    foreach ($bk['settings'] ?? [] as $k => $v) { if ($v === null) DB::table('settings')->where('key', $k)->delete(); else DB::table('settings')->where('key', $k)->update(['value' => $v]); }
    foreach ($bk['sms'] ?? [] as $id => $st) DB::table('sms_campaigns')->where('id', $id)->where('status', 'paused')->update(['status' => $st]);
    foreach ($bk['voice'] ?? [] as $r) DB::table('voice_campaigns')->where('id', $r['id'])->update(['call_start_time' => $r['call_start_time'], 'call_end_time' => $r['call_end_time']]);
  }
};
