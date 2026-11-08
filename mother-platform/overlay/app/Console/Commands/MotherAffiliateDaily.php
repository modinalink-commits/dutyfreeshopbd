<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Support\StoreScanner;
/** Owner (8 Nov 2026): the three source stores (Watch, Gadget & Electronics, Sunglass) are re-scanned once a day: new in-stock products come in, price / stock changes are synced. Started in the background by tick(). */
class MotherAffiliateDaily extends Command {
  protected $signature = 'mother:affiliate-daily {--jobs=}';
  protected $description = 'Daily scan + import of the Watch, Gadget and Sunglass source stores';
  /** Cheap check called from the 10-minute courier loop: starts the job in the background once per day after 03:00 Dhaka. */
  public static function tick(): void {
    try {
      $nowJobs = (string)(DB::table('settings')->where('key', 'affiliate_now')->value('value') ?? '');
      if ($nowJobs !== '') { DB::table('settings')->where('key', 'affiliate_now')->update(['value' => '', 'updated_at' => now()]); @exec('cd ' . escapeshellarg(base_path()) . ' && nohup php artisan mother:affiliate-daily --jobs=' . escapeshellarg($nowJobs) . ' > /dev/null 2>&1 &'); return; }
      $now = now('Asia/Dhaka'); if ($now->hour < 3) return; $today = $now->format('Y-m-d');
      if ((string)(DB::table('settings')->where('key', 'affiliate_daily_date')->value('value') ?? '') === $today) return;
      DB::table('settings')->updateOrInsert(['key' => 'affiliate_daily_date'], ['value' => $today, 'updated_at' => now(), 'created_at' => now()]);
      $cmd = 'cd ' . escapeshellarg(base_path()) . ' && nohup php artisan mother:affiliate-daily > /dev/null 2>&1 &';
      @exec($cmd);
    } catch (\Throwable $e) { report($e); }
  }
  public function handle() {
    @set_time_limit(0); ignore_user_abort(true);
    $lock = Cache::lock('affiliate-daily', 14400); if (!$lock->get()) return 0;
    try {
      $jobs = []; foreach (array_filter(explode(',', (string)$this->option('jobs'))) as $j) { [$jc, $js] = array_pad(explode(':', $j), 2, '1'); $jobs[$jc] = $js !== '0'; }
      $gd = strtolower((string)(DB::table('settings')->where('key', 'gadget_source_domain')->value('value') ?? 'startech.com.bd'));
      foreach (['watch' => 'timeaccess-store.com', 'gadget' => $gd, 'sunglass' => 'luxotix.com'] as $c => $dom) {
        if ($dom === '') continue;
        if ($jobs && !array_key_exists($c, $jobs)) continue; $doScan = $jobs ? $jobs[$c] : true;
        $s = DB::table('stores')->whereRaw('lower(domain) = ? or lower(domain) = ?', [$dom, 'www.' . $dom])->first(); if (!$s) continue;
        try {
          if ($doScan) { StoreScanner::begin($s); $t0 = time();
          while (time() - $t0 < 5400) { $r = StoreScanner::step((int)$s->id); if (($r->scan_status ?? '') !== 'running') break; } }
          request()->merge(['c' => $c]); $ctl = app(\App\Http\Controllers\WatchImportController::class); $n = 0;
          while ($n++ < 4000) { $j = $ctl->step(request())->getData(true); if (!empty($j['finished'])) break; }
          $this->info("$c done");
        } catch (\Throwable $e) { report($e); }
      }
    } finally { $lock->release(); }
    return 0;
  }
}
