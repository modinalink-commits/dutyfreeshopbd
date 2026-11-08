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
      $pend = (string)(DB::table('settings')->where('key', 'affiliate_pending')->value('value') ?? '');
      if ($pend !== '' && !Cache::has('affiliate-alive')) { @exec('cd ' . escapeshellarg(base_path()) . ' && nohup php artisan mother:affiliate-daily --jobs=' . escapeshellarg($pend) . ' > /dev/null 2>&1 &'); return; }
      $now = now('Asia/Dhaka'); if ($now->hour < 3) return; $today = $now->format('Y-m-d');
      if ((string)(DB::table('settings')->where('key', 'affiliate_daily_date')->value('value') ?? '') === $today) return;
      DB::table('settings')->updateOrInsert(['key' => 'affiliate_daily_date'], ['value' => $today, 'updated_at' => now(), 'created_at' => now()]);
      $cmd = 'cd ' . escapeshellarg(base_path()) . ' && nohup php artisan mother:affiliate-daily > /dev/null 2>&1 &';
      DB::table('settings')->updateOrInsert(['key' => 'affiliate_pending'], ['value' => 'watch:1,gadget:1,sunglass:1,amour:1', 'updated_at' => now(), 'created_at' => now()]);
      @exec($cmd);
    } catch (\Throwable $e) { report($e); }
  }
  public function handle() {
    @set_time_limit(0); ignore_user_abort(true);
    if (Cache::has('affiliate-alive')) return 0; // another run is alive (a killed run lets the key expire, so a restart resumes)
    Cache::put('affiliate-alive', 1, 150);
    try {
      $setPend = function (array $j) { $v = []; foreach ($j as $k => $sc) $v[] = $k . ':' . ($sc ? '1' : '0'); DB::table('settings')->updateOrInsert(['key' => 'affiliate_pending'], ['value' => implode(',', $v), 'updated_at' => now(), 'created_at' => now()]); };
      $jobs = []; foreach (array_filter(explode(',', (string)$this->option('jobs'))) as $j) { [$jc, $js] = array_pad(explode(':', $j), 2, '1'); $jobs[$jc] = $js !== '0'; }
      if (!$jobs) $jobs = ['watch' => true, 'gadget' => true, 'sunglass' => true, 'amour' => true];
      $gd = strtolower((string)(DB::table('settings')->where('key', 'gadget_source_domain')->value('value') ?? 'startech.com.bd'));
      foreach (['watch' => 'timeaccess-store.com', 'gadget' => $gd, 'sunglass' => 'luxotix.com', 'amour' => 'amour.com.bd'] as $c => $dom) {
        if ($dom === '') continue;
        if ($jobs && !array_key_exists($c, $jobs)) continue; $doScan = $jobs ? $jobs[$c] : true; $setPend($jobs);
        $s = DB::table('stores')->whereRaw('lower(domain) = ? or lower(domain) = ?', [$dom, 'www.' . $dom])->first(); if (!$s) continue;
        try {
          if ($doScan) { StoreScanner::begin($s); $t0 = time();
          while (time() - $t0 < 5400) { Cache::put('affiliate-alive', 1, 150); $r = StoreScanner::step((int)$s->id); if (($r->scan_status ?? '') !== 'running') break; } }
          request()->merge(['c' => $c]); $ctl = app(\App\Http\Controllers\WatchImportController::class); $n = 0;
          $jobs[$c] = false; $setPend($jobs);
          while ($n++ < 4000) { Cache::put('affiliate-alive', 1, 150); $j = $ctl->step(request())->getData(true); if (!empty($j['finished'])) break; }
          if ($c === 'amour') { $m = 0; while ($m++ < 400) { Cache::put('affiliate-alive', 1, 150); $j = $ctl->clean(request())->getData(true); if (!empty($j['finished']) || !empty($j['error'])) break; if (empty($j['ok']) && empty($j['busy'])) break; } }
          unset($jobs[$c]); $setPend($jobs);
          $this->info("$c done");
        } catch (\Throwable $e) { report($e); }
      }
    } finally { Cache::forget('affiliate-alive'); }
    return 0;
  }
}
