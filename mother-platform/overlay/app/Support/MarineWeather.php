<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Marine Supply: Port Weather for Chattogram, Mongla and Payra (owner brief 26 Nov 2026).
 * Sources, all without a subscription:
 *   - wind, temperature, rain: MET Norway Locationforecast 2.0 (CC BY 4.0), at the terminal;
 *   - waves: Copernicus Marine Global Ocean Waves Analysis and Forecast (VHM0, 3-hourly), at the outer anchorage, read from the public WMTS point query;
 *   - official warnings: Bangladesh Meteorological Department CAP feed, plus warnings the marine desk enters with a link to the original bulletin
 *     (BMD publishes its routine marine warning as a scanned PDF that is not in the CAP feed).
 * Everything here is a model estimate: there is no verified station source yet, so nothing is labelled "Observed".
 * The shared worker only starts a short background run (marine:weather) when something is due; the public API reads stored rows and never calls a source.
 * Each source keeps its last good data, the time that data is for, and the time we last checked the source.
 */
class MarineWeather
{
  /** Weather point = terminal, wave point = outer anchorage / fairway. Approximate chart positions: to be confirmed by the engineers before going live. */
  const PORTS = [
    'chattogram' => ['name' => 'Chattogram', 'terminal' => [22.3130, 91.8000], 'anchorage' => [22.2000, 91.7200], 'match' => '/chattogram|chittagong/i'],
    'mongla'     => ['name' => 'Mongla',     'terminal' => [22.4700, 89.5950], 'anchorage' => [21.4500, 89.5800], 'match' => '/mongla/i'],
    'payra'      => ['name' => 'Payra',      'terminal' => [21.9860, 90.2990], 'anchorage' => [21.6000, 90.3300], 'match' => '/payra/i'],
  ];
  const MET = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';
  const WMTS = 'https://wmts.marine.copernicus.eu/teroWmts/';
  const WAVE_LAYER = 'GLOBAL_ANALYSISFORECAST_WAV_001_027/cmems_mod_glo_wav_anfc_0.083deg_PT3H-i_202411/VHM0';
  const CAP_RSS = 'https://cap.bmd.gov.bd/api/cap/rss.xml';
  const CAP_HOST = 'cap.bmd.gov.bd';
  const BMD_PAGE = 'https://www.bmd.gov.bd/web/en/p/Marine-Warning';
  const WAVE_EVERY = 10800; const CAP_EVERY = 600; const RETRY = 900;

  // ---------- pure helpers (no database, no network): covered by the test script ----------

  public static function knots(float $ms): int { return (int)round($ms * 1.943844); }
  public static function compass(float $deg): string { $n = ['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW']; return $n[((int)round(fmod($deg + 360, 360) / 22.5)) % 16]; }

  /** MET Norway Locationforecast JSON -> model run time and an hourly series (first 60 steps). Null when the document is not usable. */
  public static function parseMet(string $json): ?array {
    $d = json_decode($json, true); $ts = $d['properties']['timeseries'] ?? null; if (!is_array($ts) || !$ts) return null;
    $upd = strtotime((string)($d['properties']['meta']['updated_at'] ?? '')); $o = [];
    foreach (array_slice($ts, 0, 60) as $e) {
      $t = strtotime((string)($e['time'] ?? '')); $i = $e['data']['instant']['details'] ?? null; if (!$t || !is_array($i) || !isset($i['air_temperature'], $i['wind_speed'], $i['wind_from_direction'])) continue;
      $n1 = $e['data']['next_1_hours'] ?? null;
      $o[] = ['t' => $t, 'temp' => round((float)$i['air_temperature'], 1), 'ms' => (float)$i['wind_speed'], 'dir' => (float)$i['wind_from_direction'],
        'rain' => isset($n1['details']['precipitation_amount']) ? round((float)$n1['details']['precipitation_amount'], 1) : null, 'sym' => isset($n1['summary']['symbol_code']) ? (string)$n1['summary']['symbol_code'] : null];
    }
    return $o ? ['updated' => $upd ?: null, 'series' => $o] : null;
  }

  /** The series step that describes "now": the latest step not in the future and not older than 2 hours. Null when the stored series no longer covers now. */
  public static function current(array $series, int $now): ?array { $c = null; foreach ($series as $e) { if ($e['t'] <= $now) $c = $e; else break; } return ($c && $now - $c['t'] <= 7200) ? $c : null; }

