<?php
namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Live Dashboard (owner, 23 Nov 2026): one wall screen that shows every background activity of the platform, what is running,
 * what is slow and what has stopped. Read-only. Every number comes from the database, the worker heartbeat or the cache;
 * nothing is invented: a signal that does not exist is reported as "na" (no signal) instead of a guess.
 */
class LiveBoard {
  /** Called from the worker loop when one of its tasks throws: the board shows that task in red with the first line of the error. */
  public static function fail(string $name, \Throwable $e): void {
    try { Cache::put('lb.fail.' . $name, ['t' => time(), 'm' => mb_substr($e->getMessage(), 0, 160)], 86400); } catch (\Throwable $x) {}
    try { AutoHeal::failed($name, mb_substr($e->getMessage(), 0, 160)); } catch (\Throwable $x) {}
  }

  /** Storefront page view: counts unique visitors per 5-minute window and per day (cache only, no table). Bots are skipped. */
  public static function visit(Request $r): void {
    try {
      if (!$r->isMethod('GET') || $r->ajax() || str_starts_with($r->path(), 'admin')) return;
      $ua = (string)$r->userAgent(); if ($ua === '' || preg_match('/bot|crawl|spider|facebookexternalhit|meta-external|preview|monitor|curl|python|wget|headless|lighthouse/i', $ua)) return;
      $ip = null; try { $ip = Capi::clientIp($r); } catch (\Throwable $e) {} $ip = $ip ?: (string)$r->ip();
      $b = intdiv(time(), 300); $id = md5($ip . '|' . $ua); $d = now('Asia/Dhaka')->format('Ymd');
      if (Cache::add('lb.v.' . $b . '.' . $id, 1, 600)) { if (!Cache::add('lb.vc.' . $b, 1, 1200)) Cache::increment('lb.vc.' . $b); }
      if (Cache::add('lb.vd.' . $d . '.' . $id, 1, 90000)) { if (!Cache::add('lb.vdc.' . $d, 1, 180000)) Cache::increment('lb.vdc.' . $d); }
    } catch (\Throwable $e) {}
  }

  private static function ago($v): ?int { try { return $v ? max(0, (int)now()->diffInSeconds(Carbon::parse($v), true)) : null; } catch (\Throwable $e) { return null; } }
  private static function dayStart(): Carbon { return now('Asia/Dhaka')->startOfDay()->setTimezone(config('app.timezone', 'UTC')); }

  /** Last activity (seconds ago) and today's count of one table. Null values when the table or column is not there. */
  private static function flow(string $table, string $col = 'created_at', ?array $where = null): array {
    try {
      $q = DB::table($table)->where($col, '>=', now()->subDays(3)); if ($where) $q->where($where); // 3-day window keeps this one light query per signal
      $r = $q->selectRaw("max($col) m, count(*) filter (where $col >= ?) n", [self::dayStart()])->first();
      return ['last' => $r && $r->m ? self::ago($r->m) : 259200, 'today' => (int)($r->n ?? 0)];
    } catch (\Throwable $e) { return ['last' => null, 'today' => null]; }
  }

