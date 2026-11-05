<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Customers who use the installed App: who is inside it right now, install / last-seen history, device and push state. */
class AppUsers
{
  const LIVE_SEC = 150; // the App pings every 60 s while it is open

  private static ?bool $ok = null;
  public static function ready(): bool {
    if (self::$ok === null) { try { self::$ok = Schema::hasTable('app_installs') && Schema::hasColumn('app_installs', 'login_customer_id'); } catch (\Throwable $e) { self::$ok = false; } }
    return self::$ok;
  }

  public static function device(?string $ua): string {
    $ua = (string)$ua; if ($ua === '') return '—';
    $os = stripos($ua, 'iPhone') !== false ? 'iPhone' : (stripos($ua, 'iPad') !== false ? 'iPad' : (stripos($ua, 'Android') !== false ? 'Android' : (stripos($ua, 'Windows') !== false ? 'Windows' : (stripos($ua, 'Mac OS') !== false ? 'Mac' : 'Other'))));
    $br = stripos($ua, 'SamsungBrowser') !== false ? 'Samsung Internet' : (preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/CriOS|Chrome\//', $ua) ? 'Chrome' : (preg_match('/FxiOS|Firefox\//', $ua) ? 'Firefox' : (stripos($ua, 'Safari') !== false ? 'Safari' : 'Browser'))));
    return $os . ' · ' . $br;
  }

  public static function ago($t): string { return $t ? \Carbon\Carbon::parse($t)->diffForHumans() : '—'; }

  /** People currently inside the App (logged in, pinged in the last few minutes) plus anonymous app sessions. */
  public static function live(): array {
    if (!self::ready()) return ['people' => [], 'guests' => 0, 'installs' => 0];
    $since = now()->subSeconds(self::LIVE_SEC);
    $rows = DB::table('app_installs as i')->join('customers as c', 'c.id', '=', 'i.login_customer_id')->where('i.last_seen_at', '>=', $since)
      ->orderByDesc('i.last_seen_at')->limit(200)->get(['c.id', 'c.name', 'c.phone', 'i.last_seen_at', 'i.ua']);
    $people = []; foreach ($rows as $r) { if (isset($people[$r->id])) continue; $people[$r->id] = ['id' => (int)$r->id, 'name' => (string)($r->name ?: $r->phone), 'phone' => (string)$r->phone, 'dev' => self::device($r->ua), 'ago' => self::ago($r->last_seen_at)]; }
    $guests = (int)DB::table('app_installs')->where('last_seen_at', '>=', $since)->whereNull('login_customer_id')->count();
    return ['people' => array_values($people), 'guests' => $guests, 'installs' => (int)DB::table('app_installs')->count()];
  }

  /** Per-customer App facts for a set of customer ids: [cid => ['installed','first','last','live','dev','push','logged']]. */
  public static function forCustomers(array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids || !self::ready()) return [];
    $since = now()->subSeconds(self::LIVE_SEC)->timestamp;
    $rows = DB::table('app_installs')->where(fn($q) => $q->whereIn('login_customer_id', $ids)->orWhereIn('customer_id', $ids))->orderByDesc('last_seen_at')->get();
    $push = DB::table('push_subscriptions')->whereIn('customer_id', $ids)->where('active', true)->select('customer_id', DB::raw('count(*) n'))->groupBy('customer_id')->pluck('n', 'customer_id');
    $out = [];
    foreach ($rows as $r) {
      foreach (array_unique(array_filter([(int)$r->login_customer_id, (int)$r->customer_id])) as $cid) {
        if (!in_array($cid, $ids, true)) continue;
        $o = $out[$cid] ?? ['installs' => 0, 'first' => null, 'last' => null, 'live' => false, 'dev' => '—', 'push' => (int)($push[$cid] ?? 0), 'logged' => false];
        $o['installs']++;
        if (!$o['first'] || $r->first_seen_at < $o['first']) $o['first'] = $r->first_seen_at;
        if (!$o['last'] || $r->last_seen_at > $o['last']) { $o['last'] = $r->last_seen_at; $o['dev'] = self::device($r->ua); }
        if ((int)$r->login_customer_id === $cid && $r->last_seen_at && strtotime((string)$r->last_seen_at) >= $since) $o['live'] = true;
        if ((int)$r->login_customer_id === $cid) $o['logged'] = true;
        $out[$cid] = $o;
      }
    }
    return $out;
  }

  public static function label(?array $a): string {
    if (!$a) return '—';
    return ($a['live'] ? '🟢 Live now' : '📱 App · ' . self::ago($a['last'])) . ($a['push'] > 0 ? ' · 🔔' : '');
  }

  /** Customer ids that use the App (push audience "App users"). */
  public static function customerIds(bool $liveOnly = false): array {
    if (!self::ready()) return [0];
    if ($liveOnly) $ids = DB::table('app_installs')->where('last_seen_at', '>=', now()->subSeconds(self::LIVE_SEC))->whereNotNull('login_customer_id')->pluck('login_customer_id')->all();
    else $ids = array_merge(DB::table('app_installs')->whereNotNull('customer_id')->pluck('customer_id')->all(), DB::table('app_installs')->whereNotNull('login_customer_id')->pluck('login_customer_id')->all());
    return array_values(array_unique(array_map('intval', $ids))) ?: [0];
  }
}