  /** Start of the 3-hour wave model step containing $now, and the next eight (24 hours ahead). */
  public static function waveSteps(int $now): array { $t = intdiv($now, 10800) * 10800; $o = []; for ($i = 0; $i < 9; $i++) $o[] = $t + $i * 10800; return $o; }

  /** WMTS GetFeatureInfo address for one point and time on the EPSG:4326 grid, level 6 (256 px tiles of 2.8125 degrees). */
  public static function waveUrl(float $lat, float $lon, int $t): string {
    $ts = 180 / 64; $cx = ($lon + 180) / $ts; $ry = (90 - $lat) / $ts; $col = (int)floor($cx); $row = (int)floor($ry);
    return self::WMTS . '?' . http_build_query(['SERVICE' => 'WMTS', 'VERSION' => '1.0.0', 'REQUEST' => 'GetFeatureInfo', 'LAYER' => self::WAVE_LAYER, 'TILEMATRIXSET' => 'EPSG:4326', 'TILEMATRIX' => '6',
      'TILEROW' => (string)$row, 'TILECOL' => (string)$col, 'I' => (string)(int)floor(($cx - $col) * 256), 'J' => (string)(int)floor(($ry - $row) * 256), 'INFOFORMAT' => 'application/json', 'TIME' => gmdate('Y-m-d\TH:i:s\Z', $t)]);
  }

  /** One WMTS answer -> significant wave height in metres and the model cell. Null for land cells, missing values or an unexpected unit. */
  public static function parseWave(string $json): ?array {
    $p = json_decode($json, true)['features'][0]['properties'] ?? null; if (!is_array($p) || !isset($p['value']) || !is_numeric($p['value'])) return null;
    $v = (float)$p['value']; if (is_nan($v) || $v < 0 || $v > 30 || (isset($p['units']) && $p['units'] !== 'm')) return null;
    return ['hs' => round($v, 1), 'lat' => isset($p['lat']) ? round((float)$p['lat'], 3) : null, 'lon' => isset($p['lon']) ? round((float)$p['lon'], 3) : null];
  }

  /** RSS -> [guid, link] of the newest items. Only links on BMD's CAP host are kept. */
  public static function parseRss(string $xml): array {
    $o = []; if (!preg_match_all('~<item>(.*?)</item>~s', $xml, $m)) return $o;
    foreach (array_slice($m[1], 0, 10) as $it) {
      if (!preg_match('~<link>\s*([^<\s]+)\s*</link>~', $it, $l)) continue; $u = html_entity_decode($l[1]);
      if (parse_url($u, PHP_URL_SCHEME) !== 'https' || parse_url($u, PHP_URL_HOST) !== self::CAP_HOST) continue;
      $g = preg_match('~<guid[^>]*>\s*([^<]+?)\s*</guid>~', $it, $x) ? $x[1] : $u; $o[] = ['guid' => mb_substr($g, 0, 120), 'link' => $u];
    }
    return $o;
  }

