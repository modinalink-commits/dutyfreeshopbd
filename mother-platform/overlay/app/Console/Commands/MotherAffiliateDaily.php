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
      $set = fn($k, $d) => (string)(DB::table('settings')->where('key', $k)->value('value') ?? $d);
      if ($set('auto_scan_on', '1') !== '1') return;
      $now = now('Asia/Dhaka'); $tm = preg_match('/^(\d{1,2}):(\d{2})$/', $set('auto_scan_time', '03:00'), $mm) ? [(int)$mm[1], (int)$mm[2]] : [3, 0];
      if ($now->hour * 60 + $now->minute < $tm[0] * 60 + $tm[1]) return; $today = $now->format('Y-m-d');
      if ((string)(DB::table('settings')->where('key', 'affiliate_daily_date')->value('value') ?? '') === $today) return;
      DB::table('settings')->updateOrInsert(['key' => 'affiliate_daily_date'], ['value' => $today, 'updated_at' => now(), 'created_at' => now()]);
      $cmd = 'cd ' . escapeshellarg(base_path()) . ' && nohup php artisan mother:affiliate-daily > /dev/null 2>&1 &';
      DB::table('settings')->updateOrInsert(['key' => 'affiliate_pending'], ['value' => 'watch:1,gadget:1,sunglass:1,amour:1,beauty:1,all:1', 'updated_at' => now(), 'created_at' => now()]);
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
      if (!$jobs) $jobs = ['watch' => true, 'gadget' => true, 'sunglass' => true, 'amour' => true, 'beauty' => true];
      $gd = strtolower((string)(DB::table('settings')->where('key', 'gadget_source_domain')->value('value') ?? 'startech.com.bd'));
      foreach (['watch' => 'timeaccess-store.com', 'gadget' => $gd, 'sunglass' => 'luxotix.com', 'amour' => 'amour.com.bd', 'beauty' => 'sundora.com.bd'] as $c => $dom) {
        if ($dom === '') continue;
        if ($jobs && !array_key_exists($c, $jobs)) continue; $doScan = $jobs ? $jobs[$c] : true; $setPend($jobs);
        $s = DB::table('stores')->whereRaw('lower(domain) = ? or lower(domain) = ?', [$dom, 'www.' . $dom])->orderByRaw('(select count(*) from store_products sp where sp.store_id = stores.id) desc')->orderBy('id')->first(); if (!$s) continue;
        try {
          if ($doScan) { StoreScanner::begin($s); $t0 = time();
          while (time() - $t0 < 5400) { Cache::put('affiliate-alive', 1, 150); $r = StoreScanner::step((int)$s->id); if (($r->scan_status ?? '') !== 'running') break; } }
          request()->merge(['c' => $c]); $ctl = app(\App\Http\Controllers\WatchImportController::class); $n = 0;
          $jobs[$c] = false; $setPend($jobs);
          while ($n++ < 4000) { Cache::put('affiliate-alive', 1, 150); $j = $ctl->step(request())->getData(true); if (!empty($j['finished'])) break; }
          if ($c === 'amour') { $m = 0; while ($m++ < 4000) { Cache::put('affiliate-alive', 1, 150); $j = $ctl->clean(request())->getData(true); if (!empty($j['finished']) || !empty($j['error'])) break; if (empty($j['ok']) && empty($j['busy'])) break; if (!empty($j['busy'])) sleep(5); } }
          // real product descriptions and extra photos from the source page (a few products per call, resumes after a restart)
          $d = 0; $bad = 0; while ($d++ < 3000) { Cache::put('affiliate-alive', 1, 150); $j = $ctl->details(request())->getData(true); if (!empty($j['finished'])) break; if (!empty($j['busy'])) { sleep(2); continue; } if (empty($j['ok'])) { if (++$bad > 40) break; } else $bad = 0; usleep(400000); }
          unset($jobs[$c]); $setPend($jobs);
          $this->info("$c done");
        } catch (\Throwable $e) { report($e); }
      }
      // every other source store registered in Stores & Scan: re-scan so its stock/price stays equal to the source (central inventory)
      $own = ['timeaccess-store.com', $gd, 'luxotix.com', 'amour.com.bd', 'sundora.com.bd'];
      foreach (DB::table('stores')->whereNotNull('last_scan_at')->orderBy('id')->get() as $o) {
        $d = strtolower(preg_replace('#^www\.#', '', (string)$o->domain)); if ($d === '' || in_array($d, $own, true) || str_contains($d, 'dutyfreebd')) continue;
        if ($this->option('jobs') !== null && $this->option('jobs') !== '' && !str_contains((string)$this->option('jobs'), 'all')) continue;
        try { StoreScanner::begin($o); $t0 = time(); while (time() - $t0 < 3000) { Cache::put('affiliate-alive', 1, 150); $r = StoreScanner::step((int)$o->id); if (($r->scan_status ?? '') !== 'running') break; } } catch (\Throwable $e) { report($e); }
      }
      unset($jobs['all']); $setPend($jobs);
      DB::table('settings')->updateOrInsert(['key' => 'auto_scan_last'], ['value' => now('Asia/Dhaka')->format('Y-m-d H:i'), 'updated_at' => now(), 'created_at' => now()]);
    } finally { Cache::forget('affiliate-alive'); }
    return 0;
  }
}