  /** [key, group, icon, name, worker task names, flow (table, column, where), admin link] */
  private static function nodes(): array {
    return [
      ['calls', 'a', '📞', 'কল লগ', [], ['call_logs', 'started_at'], '/admin/call-center'],
      ['ordercall', 'a', '☎️', 'অর্ডার কনফার্ম কল', ['ordercall'], ['order_call_verifications'], '/admin/orders'],
      ['voice', 'a', '🎙️', 'ভয়েস ক্যাম্পেইন', ['voice'], ['voice_call_attempts', 'started_at'], '/admin/voice'],
      ['wacalls', 'a', '📲', 'WhatsApp কল', ['wacalls'], ['wa_calls'], '/admin/call-center'],
      ['warec', 'a', '⏺️', 'কল রেকর্ডিং', ['warec'], ['wa_call_recordings'], '/admin/call-center'],
      ['pbx', 'a', '🏢', 'PBX লাইন', ['pbxline', 'relaywatch'], null, '/admin/call-center'],
      ['calldaily', 'a', '🗒️', 'দৈনিক কল রিপোর্ট', ['call-daily'], null, '/admin/call-center'],

      ['capi', 'b', '🎯', 'Pixel / CAPI', [], null, '/admin/pixel'],
      ['arrivals', 'b', '🧭', 'অ্যাড থেকে ভিজিটর', [], null, '/admin/ad-arrivals'],
      ['winback', 'b', '🔁', 'Win-Back', ['winback'], ['winback_events'], '/admin/winback'],
      ['referral', 'b', '🤝', 'রেফারেল', ['referral'], null, '/admin/referral'],
      ['push', 'b', '🔔', 'পুশ নোটিফিকেশন', ['campaigns'], ['push_campaigns'], '/admin/push'],
      ['bscamp', 'b', '🏆', 'বেস্ট সেলার ক্যাম্পেইন', ['bscamp'], null, '/admin/m/campaigns'],
      ['appreward', 'b', '🎁', 'অ্যাপ রিওয়ার্ড', ['appreward'], null, '/admin/app-rewards'],
      ['seo', 'b', '🔎', 'SEO অটো', ['seo', 'seoauto'], null, '/admin/seo'],
      ['autoblog', 'b', '✒️', 'দৈনিক ব্লগ পোস্ট', ['autoblog'], ['posts', 'published_at'], '/admin/m/posts'],

      ['wa', 'c', '💬', 'WhatsApp', ['wa'], ['chat_threads', 'last_at', ['channel' => 'whatsapp']], '/admin/chat'],
      ['fb', 'c', '📘', 'Facebook', [], ['chat_threads', 'last_at', ['channel' => 'facebook']], '/admin/chat'],
      ['ig', 'c', '📸', 'Instagram', [], ['chat_threads', 'last_at', ['channel' => 'instagram']], '/admin/chat'],
      ['web', 'c', '🖥️', 'ওয়েবসাইট চ্যাট', [], ['chat_threads', 'last_at', ['channel' => 'web']], '/admin/chat'],
      ['reply', 'c', '✍️', 'পাঠানো উত্তর', [], ['chat_messages', 'created_at', ['dir' => 'out']], '/admin/chat'],
      ['fbcomments', 'c', '↩️', 'কমেন্ট রিপ্লাই', ['fbcomments'], ['chat_comments'], '/admin/chat'],
      ['aichat', 'c', '🤖', 'AI চ্যাট এজেন্ট', ['ainudge'], ['ai_activity_logs'], '/admin/chat'],
      ['chatcare', 'c', '🧹', 'চ্যাট দেখভাল', ['chat', 'chatclose', 'chatbacklog', 'chatowner', 'chatstale', 'chatsilent'], null, '/admin/chat'],

      ['orders', 'd', '🛒', 'নতুন অর্ডার', [], ['orders'], '/admin/orders'],
      ['aiapprove', 'd', '✅', 'AI অ্যাপ্রুভ', ['aiverify'], ['order_approval_history', 'created_at', ['source' => 'AI']], '/admin/orders'],
      ['manapprove', 'd', '🧑‍💼', 'ম্যানুয়াল অ্যাপ্রুভ', [], ['order_approval_history', 'created_at', ['source' => 'MANUAL']], '/admin/orders'],
      ['sched', 'd', '⏰', 'শিডিউল অর্ডার', ['sched'], null, '/admin/orders'],
      ['ordernotify', 'd', '📣', 'অর্ডার নোটিফাই', ['orders', 'cancel-notice'], null, '/admin/orders'],
      ['orderwatch', 'd', '👁️', 'অর্ডার ওয়াচ', ['order-watch'], ['order_watch_reports'], '/admin/order-watch'],
      ['incomplete', 'd', '🧺', 'অসম্পূর্ণ অর্ডার', ['incnudge'], ['incomplete_orders'], '/admin/orders'],
      ['fraud', 'd', '🛡️', 'ফ্রড চেক', [], ['fraud_checks'], '/admin/fraud'],
      ['scan', 'd', '🏷️', 'প্যাকেজিং স্ক্যান', [], ['order_scan_logs'], '/admin/orders'],

      ['csync', 'e', '🔄', 'কুরিয়ার সিঙ্ক', [], null, '/admin/courier-dashboard'],
      ['pathao', 'e', '🚚', 'Pathao', [], ['courier_events', 'happened_at', ['courier' => 'Pathao']], '/admin/courier'],
      ['steadfast', 'e', '📦', 'Steadfast', [], ['courier_events', 'happened_at', ['courier' => 'Steadfast']], '/admin/steadfast'],
      ['redx', 'e', '🟥', 'RedX', [], ['courier_events', 'happened_at', ['courier' => 'RedX']], '/admin/redx'],
      ['carrybee', 'e', '🐝', 'Carrybee', [], ['courier_events', 'happened_at', ['courier' => 'Carrybee']], '/admin/carrybee'],

      ['sms', 'f', '📱', 'SMS পাঠানো', ['sms-report'], ['sms_logs'], '/admin/sms'],
      ['smscamp', 'f', '📨', 'SMS ক্যাম্পেইন', ['sms', 'smsauto'], ['sms_campaigns'], '/admin/sms-marketing'],
      ['email', 'f', '✉️', 'ইমেইল', ['email'], ['mail_outbox', 'sent_at'], '/admin/email'],
      ['waupdate', 'f', '🟢', 'WhatsApp অর্ডার আপডেট', ['wa-update'], ['order_whatsapp_events'], '/admin/orders'],
      ['pubsms', 'f', '🛎️', 'পাবলিশ নোটিস SMS', ['pubsms'], null, '/admin/back-in-store-sms'],
      ['supsms', 'f', '🏭', 'সাপ্লায়ার SMS', [], ['supplier_sms_log'], '/admin/purchase'],

      ['reorder', 'g', '🧾', 'রিঅর্ডার অ্যালার্ট', ['reorder'], ['reorder_requests'], '/admin/purchase'],
      ['restock', 'g', '📈', 'রিস্টক ফলো-আপ', ['restock'], ['restock_events'], '/admin/purchase'],
      ['purchaselist', 'g', '📝', 'পারচেজ লিস্ট', ['purchase-list', 'procure'], null, '/admin/purchase'],
      ['autoprice', 'g', '💲', 'অটো প্রাইস', ['autoprice'], null, '/admin/products'],
      ['bsstock', 'g', '⭐', 'বেস্ট সেলার স্টক', ['bs-stock'], null, '/admin/products'],
      ['aiimg', 'g', '🖼️', 'AI পণ্যের ছবি', ['aiimg'], ['ai_images'], '/admin/ai-images'],
      ['single', 'g', '🚬', 'সিঙ্গেল প্যাকেট', ['spfollow'], null, '/admin/products'],
      ['stock', 'g', '📉', 'স্টক মুভমেন্ট', [], ['stock_logs'], '/admin/inventory'],

      ['website', 'i', '🌐', 'ওয়েবসাইট', [], null, '/admin/deploy'],
      ['database', 'i', '🗄️', 'ডেটাবেস', [], null, '/admin/db-health'],
      ['queue', 'i', '⚙️', 'কিউ', [], null, '/admin/deploy'],
      ['scheduler', 'i', '💓', 'ওয়ার্কার', [], null, '/admin/deploy'],
      ['ssl', 'i', '🔒', 'SSL', [], null, '/admin/deploy'],
      ['deploy', 'i', '🚀', 'ডিপ্লয়', ['deploy'], ['deployments'], '/admin/deploy'],
      ['svc', 'i', '💳', 'সার্ভিস ব্যালেন্স', ['svcbal'], null, '/admin/services'],
      ['recurring', 'i', '🧮', 'রিকারিং খরচ', ['recurring'], null, '/admin/m/recurring-expenses'],
      ['aibrief', 'i', '🧠', 'AI ব্রিফ ও মেমরি', ['aibrief', 'aimem'], ['ai_briefs'], '/admin/ai-brief'],
      ['alerts', 'i', '🚨', 'অ্যালার্ট স্ক্যান', ['alerts', 'health'], null, '/admin/alerts/all'],
    ];
  }