  /** A signal number is reported only when the bulletin text itself states one ("Signal 3", "signal no. 4", "signal number 7"). */
  public static function capSignal(string $text): ?int { return preg_match('/\bsignal\s*(?:no\.?|number|nr\.?)?\s*[-:]?\s*(\d{1,2})\b/i', $text, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 11 ? (int)$m[1] : null; }

  /** Which of our ports a bulletin covers, from its headline and area names ("maritime ports" = all three). */
  public static function capPorts(string $text): array { $o = []; $all = preg_match('/maritime\s+ports/i', $text) === 1; foreach (self::PORTS as $k => $p) if ($all || preg_match($p['match'], $text)) $o[] = $k; return $o; }

  /** One CAP 1.2 alert -> the fields we show. Null when it is not a CAP alert. */
  public static function parseCap(string $xml): ?array {
    $x = preg_replace('~(</?)cap:~', '$1', $xml); $x = preg_replace('~\sxmlns(:\w+)?="[^"]*"~', '', (string)$x);
    $prev = libxml_use_internal_errors(true); $a = simplexml_load_string((string)$x, 'SimpleXMLElement', LIBXML_NONET); libxml_clear_errors(); libxml_use_internal_errors($prev);
    if (!$a || $a->getName() !== 'alert' || !isset($a->info)) return null;
    $i = $a->info[0]; $areas = []; foreach ($i->area as $ar) $areas[] = trim((string)$ar->areaDesc);
    $head = trim((string)$i->headline) ?: trim((string)$i->event); $txt = $head . ' ' . implode(' ', $areas); $web = trim((string)$i->web);
    $refs = []; foreach (preg_split('/\s+/', trim((string)$a->references)) ?: [] as $r) { $p = explode(',', $r); if (isset($p[1]) && $p[1] !== '') $refs[] = $p[1]; }
    return ['identifier' => mb_substr(trim((string)$a->identifier), 0, 190), 'status' => trim((string)$a->status), 'msg_type' => trim((string)$a->msgType), 'refs' => $refs,
      'title' => mb_substr($head, 0, 200), 'event' => mb_substr(trim((string)$i->event), 0, 80), 'severity' => mb_substr(trim((string)$i->severity), 0, 20),
      'issued' => strtotime((string)$a->sent) ?: null, 'expires' => strtotime((string)$i->expires) ?: null,
      'ports' => self::capPorts($txt), 'signal' => self::capSignal($head . ' ' . (string)$i->description),
      'url' => (parse_url($web, PHP_URL_SCHEME) === 'https' && preg_match('/(^|\.)bmd\.gov\.bd$/', (string)parse_url($web, PHP_URL_HOST))) ? mb_substr($web, 0, 300) : null];
  }

  /** A warning counts while it has not been ended, has started, and has not passed its expiry. */
  public static function active(array $w, int $now): bool { return empty($w['ended']) && (empty($w['issued']) || $w['issued'] <= $now + 300) && (empty($w['expires']) || $w['expires'] > $now); }

  /**
   * The public answer, built only from stored rows.
   * $recs: "port|source" => ['payload' => array|null, 'ok' => bool, 'checked' => epoch|null, 'ok_at' => epoch|null]; sources: met, wave per port and "all|cap".
   * $warns: [['ports' => [...], 'signal' => ?int, 'title', 'url', 'issued', 'expires', 'ended', 'source' => cap|desk], ...]
   */
  public static function build(array $recs, array $warns, int $now): array {
    $iso = fn($t) => $t ? gmdate('Y-m-d\TH:i:s\Z', (int)$t) : null; $ports = [];
    $cap = $recs['all|cap'] ?? null; $capOk = $cap && !empty($cap['ok']) && !empty($cap['checked']) && $now - (int)$cap['checked'] <= 3600;
    foreach (self::PORTS as $k => $p) {
      $m = $recs[$k . '|met'] ?? null; $w = $recs[$k . '|wave'] ?? null; $series = $m['payload']['series'] ?? []; $steps = $w['payload']['steps'] ?? []; $hs = []; foreach ($steps as $s) if (isset($s[1])) $hs[(int)$s[0]] = (float)$s[1];
      $c = self::current($series, $now); $wt = intdiv($now, 10800) * 10800; $fc = [];
      foreach ($series as $e) { if ($e['t'] < $now - 3599 || $e['t'] > $now + 86400) continue; $fc[] = ['time' => $iso($e['t']), 'temp_c' => $e['temp'], 'wind_kn' => self::knots($e['ms']), 'wind_deg' => (int)round($e['dir']), 'wind_dir' => self::compass($e['dir']), 'rain_mm' => $e['rain'], 'symbol' => $e['sym'], 'wave_m' => $hs[$e['t']] ?? null]; }
      $ws = []; foreach ($warns as $x) if (in_array($k, $x['ports'] ?? [], true) && self::active($x, $now)) $ws[] = ['title' => (string)$x['title'], 'signal' => $x['signal'] ?? null, 'issued_at' => $iso($x['issued'] ?? null), 'expires_at' => $iso($x['expires'] ?? null), 'url' => $x['url'] ?: self::BMD_PAGE, 'source' => $x['source'] === 'cap' ? 'BMD CAP feed' : 'BMD bulletin (entered by our marine desk)'];
      usort($ws, fn($a, $b) => strcmp((string)$b['issued_at'], (string)$a['issued_at']));
      $ports[$k] = ['name' => $p['name'], 'kind' => 'Model estimate',
        'terminal' => ['lat' => $p['terminal'][0], 'lon' => $p['terminal'][1]], 'anchorage' => ['lat' => $w['payload']['cell'][0] ?? $p['anchorage'][0], 'lon' => $w['payload']['cell'][1] ?? $p['anchorage'][1]],
        'current' => $c ? ['time' => $iso($c['t']), 'temp_c' => $c['temp'], 'wind_kn' => self::knots($c['ms']), 'wind_deg' => (int)round($c['dir']), 'wind_dir' => self::compass($c['dir']), 'rain_mm' => $c['rain'], 'symbol' => $c['sym']] : null,
        'wave' => isset($hs[$wt]) ? ['time' => $iso($wt), 'hs_m' => $hs[$wt]] : null,
        'forecast' => $fc,
        'weather_source' => ['name' => 'MET Norway Locationforecast', 'data_time' => $iso($m['payload']['updated'] ?? null), 'checked_at' => $iso($m['checked'] ?? null), 'last_ok_at' => $iso($m['ok_at'] ?? null), 'ok' => (bool)($m['ok'] ?? false)],
        'wave_source' => ['name' => 'Copernicus Marine Global Ocean Waves Forecast', 'data_time' => isset($hs[$wt]) ? $iso($wt) : null, 'checked_at' => $iso($w['checked'] ?? null), 'last_ok_at' => $iso($w['ok_at'] ?? null), 'ok' => (bool)($w['ok'] ?? false)],
        'warnings' => $ws, 'no_active_warning' => $capOk && !$ws];
    }
    return ['generated_at' => $iso($now), 'tz' => 'UTC+6', 'ports' => $ports,
      'bmd' => ['ok' => $capOk, 'checked_at' => $iso($cap['checked'] ?? null), 'last_ok_at' => $iso($cap['ok_at'] ?? null), 'page' => self::BMD_PAGE],
      'credits' => ['Weather: MET Norway (CC BY 4.0)', 'Waves: E.U. Copernicus Marine Service Information', 'Warnings: Bangladesh Meteorological Department'],
      'note' => 'Model estimates for supply planning. Not for navigation.'];
  }

  // ---------- stored rows ----------

  public static function ready(): bool { try { return Schema::hasTable('marine_weather') && Schema::hasTable('marine_weather_warnings'); } catch (\Throwable $e) { return false; } }
  public static function on(): bool { try { return (string)DB::table('settings')->where('key', 'marine_weather_on')->value('value') !== '0'; } catch (\Throwable $e) { return false; } }

  /** Go-live switch (after the engineers approve): until then the page is reachable by its address only, with no menu entry and no banner on Marine Overview. */
  public static function live(): bool { try { return (string)Cache::remember('marine_wx_live', 60, fn() => (string)DB::table('settings')->where('key', 'marine_weather_live')->value('value')) === '1'; } catch (\Throwable $e) { return false; } }
  private static function row(string $port, string $src): ?object { return DB::table('marine_weather')->where('port', $port)->where('source', $src)->first(); }
  private static function save(string $port, string $src, array $f): void { DB::table('marine_weather')->updateOrInsert(['port' => $port, 'source' => $src], $f + ['checked_at' => now(), 'updated_at' => now()]); }
  private static function fail(string $port, string $src, string $why, int $retry = self::RETRY): void { self::save($port, $src, ['ok' => false, 'note' => mb_substr($why, 0, 190), 'next_at' => now()->addSeconds($retry)]); }

  public static function recs(): array { $o = []; foreach (DB::table('marine_weather')->get() as $r) $o[$r->port . '|' . $r->source] = ['payload' => $r->payload ? json_decode($r->payload, true) : null, 'ok' => (bool)$r->ok, 'checked' => $r->checked_at ? strtotime($r->checked_at) : null, 'ok_at' => $r->ok_at ? strtotime($r->ok_at) : null, 'note' => $r->note, 'next' => $r->next_at ? strtotime($r->next_at) : null]; return $o; }
  public static function warns(): array {
    $o = []; foreach (DB::table('marine_weather_warnings')->whereNull('ended_at')->where(fn($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()->subDay()))->orderByDesc('issued_at')->limit(60)->get() as $r)
      $o[] = ['id' => (int)$r->id, 'ports' => array_values(array_filter(explode(',', (string)$r->ports))), 'signal' => $r->signal !== null ? (int)$r->signal : null, 'title' => (string)$r->title, 'url' => (string)$r->bulletin_url, 'issued' => $r->issued_at ? strtotime($r->issued_at) : null, 'expires' => $r->expires_at ? strtotime($r->expires_at) : null, 'ended' => null, 'source' => (string)$r->source, 'ack' => $r->ack_at !== null, 'by' => (string)($r->created_by ?? '')];
    return $o;
  }
  public static function api(): array { return Cache::remember('marine.wx', 60, fn() => self::build(self::recs(), self::warns(), time())); }
  /** Active official warnings nobody at the desk has reviewed yet: the desk sees "Review delivery time" for these ports. */
  public static function review(): array { try { if (!self::ready()) return []; $now = time(); return array_values(array_filter(self::warns(), fn($w) => !$w['ack'] && $w['ports'] && self::active($w, $now))); } catch (\Throwable $e) { return []; } }

  // ---------- background refresh ----------

  /** Called by the shared worker every tick: starts a short background run when it is time. Cheap when nothing is due. */
  public static function ensure(): void {
    if (!self::ready() || !self::on() || time() < (int)Cache::get('marine_wx_next', 0)) return;
    Cache::put('marine_wx_next', time() + 300, 3600);
    @exec('nohup nice -n 15 php ' . escapeshellarg(base_path('artisan')) . ' marine:weather > /dev/null 2>&1 &');
  }

  private static function ua(): string { $e = ''; try { $e = trim((string)DB::table('settings')->where('key', 'marine_desk_email')->value('value')); } catch (\Throwable $x) {} return 'marinedutyfreebd.com PortWeather/1.0 ' . ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : 'https://marinedutyfreebd.com/request-access'); }
  private static function due(?object $r): bool { return !$r || !$r->next_at || strtotime($r->next_at) <= time(); }

