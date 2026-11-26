<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** 26 Nov 2026: a big SMS campaign (28,000 customers) used to go out 30 messages per worker round (about 10 a minute, two days). Several of these loops now send campaign rows side by side. Each message is claimed with one UPDATE (Sms::deliver), so two loops can never send the same row. Site SMS (OTP, order updates) are not touched here and keep going through the normal path first. */
class SmsBlast extends Command
{
  protected $signature = 'sms:blast {slot=1}';
  protected $description = 'Fast sender for queued SMS campaign rows';
  public function handle(): int {
    $slot = (int)$this->argument('slot');
    $lock = Cache::lock('sms_blast_' . $slot, 1300); if (!$lock->get()) return 0;
    try {
      $end = time() + 1200; $idle = 0;
      while (time() < $end) {
        Cache::put('sms_blast_hb_' . $slot, time(), 300);
        if (!\App\Support\Sms::configured()) break;
        $rows = DB::table('sms_logs as l')->join('sms_campaigns as c', 'c.id', '=', 'l.campaign_id')->where('l.status', 'queued')->where('c.status', 'sending')
          ->orderBy('l.id')->offset(0)->limit(10)->select('l.*')->get();
        if ($rows->isEmpty()) { if (++$idle > 6) break; sleep(5); continue; } $idle = 0;
        foreach ($rows as $r) { try { \App\Support\Sms::deliver($r); } catch (\Throwable $e) { report($e); } }
      }
    } finally { Cache::forget('sms_blast_hb_' . $slot); $lock->release(); }
    return 0;
  }
}