  private static function staff(): array {
    return Cache::remember('lb.staff', 20, function () {
      try {
        $r = StaffLive::rows(); $out = [];
        foreach ($r['rows'] as $s) $out[] = ['name' => $s['name'], 'state' => $s['state'], 'page' => $s['page'], 'work_min' => $s['work_min'], 'orders' => $s['orders'], 'calls' => $s['calls'], 'chats' => $s['chats'], 'waiting' => $s['waiting']];
        return ['rows' => $out, 'unassigned' => (int)$r['unassigned']];
      } catch (\Throwable $e) { return ['rows' => [], 'unassigned' => 0]; }
    });
  }

  /**
   * Order pipeline. The counts are the SAME query the Orders page uses for its status tabs (all orders grouped by status, Incomplete = open
   * incomplete orders), so the two screens always agree. Approved is shown split by approval source and never merged (AI / MANUAL / other).
   */
  private static function pipeline(): array {
    $c = []; try { $c = DB::table('orders')->groupBy('status')->selectRaw('status, count(*) n')->pluck('n', 'status')->all(); } catch (\Throwable $e) {}
    $inc = null; try { $inc = (int)DB::table('incomplete_orders')->where('status', 'open')->count(); } catch (\Throwable $e) {}
    $ai = null; $man = null; $oth = 0;
    try { $a = DB::table('orders')->where('status', 'Approved')->groupBy('approval_source')->selectRaw("coalesce(approval_source, '') s, count(*) n")->pluck('n', 's')->all(); $ai = (int)($a['AI'] ?? 0); $man = (int)($a['MANUAL'] ?? 0); $oth = array_sum($a) - $ai - $man; } catch (\Throwable $e) {}
    $log = fn(string $to) => (function () use ($to) { try { return (int)DB::table('order_status_logs')->where('to_status', $to)->where('created_at', '>=', self::dayStart())->distinct()->count('order_id'); } catch (\Throwable $e) { return null; } })();
    $n = fn(string $st) => (int)($c[$st] ?? 0);
    $p = [
      ['k' => 'new', 'l' => 'নিউ অর্ডার', 'n' => $n('New'), 'c' => 'new'],
      ['k' => 'inc', 'l' => 'অসম্পূর্ণ', 'n' => $inc, 'c' => ''],
      ['k' => 'assign', 'l' => 'অ্যাসাইন', 'n' => $n('Assign'), 'c' => ''],
      ['k' => 'pending', 'l' => 'পেন্ডিং', 'n' => $n('Pending'), 'c' => ''],
      ['k' => 'sched', 'l' => 'শিডিউল', 'n' => $n('Scheduled'), 'c' => ''],
      ['k' => 'wfp', 'l' => 'WFP', 'n' => $n('WFP'), 'c' => ''],
      ['k' => 'ai', 'l' => 'AI অ্যাপ্রুভড', 'n' => $ai, 'c' => 'ai'],
      ['k' => 'manual', 'l' => 'ম্যানুয়াল অ্যাপ্রুভড', 'n' => $man, 'c' => ''],
    ];
    if ($oth > 0) $p[] = ['k' => 'oth', 'l' => 'অন্য অ্যাপ্রুভড', 'n' => $oth, 'c' => ''];
    if ($n('AI Order') > 0) $p[] = ['k' => 'aiorder', 'l' => 'AI অর্ডার', 'n' => $n('AI Order'), 'c' => 'ai'];
    return array_merge($p, [
      ['k' => 'pack', 'l' => 'প্যাকেজিং', 'n' => $n('Packaging'), 'c' => ''],
      ['k' => 'ship', 'l' => 'শিপমেন্ট', 'n' => $n('Shipment'), 'c' => ''],
      ['k' => 'done', 'l' => 'আজ ডেলিভার্ড', 'n' => $log('Delivered'), 'c' => 'done'],
      null,
      ['k' => 'late', 'l' => 'লেট ডেলিভারি', 'n' => $n('Late Delivery'), 'c' => 'warn'],
      ['k' => 'ret', 'l' => 'রিটার্ন পেন্ডিং', 'n' => $n('Return Pending'), 'c' => 'bad'],
      ['k' => 'cancel', 'l' => 'আজ ক্যান্সেল', 'n' => $log('Cancel'), 'c' => 'bad'],
    ]);
  }