  public static function refresh(bool $force = false): array {
    $done = [];
    foreach (self::PORTS as $k => $p) {
      try { $done[$k . ' weather'] = self::refreshMet($k, $p, $force); } catch (\Throwable $e) { self::fail($k, 'met', $e->getMessage()); $done[$k . ' weather'] = 'failed'; }
      try { $done[$k . ' waves'] = self::refreshWave($k, $p, $force); } catch (\Throwable $e) { self::fail($k, 'wave', $e->getMessage()); $done[$k . ' waves'] = 'failed'; }
    }
    try { $done['BMD'] = self::refreshCap($force); } catch (\Throwable $e) { self::fail('all', 'cap', $e->getMessage()); $done['BMD'] = 'failed'; }
    Cache::forget('marine.wx'); return $done;
  }

  /** MET Norway terms: identifying User-Agent, at most 4 decimals, never ask again before Expires, then If-Modified-Since. A forced run still respects Expires. */
  private static function refreshMet(string $k, array $p, bool $force): string {
    $r = self::row($k, 'met'); if (!self::due($r)) return 'not due';
    $h = ['User-Agent' => self::ua(), 'Accept' => 'application/json']; if ($r && $r->last_modified && $r->payload) $h['If-Modified-Since'] = $r->last_modified;
    $res = Http::withHeaders($h)->timeout(15)->get(self::MET, ['lat' => number_format($p['terminal'][0], 4, '.', ''), 'lon' => number_format($p['terminal'][1], 4, '.', '')]);
    $exp = strtotime((string)$res->header('Expires')); $next = now()->setTimestamp(max(time() + 300, min($exp ?: time() + 1800, time() + 7200)));
    if ($res->status() === 304) { self::save($k, 'met', ['ok' => true, 'ok_at' => now(), 'note' => null, 'next_at' => $next]); return 'unchanged'; }
    if (!$res->successful()) { self::fail($k, 'met', 'HTTP ' . $res->status(), $res->status() === 429 ? 3600 : self::RETRY); return 'failed'; }
    $d = self::parseMet($res->body()); if (!$d) { self::fail($k, 'met', 'Unreadable answer'); return 'failed'; }
    self::save($k, 'met', ['ok' => true, 'ok_at' => now(), 'note' => null, 'payload' => json_encode($d), 'data_time' => $d['updated'] ? now()->setTimestamp($d['updated']) : null, 'last_modified' => mb_substr((string)$res->header('Last-Modified'), 0, 60) ?: null, 'next_at' => $next]);
    return 'updated';
  }