  /**
   * Server vitals read from inside the app container: CPU load against the number of cores, memory in use, disk in use, database connections
   * and application errors written to the log in the last 15 minutes. Each one carries its own state so anything wrong shows on the board at once.
   */
  private static function sys(): array {
    $v = []; $lv = fn($x, $warn, $bad) => $x === null ? 'na' : ($x >= $bad ? 'dead' : ($x >= $warn ? 'slow' : 'ok'));
    // CPU measured from /proc/stat over a quarter second: what our programs really use, what waits on the disk, and what the hosting company's
    // other customers take away from us ("steal"). The load average alone mixes these three, so it is shown only as a detail.
    try {
      $rd = function () { $l = explode(' ', preg_replace('/\s+/', ' ', trim((string)strtok((string)@file_get_contents('/proc/stat'), "\n")))); array_shift($l); return array_map('floatval', $l); };
      $a = $rd(); usleep(250000); $b = $rd(); $d = []; foreach ($b as $i => $x) $d[$i] = $x - ($a[$i] ?? 0); $tot = array_sum(array_slice($d, 0, 8)) ?: 1;
      $busy = (int)round(($d[0] + $d[1] + $d[2] + ($d[5] ?? 0) + ($d[6] ?? 0)) / $tot * 100); $wait = (int)round(($d[4] ?? 0) / $tot * 100); $steal = (int)round(($d[7] ?? 0) / $tot * 100);
      $la = function_exists('sys_getloadavg') ? sys_getloadavg() : null; $cores = max(1, (int)preg_match_all('/^processor\s*:/m', (string)@file_get_contents('/proc/cpuinfo')));
      $ld = $la ? 'লোড ' . round($la[0], 1) . ' / ' . $cores . ' কোর' : '';
      $v[] = ['k' => 'cpu', 'l' => 'CPU', 'v' => $busy . '%', 's' => $lv($busy, 85, 95), 't' => 'আমাদের প্রোগ্রামের ব্যবহার · ' . $ld, 'fix' => 'সার্ভারে চাপ বেশি। Server Speed পেজ ও চলমান ক্যাম্পেইন দেখুন।'];
      $v[] = ['k' => 'iow', 'l' => 'ডিস্ক অপেক্ষা', 'v' => $wait . '%', 's' => $lv($wait, 35, 60), 't' => 'CPU ডিস্কের জন্য বসে আছে · ' . $ld, 'fix' => 'কোনো প্রোগ্রাম ডিস্ক থেকে অনেক বেশি পড়ছে (সম্ভবত অন্য সাইটের ডেটাবেস)।'];
      $v[] = ['k' => 'steal', 'l' => 'Steal', 'v' => $steal . '%', 's' => $lv($steal, 25, 45), 't' => 'Hostinger-এর একই মেশিনের অন্য গ্রাহকরা এই পরিমাণ CPU নিয়ে নিচ্ছে', 'fix' => 'এটা আমাদের সার্ভারের ভেতর থেকে ঠিক করা যায় না। Hostinger সাপোর্টকে জানাতে হবে।'];
    } catch (\Throwable $e) {}
    try { $m = (string)@file_get_contents('/proc/meminfo'); $tot = preg_match('/MemTotal:\s+(\d+)/', $m, $a) ? (int)$a[1] : 0; $av = preg_match('/MemAvailable:\s+(\d+)/', $m, $b) ? (int)$b[1] : 0;
      $r = $tot ? round(($tot - $av) / $tot * 100) : null; $v[] = ['k' => 'ram', 'l' => 'RAM', 'v' => $r === null ? null : $r . '%', 's' => $lv($r, 90, 97), 't' => $tot ? round(($tot - $av) / 1048576, 1) . ' / ' . round($tot / 1048576, 1) . ' GB' : '', 'fix' => 'মেমরি প্রায় শেষ। সার্ভারের RAM বাড়ানো বা ভারী কাজ কমানো দরকার।']; } catch (\Throwable $e) {}
    try { $f = @disk_free_space(storage_path()); $t = @disk_total_space(storage_path()); $r = ($f && $t) ? round(($t - $f) / $t * 100) : null;
      $v[] = ['k' => 'disk', 'l' => 'ডিস্ক', 'v' => $r === null ? null : $r . '%', 's' => $lv($r, 88, 95), 't' => $t ? round(($t - $f) / 1073741824, 1) . ' / ' . round($t / 1073741824, 1) . ' GB' : '', 'fix' => 'ডিস্ক ভরে আসছে। পুরোনো লগ, আপলোড বা পুরোনো রিলিজ মুছতে হবে।']; } catch (\Throwable $e) {}
    try { $c = DB::selectOne("select count(*) n, (select setting::int from pg_settings where name = 'max_connections') mx from pg_stat_activity"); $r = $c && $c->mx ? round($c->n / $c->mx * 100) : null;
      $v[] = ['k' => 'db', 'l' => 'DB সংযোগ', 'v' => $c ? (int)$c->n : null, 's' => $lv($r, 70, 90), 't' => $c ? 'সর্বোচ্চ ' . (int)$c->mx : '', 'fix' => 'ডেটাবেসে সংযোগ বেশি। DB Health পেজ দেখুন।']; } catch (\Throwable $e) {}
    try {
      $n = 0; $last = ''; $file = storage_path('logs/laravel.log');
      if (is_file($file)) { $sz = filesize($file); $fh = fopen($file, 'r'); if ($fh) { fseek($fh, max(0, $sz - 262144)); $buf = (string)fread($fh, 262144); fclose($fh);
        $cut = now()->subMinutes(15)->format('Y-m-d H:i:s');
        if (preg_match_all('/^\[(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d)\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.{0,140})/m', $buf, $mm, PREG_SET_ORDER)) foreach ($mm as $x) if ($x[1] >= $cut) { $n++; $last = $x[3]; } } }
      $v[] = ['k' => 'err', 'l' => 'ত্রুটি (১৫ মি.)', 'v' => $n, 's' => $lv($n, 5, 30), 't' => $last, 'fix' => 'অ্যাপে ত্রুটি হচ্ছে। Error Log পেজ খুলে প্রথম লাইন দেখুন।'];
    } catch (\Throwable $e) {}
    return $v;
  }

  /**
   * One-second pulse for the wall screen: only numbers that are cheap to read and can really change within a second.
   * CPU is the difference between this call and the previous one (no waiting); the three database counts are shared for 3 seconds.
   */
  public static function pulse(): array {
    $now = time(); $o = ['t' => $now, 'sys' => [], 'k' => []];
    $lv = fn($x, $warn, $bad) => $x >= $bad ? 'dead' : ($x >= $warn ? 'slow' : 'ok');
    try {
      $rd = function () { $l = explode(' ', preg_replace('/\s+/', ' ', trim((string)strtok((string)@file_get_contents('/proc/stat'), "\n")))); array_shift($l); return array_map('floatval', $l); };
      $b = $rd(); $mt = microtime(true); $pv = Cache::get('lb.pulse.stat'); Cache::put('lb.pulse.stat', ['t' => $mt, 'v' => $b], 30);
      if (is_array($pv) && $mt - (float)$pv['t'] >= 0.4 && $mt - (float)$pv['t'] <= 6) $a = $pv['v']; else { $a = $b; usleep(150000); $b = $rd(); }
      $d = []; foreach ($b as $i => $x) $d[$i] = $x - ($a[$i] ?? 0); $tot = array_sum(array_slice($d, 0, 8));
      if ($tot > 0) {
        $busy = (int)round(($d[0] + $d[1] + $d[2] + ($d[5] ?? 0) + ($d[6] ?? 0)) / $tot * 100); $wait = (int)round(($d[4] ?? 0) / $tot * 100); $steal = (int)round(($d[7] ?? 0) / $tot * 100);
        $o['sys'] = ['cpu' => [$busy . '%', $lv($busy, 85, 95)], 'iow' => [$wait . '%', $lv($wait, 35, 60)], 'steal' => [$steal . '%', $lv($steal, 25, 45)]];
      }
    } catch (\Throwable $e) {}
    try { $b = intdiv($now, 300); $fr = ($now % 300) / 300;
      $o['k']['এখন সাইটে ভিজিটর'] = ['v' => (int)round((int)Cache::get('lb.vc.' . $b, 0) + (int)Cache::get('lb.vc.' . ($b - 1), 0) * (1 - $fr)), 's' => 'আজ মোট ' . (int)Cache::get('lb.vdc.' . now('Asia/Dhaka')->format('Ymd'), 0) . ' জন'];
      $o['k']['Meta (Pixel / CAPI)'] = ['v' => (int)Cache::get('arr.d.' . now('Asia/Dhaka')->format('Y-m-d'), 0)];
    } catch (\Throwable $e) {}
    try {
      $db = Cache::remember('lb.pulse.db', 3, function () {
        $r = []; $ds = self::dayStart();
        try { $q = DB::table('orders')->where('created_at', '>=', $ds)->whereNotIn('status', ['Cancel', 'Incomplete'])->selectRaw('count(*) n, coalesce(sum(total),0) t')->first(); $r['আজকের বিক্রি'] = ['v' => (int)round($q->t), 's' => 'অর্ডার ' . (int)$q->n . 'টি (ক্যান্সেল বাদে)']; } catch (\Throwable $e) {}
        try { $w = (int)DB::table('chat_threads')->where('awaiting_reply', true)->where('status', '!=', 'resolved')->where('last_at', '>=', now()->subHours(22))->count(); $r['উত্তরের অপেক্ষায় চ্যাট'] = ['v' => $w, 'st' => $w > 15 ? 'slow' : 'ok']; } catch (\Throwable $e) {}
        return $r;
      });
      foreach ($db as $l => $x) $o['k'][$l] = $x;
    } catch (\Throwable $e) {}
    return $o;
  }