  /** Nine 3-hourly steps (now to +24 h) at the anchorage point. Refreshed every 3 hours; kept as it was when the source cannot be read. */
  private static function refreshWave(string $k, array $p, bool $force): string {
    $r = self::row($k, 'wave'); if (!$force && !self::due($r)) return 'not due';
    $steps = []; $cell = null; $good = 0;
    foreach (self::waveSteps(time()) as $t) {
      $v = null; try { $res = Http::withHeaders(['User-Agent' => self::ua()])->timeout(12)->get(self::waveUrl($p['anchorage'][0], $p['anchorage'][1], $t)); if ($res->successful()) $v = self::parseWave($res->body()); } catch (\Throwable $e) {}
      if ($v) { $good++; $cell = $cell ?: [$v['lat'], $v['lon']]; } $steps[] = [$t, $v['hs'] ?? null];
    }
    if (!isset($steps[0][1]) || $good < 5) { self::fail($k, 'wave', 'Only ' . $good . ' of 9 time steps could be read'); return 'failed'; }
    $nx = intdiv(time(), 10800) * 10800 + self::WAVE_EVERY + 120;
    self::save($k, 'wave', ['ok' => true, 'ok_at' => now(), 'note' => null, 'payload' => json_encode(['cell' => $cell, 'steps' => $steps]), 'data_time' => now()->setTimestamp($steps[0][0]), 'next_at' => now()->setTimestamp($nx)]);
    return 'updated';
  }