  /** Whole board, cached 10 seconds so an always-open screen costs almost nothing. */
  public static function data(): array {
    return Cache::remember('lb.data', 10, function () {
      try { AutoHeal::watchdog('web'); } catch (\Throwable $e) {} // an open board is one more watcher: stuck loops are un-stuck from here too
      $now = time(); $beat = null; try { $beat = DeployHealth::beatAge(); } catch (\Throwable $e) {}
      $cells = []; try { foreach (DeployHealth::light()['cells'] as $c) $cells[$c['key']] = $c; } catch (\Throwable $e) {}
      $out = [];
      foreach (self::nodes() as [$key, $g, $ic, $name, $ticks, $fl, $href]) {
        $n = ['k' => $key, 'g' => $g, 'i' => $ic, 'n' => $name, 'h' => $href, 's' => 'na', 't' => '', 'last' => null, 'today' => null, 'beat' => false];
        if ($fl) { $f = self::flow($fl[0], $fl[1] ?? 'created_at', $fl[2] ?? null); $n['last'] = $f['last']; $n['today'] = $f['today']; if ($f['last'] !== null || $f['today'] !== null) $n['s'] = 'ok'; }
        if ($ticks) {
          $n['beat'] = true;
          if ($beat === null) { $n['s'] = 'na'; $n['t'] = 'ওয়ার্কারের সংকেত নেই'; }
          elseif ($beat > 600) { $n['s'] = 'dead'; $n['t'] = 'ওয়ার্কার বন্ধ'; }
          elseif ($beat > 180) { $n['s'] = 'slow'; $n['t'] = 'ওয়ার্কার দেরি করছে'; }
          else {
            $n['s'] = 'ok';
            foreach ($ticks as $tk) if (AutoHeal::resting($tk)) { $n['s'] = 'slow'; $n['t'] = 'বারবার আটকে যাওয়ায় ৩০ মিনিটের বিরতিতে, এরপর নিজে থেকে আবার চেষ্টা হবে'; }
            foreach ($ticks as $tk) { $f = Cache::get('lb.fail.' . $tk); if (is_array($f)) { $age = $now - (int)$f['t']; if ($age <= 600) { $n['s'] = 'dead'; $n['t'] = 'ত্রুটি: ' . $f['m']; break; } if ($age <= 3600 && $n['s'] === 'ok') { $n['s'] = 'slow'; $n['t'] = 'কিছুক্ষণ আগে ত্রুটি: ' . $f['m']; } } }
          }
          if ($n['last'] === null) $n['last'] = $beat;
        }
        if (isset($cells[$key])) { $c = $cells[$key]; $n['s'] = ['ok' => 'ok', 'warn' => 'slow', 'fail' => 'dead', 'na' => 'na'][$c['state']] ?? 'na'; $n['t'] = $c['text'] . ($c['detail'] !== '' ? ' · ' . $c['detail'] : ''); if ($key === 'scheduler') $n['last'] = $beat; }
        $out[$key] = $n;
      }
      // Pixel / CAPI: last 30 server events kept by Capi::log
      try {
        $a = json_decode((string)DB::table('settings')->where('key', 'capi_log')->value('value'), true) ?: [];
        if ($a) { $bad = count(array_filter($a, fn($x) => empty($x['ok']))); $lt = null; try { $lt = Carbon::createFromFormat('d M H:i:s', (string)$a[0]['t'], 'Asia/Dhaka'); } catch (\Throwable $e) {}
          $out['capi']['last'] = $lt ? max(0, $now - $lt->getTimestamp()) : null; $out['capi']['s'] = empty($a[0]['ok']) ? 'dead' : ($bad > 3 ? 'slow' : 'ok'); $out['capi']['t'] = 'শেষ ' . count($a) . 'টি ইভেন্টে ব্যর্থ ' . $bad . 'টি'; }
      } catch (\Throwable $e) {}
      try { $d = now('Asia/Dhaka'); $out['arrivals']['today'] = (int)Cache::get('arr.d.' . $d->format('Y-m-d'), 0); $out['arrivals']['s'] = 'ok'; $out['arrivals']['t'] = 'এই ঘণ্টায় ' . (int)Cache::get('arr.h.' . $d->format('Y-m-d') . '-' . $d->format('H'), 0) . ' জন'; } catch (\Throwable $e) {}
      // order emails: the worker can be healthy while nothing is actually sent, so a full day without one sent email is a warning
      try { if ($out['email']['s'] === 'ok' && $out['email']['last'] !== null && $out['email']['last'] > 86400) { $out['email']['s'] = 'slow'; $out['email']['t'] = '২৪ ঘণ্টার বেশি কোনো ইমেইল পাঠানো হয়নি'; } } catch (\Throwable $e) {}
      try { $v = Cache::get('courier.last_sync'); $ag = self::ago($v); $out['csync']['last'] = $ag; if ($ag !== null) { $out['csync']['s'] = $ag > 7200 ? 'dead' : ($ag > 2400 ? 'slow' : 'ok'); $out['csync']['t'] = $ag > 2400 ? 'কুরিয়ার সিঙ্ক অনেকক্ষণ চলেনি' : ''; } } catch (\Throwable $e) {}
      try {
        $bad = []; foreach (DB::table('service_accounts')->get() as $r) { $st = ServiceBalances::state($r); if (in_array($st[0], ['low', 'expired', 'failed'], true)) $bad[] = ($r->name ?? 'Service') . ' (' . $st[1] . ')'; }
        if ($bad && $out['svc']['s'] === 'ok') { $out['svc']['s'] = 'slow'; $out['svc']['t'] = implode(', ', array_slice($bad, 0, 4)); }
      } catch (\Throwable $e) {}

      // headline cards
      $ds = self::dayStart(); $k = [];
      $web = $cells['website'] ?? null; $k[] = ['l' => 'ওয়েবসাইট স্পিড', 'v' => $web && $web['state'] === 'ok' ? (int)$web['detail'] : null, 'u' => 'ms সার্ভার সাড়া', 's' => $web ? ($web['state'] === 'ok' ? 'সাইট চালু' : 'সাইট সাড়া দিচ্ছে না') : '', 'st' => $web ? ($web['state'] === 'ok' ? ((int)$web['detail'] > 1500 ? 'slow' : 'ok') : 'dead') : 'na'];
      $b = intdiv($now, 300); $fr = ($now % 300) / 300; $vis = (int)round((int)Cache::get('lb.vc.' . $b, 0) + (int)Cache::get('lb.vc.' . ($b - 1), 0) * (1 - $fr));
      $k[] = ['l' => 'এখন সাইটে ভিজিটর', 'v' => $vis, 'u' => 'জন (শেষ ৫ মিনিট)', 's' => 'আজ মোট ' . (int)Cache::get('lb.vdc.' . now('Asia/Dhaka')->format('Ymd'), 0) . ' জন', 'st' => 'ok'];
      try { $o = DB::table('orders')->where('created_at', '>=', $ds)->whereNotIn('status', ['Cancel', 'Incomplete'])->selectRaw('count(*) n, coalesce(sum(total),0) t')->first(); $k[] = ['l' => 'আজকের বিক্রি', 'v' => (int)round($o->t), 'u' => 'টাকা', 's' => 'অর্ডার ' . (int)$o->n . 'টি (ক্যান্সেল বাদে)', 'st' => 'ok']; } catch (\Throwable $e) { $k[] = ['l' => 'আজকের বিক্রি', 'v' => null, 'u' => '', 's' => '', 'st' => 'na']; }
      try { $w = (int)DB::table('chat_threads')->where('awaiting_reply', true)->where('status', '!=', 'resolved')->where('last_at', '>=', now()->subHours(22))->count(); $k[] = ['l' => 'উত্তরের অপেক্ষায় চ্যাট', 'v' => $w, 'u' => 'টি', 's' => 'গত ২২ ঘণ্টার', 'st' => $w > 15 ? 'slow' : 'ok']; } catch (\Throwable $e) {}
      $c = $out['capi']; $k[] = ['l' => 'Meta (Pixel / CAPI)', 'v' => $out['arrivals']['today'], 'u' => 'জন অ্যাড থেকে এসেছে', 's' => $c['t'] ?: 'ইভেন্টের তথ্য নেই', 'st' => $c['s']];
      try { $dp = DB::table('deployments')->where('created_at', '>=', $ds)->count(); $ld = DB::table('deployments')->orderByDesc('id')->first(['status', 'created_at']); $k[] = ['l' => 'ডিপ্লয়মেন্ট', 'v' => (int)$dp, 'u' => 'টি আজ', 's' => $ld ? 'শেষটা: ' . $ld->status : '', 'st' => $ld && in_array($ld->status, ['Failed', 'Rolled Back'], true) ? 'slow' : 'ok']; } catch (\Throwable $e) {}
      try { $pw = DB::table('posts')->where('published_at', '>=', now()->subDays(7))->count(); $pl = self::ago(DB::table('posts')->max('published_at')); $k[] = ['l' => 'ব্লগ', 'v' => (int)$pw, 'u' => 'পোস্ট এ সপ্তাহে', 's' => $pl === null ? 'কোনো পোস্ট নেই' : 'শেষ পোস্ট ' . (int)floor($pl / 86400) . ' দিন আগে', 'st' => $pl !== null && $pl > 14 * 86400 ? 'slow' : 'ok']; } catch (\Throwable $e) {}
      $gseo = null; // filled after the per-minute history below (its graph is Google's own daily clicks)

      // one sample a minute per card (last 60 kept in the cache) so every card can draw its real trend line
      try {
        $hist = Cache::get('lb.hist', []); if (!is_array($hist)) $hist = []; $m = intdiv($now, 60);
        if (($hist['m'] ?? 0) !== $m) { $hist['m'] = $m; foreach ($k as $c) if ($c['v'] !== null) { $hist['d'][$c['l']][] = $c['v']; $hist['d'][$c['l']] = array_slice($hist['d'][$c['l']], -60); } Cache::put('lb.hist', $hist, 86400); }
        foreach ($k as $i => $c) $k[$i]['h'] = array_values($hist['d'][$c['l']] ?? []);
      } catch (\Throwable $e) {}
      try { $g = GoogleSeo::data();
        if ($g['state'] === 'ok') $k[] = ['l' => 'Google SEO (৭ দিন)', 'v' => (int)$g['clicks'], 'u' => 'ক্লিক', 's' => 'ইমপ্রেশন ' . number_format($g['impressions']) . ' · CTR ' . $g['ctr'] . '% · গড় অবস্থান ' . $g['position'], 'st' => 'ok', 'h' => $g['days'], 'fixed' => true, 'href' => '/admin/live-dashboard/google'];
        elseif ($g['state'] === 'none') $k[] = ['l' => 'Google SEO', 'v' => null, 'u' => '', 's' => 'চাবি বসানো হয়নি। এখানে ক্লিক করে সংযোগ দিন।', 'st' => 'na', 'h' => [], 'fixed' => true, 'href' => '/admin/live-dashboard/google'];
        else $k[] = ['l' => 'Google SEO', 'v' => null, 'u' => '', 's' => 'তথ্য আসছে না: ' . $g['msg'], 'st' => 'slow', 'h' => [], 'fixed' => true, 'href' => '/admin/live-dashboard/google'];
      } catch (\Throwable $e) {}
      $issues = []; try { foreach (array_slice(DeployHealth::active(), 0, 3) as $i) $issues[] = ['title' => $i['title'], 'severity' => $i['severity'], 'service' => $i['service'], 'action' => $i['action'], 'at' => $i['detected'] ?? '']; } catch (\Throwable $e) {}
      $disk = null; try { $f = @disk_free_space(storage_path()); $t = @disk_total_space(storage_path()); if ($f && $t) $disk = round($f / $t * 100, 1); } catch (\Throwable $e) {}
      return ['at' => $now, 'time' => now('Asia/Dhaka')->format('H:i:s'), 'beat' => $beat, 'nodes' => array_values($out), 'kpi' => $k, 'pipe' => self::pipeline(), 'staff' => self::staff(), 'issues' => $issues, 'heal' => AutoHeal::summary(), 'sys' => self::sys(), 'disk' => $disk];
    });
  }
}