  /** BMD CAP feed: newest items; each alert document is read once and stored. Cancel / Update messages end the alerts they reference. */
  private static function refreshCap(bool $force): string {
    $r = self::row('all', 'cap'); if (!$force && !self::due($r)) return 'not due';
    $res = Http::withHeaders(['User-Agent' => self::ua()])->timeout(15)->get(self::CAP_RSS);
    if (!$res->successful() || stripos($res->body(), '<rss') === false) { self::fail('all', 'cap', 'HTTP ' . $res->status()); return 'failed'; }
    $new = 0;
    foreach (self::parseRss($res->body()) as $it) {
      if (DB::table('marine_weather_warnings')->where('cap_guid', $it['guid'])->exists()) continue;
      $x = Http::withHeaders(['User-Agent' => self::ua()])->timeout(15)->get($it['link']); if (!$x->successful()) { self::fail('all', 'cap', 'Alert document HTTP ' . $x->status()); return 'failed'; }
      $a = self::parseCap($x->body()); $now = now();
      if (!$a) { DB::table('marine_weather_warnings')->insert(['source' => 'cap', 'cap_guid' => $it['guid'], 'ports' => '', 'title' => 'Unreadable CAP document', 'bulletin_url' => $it['link'], 'ended_at' => $now, 'created_at' => $now, 'updated_at' => $now]); continue; }
      if ($a['refs'] && in_array($a['msg_type'], ['Cancel', 'Update'], true)) DB::table('marine_weather_warnings')->where('source', 'cap')->whereIn('cap_identifier', $a['refs'])->whereNull('ended_at')->update(['ended_at' => $now, 'updated_at' => $now]);
      $live = $a['status'] === 'Actual' && $a['msg_type'] !== 'Cancel' && $a['msg_type'] !== 'Ack';
      DB::table('marine_weather_warnings')->insert(['source' => 'cap', 'cap_guid' => $it['guid'], 'cap_identifier' => $a['identifier'] ?: null, 'ports' => implode(',', $a['ports']), 'signal' => $a['signal'], 'title' => $a['title'] ?: 'BMD alert', 'event' => $a['event'], 'severity' => $a['severity'],
        'bulletin_url' => $a['url'] ?: $it['link'], 'issued_at' => $a['issued'] ? now()->setTimestamp($a['issued']) : $now, 'expires_at' => $a['expires'] ? now()->setTimestamp($a['expires']) : $now->copy()->addDay(), 'ended_at' => $live ? null : $now, 'created_by' => 'BMD CAP feed', 'created_at' => $now, 'updated_at' => $now]);
      $new++;
    }
    self::save('all', 'cap', ['ok' => true, 'ok_at' => now(), 'note' => null, 'next_at' => now()->addSeconds(self::CAP_EVERY)]);
    return $new ? $new . ' new' : 'checked';
  }
}
