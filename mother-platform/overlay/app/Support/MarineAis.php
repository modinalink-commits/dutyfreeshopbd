<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marine Supply: collects public AIS data (vessel names, details, positions) for the Bay of Bengal from AISStream into marine_vessels.
 * Owner 26 Nov 2026: free ship tracking on marinedutyfreebd.com with name search and full vessel details, from data held on our own server.
 * A small dedicated loop (marine:ais) keeps one websocket open for ~20 minutes; the shared worker only makes sure the loop is alive.
 * The API key is an encrypted integration secret (marine_aisstream_key) saved on Admin > Marine Supply > Vessel Tracking.
 */
class MarineAis
{
  /** South-west and north-east corners [lat, lon]: Bay of Bengal north of 17N, including Chattogram, Mongla, Payra and their approaches. */
  const BOX = [[[17.0, 86.0], [23.6, 93.5]]];
  const HOST = 'stream.aisstream.io';

  public static function ensureLoop(): void {
    if (!\App\Http\Controllers\MarineController::aisOn() || !Schema::hasTable('marine_vessels')) return;
    if (time() - (int)Cache::get('marine_ais_hb', 0) < 90) return;
    Cache::put('marine_ais_hb', time(), 300);
    @exec('nohup php ' . escapeshellarg(base_path('artisan')) . ' marine:ais > /dev/null 2>&1 &');
  }

  private static function note(?string $m): void { if ($m === null) Cache::forget('marine_ais_err'); else Cache::put('marine_ais_err', mb_substr($m, 0, 200) . ' (' . now()->format('d M H:i') . ')', 86400); }

  public static function run(int $secs = 1200): void {
    $key = (string)Sms::secret('marine_aisstream_key'); if ($key === '') return;
    $fp = self::open($key, self::BOX, $why); if (!$fp) { self::note($why); return; }
    try {
      $end = time() + $secs; $buf = []; $flush = time(); $msg = ''; $got = 0; $idle = time(); $chk = time(); $t0 = time();
      while (time() < $end) {
        $f = self::frame($fp);
        if ($f === false) { self::note($got ? null : 'AISStream closed the connection before any data. Check the key on the Vessel Tracking page.'); break; }
        if ($f !== null) {
          [$fin, $op, $pl] = $f;
          if ($op === 8) { if (!$got) self::note('AISStream closed the connection: ' . preg_replace('/[^\x20-\x7e]/', '', substr($pl, 2, 120))); break; }
          if ($op === 9) self::send($fp, 10, $pl);
          elseif ($op === 0 || $op === 1 || $op === 2) {
            $msg .= $pl; if (strlen($msg) > 262144) $msg = '';
            if ($fin) { if (self::take($msg, $buf)) { $got++; $idle = time(); } elseif (!$got && str_contains($msg, 'rror')) { self::note('AISStream: ' . preg_replace('/[^\x20-\x7e]/', '', mb_substr($msg, 0, 150))); } $msg = ''; }
          }
        }
        if (time() - $flush >= 5) { self::flush($buf); $buf = []; $flush = time(); Cache::put('marine_ais_hb', time(), 300); Cache::put('marine_ais_stat', ['connected' => $t0, 'msgs' => $got, 'at' => time()], 3600); if ($got) self::note(null); }
        if (time() - $idle > 240) { self::send($fp, 9, 'k'); $idle = time() - 120; }
        if (time() - $chk > 60) { $chk = time(); if (!\App\Http\Controllers\MarineController::aisOn()) break; }
      }
      self::flush($buf);
    } catch (\Throwable $e) { self::note('Collector error: ' . $e->getMessage()); report($e); }
    finally { @fclose($fp); }
  }

  /** Opens the stream and subscribes to a bounding box. Returns the socket, or null with the reason in $why. */
  private static function open(string $key, array $box, &$why = null) {
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => self::HOST, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client('tls://' . self::HOST . ':443', $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $why = 'Could not connect to AISStream: ' . $es; return null; }
    stream_set_timeout($fp, 20);
    fwrite($fp, "GET /v0/stream HTTP/1.1\r\nHost: " . self::HOST . "\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: " . base64_encode(random_bytes(16)) . "\r\nSec-WebSocket-Version: 13\r\nUser-Agent: DutyFreeBD-Marine/1.0\r\n\r\n");
    $h = ''; while (!str_contains($h, "\r\n\r\n")) { $c = fread($fp, 1); if ($c === '' || $c === false) { if (feof($fp) || stream_get_meta_data($fp)['timed_out']) break; continue; } $h .= $c; if (strlen($h) > 8192) break; }
    if (!preg_match('#^HTTP/1\.1 101#', $h)) { $why = 'AISStream refused the connection: ' . trim(strtok($h, "\r\n") ?: 'no reply'); @fclose($fp); return null; }
    self::send($fp, 1, json_encode(['APIKey' => $key, 'BoundingBoxes' => $box, 'FilterMessageTypes' => ['PositionReport', 'StandardClassBPositionReport', 'ShipStaticData']]));
    return $fp;
  }

  /** Connection test for the admin page: listens to one area for a few seconds and counts the vessel messages. Saves nothing. */
  public static function probe(array $box, int $secs = 10): array {
    $key = (string)Sms::secret('marine_aisstream_key'); if ($key === '') return ['ok' => false, 'msgs' => 0, 'note' => 'No key saved'];
    $fp = self::open($key, $box, $why); if (!$fp) return ['ok' => false, 'msgs' => 0, 'note' => $why];
    $n = 0; $names = []; $note = ''; $end = time() + $secs; $msg = ''; stream_set_timeout($fp, 3);
    try { while (time() < $end) { $f = self::frame($fp); if ($f === false) { $note = $n ? '' : 'Connection closed by AISStream'; break; } if ($f === null) continue; [$fin, $op, $pl] = $f; if ($op === 8) { $note = 'Closed: ' . preg_replace('/[^\x20-\x7e]/', '', substr($pl, 2, 100)); break; } if ($op === 9) { self::send($fp, 10, $pl); continue; }
      if ($op <= 2) { $msg .= $pl; if ($fin) { $m = json_decode($msg, true); if (is_array($m) && isset($m['MessageType'])) { $n++; $nm = trim((string)((array_change_key_case((array)($m['MetaData'] ?? []), CASE_LOWER))['shipname'] ?? '')); if ($nm !== '' && count($names) < 5) $names[$nm] = 1; } elseif (!$n) $note = preg_replace('/[^\x20-\x7e]/', '', mb_substr($msg, 0, 150)); $msg = ''; } } } }
    catch (\Throwable $e) { $note = $e->getMessage(); } finally { @fclose($fp); }
    return ['ok' => true, 'msgs' => $n, 'names' => array_keys($names), 'note' => $note];
  }

  private static function send($fp, int $op, string $pl): void {
    $n = strlen($pl); $mask = random_bytes(4); $h = chr(0x80 | $op);
    $h .= $n < 126 ? chr(0x80 | $n) : ($n < 65536 ? chr(0x80 | 126) . pack('n', $n) : chr(0x80 | 127) . pack('J', $n));
    $m = ''; for ($i = 0; $i < $n; $i++) $m .= $pl[$i] ^ $mask[$i & 3];
    fwrite($fp, $h . $mask . $m);
  }

  /** Exactly $n bytes. null = nothing arrived (idle, only when $idleOk); false = connection lost. */
  private static function readN($fp, int $n, bool $idleOk = false) {
    $b = ''; $t0 = time();
    while (strlen($b) < $n) {
      $c = fread($fp, $n - strlen($b));
      if ($c === '' || $c === false) {
        if (feof($fp)) return false;
        if (stream_get_meta_data($fp)['timed_out']) { if ($b === '' && $idleOk) return null; if (time() - $t0 > 60) return false; }
        continue;
      }
      $b .= $c;
    }
    return $b;
  }

  /** One websocket frame: [fin, opcode, payload]; null when idle; false when the connection is gone. */
  private static function frame($fp) {
    $h = self::readN($fp, 2, true); if ($h === null || $h === false) return $h;
    $b0 = ord($h[0]); $b1 = ord($h[1]); $len = $b1 & 0x7f;
    if ($len === 126) { $x = self::readN($fp, 2); if ($x === false) return false; $len = unpack('n', $x)[1]; }
    elseif ($len === 127) { $x = self::readN($fp, 8); if ($x === false) return false; $len = unpack('J', $x)[1]; }
    if ($len > 1048576) return false;
    $mask = ($b1 & 0x80) ? self::readN($fp, 4) : null; if ($mask === false) return false;
    $pl = $len ? self::readN($fp, $len) : ''; if ($pl === false) return false;
    if ($mask) { $o = ''; for ($i = 0; $i < $len; $i++) $o .= $pl[$i] ^ $mask[$i & 3]; $pl = $o; }
    return [(bool)($b0 & 0x80), $b0 & 0x0f, $pl];
  }

  private static function txt($v, int $n): ?string { $v = trim(preg_replace('/\s+/', ' ', preg_replace('/[^\x20-\x7e]/', '', str_replace('@', ' ', (string)$v)))); return $v === '' ? null : mb_substr($v, 0, $n); }

  /** One AISStream JSON message into the buffer (keyed by MMSI). True when it was a usable vessel message. */
  private static function take(string $json, array &$buf): bool {
    $m = json_decode($json, true); if (!is_array($m)) return false;
    $t = (string)($m['MessageType'] ?? ''); $md = array_change_key_case((array)($m['MetaData'] ?? []), CASE_LOWER); $p = (array)(($m['Message'] ?? [])[$t] ?? []);
    $mmsi = (int)($md['mmsi'] ?? $p['UserID'] ?? 0); if ($mmsi < 100000000 || $mmsi > 799999999) return $mmsi > 0; // ships only (not shore stations, aircraft, aids)
    $r = $buf[$mmsi] ?? ['mmsi' => $mmsi]; $r['seen_at'] = now();
    if ($n = self::txt($md['shipname'] ?? null, 60)) $r['name'] = $n;
    if ($t === 'PositionReport' || $t === 'StandardClassBPositionReport') {
      $la = $p['Latitude'] ?? $md['latitude'] ?? null; $lo = $p['Longitude'] ?? $md['longitude'] ?? null;
      if (is_numeric($la) && is_numeric($lo) && abs((float)$la) <= 90 && abs((float)$lo) <= 180 && !((float)$la == 0.0 && (float)$lo == 0.0)) { $r['lat'] = round((float)$la, 5); $r['lon'] = round((float)$lo, 5); $r['pos_at'] = now(); }
      if (isset($p['Sog']) && is_numeric($p['Sog']) && $p['Sog'] < 102) $r['sog'] = round((float)$p['Sog'], 1);
      if (isset($p['Cog']) && is_numeric($p['Cog']) && $p['Cog'] < 360) $r['cog'] = round((float)$p['Cog'], 1);
      if (isset($p['TrueHeading']) && is_numeric($p['TrueHeading']) && $p['TrueHeading'] < 360) $r['heading'] = (int)$p['TrueHeading'];
      if (isset($p['NavigationalStatus']) && is_numeric($p['NavigationalStatus'])) $r['nav_status'] = (int)$p['NavigationalStatus'];
    } elseif ($t === 'ShipStaticData') {
      $imo = (int)($p['ImoNumber'] ?? 0); if ($imo >= 1000000 && $imo <= 9999999) $r['imo'] = $imo;
      if ($n = self::txt($p['Name'] ?? null, 60)) $r['name'] = $n;
      if ($n = self::txt($p['CallSign'] ?? null, 12)) $r['callsign'] = $n;
      if (isset($p['Type']) && is_numeric($p['Type']) && $p['Type'] > 0) $r['ship_type'] = (int)$p['Type'];
      $d = (array)($p['Dimension'] ?? []); $len = (int)($d['A'] ?? 0) + (int)($d['B'] ?? 0); $bm = (int)($d['C'] ?? 0) + (int)($d['D'] ?? 0);
      if ($len > 0 && $len < 520) $r['length'] = $len; if ($bm > 0 && $bm < 80) $r['beam'] = $bm;
      if (isset($p['MaximumStaticDraught']) && is_numeric($p['MaximumStaticDraught']) && $p['MaximumStaticDraught'] > 0 && $p['MaximumStaticDraught'] < 26) $r['draught'] = round((float)$p['MaximumStaticDraught'], 1);
      $r['destination'] = self::txt($p['Destination'] ?? null, 40);
      $e = (array)($p['Eta'] ?? []); $mo = (int)($e['Month'] ?? 0); $dy = (int)($e['Day'] ?? 0); $hr = (int)($e['Hour'] ?? 24); $mi = (int)($e['Minute'] ?? 60);
      $r['eta'] = ($mo >= 1 && $mo <= 12 && $dy >= 1 && $dy <= 31) ? sprintf('%02d-%02d %02d:%02d', $mo, $dy, $hr < 24 ? $hr : 0, $mi < 60 ? $mi : 0) : null;
      $r['static_at'] = now();
    } else return false;
    $buf[$mmsi] = $r; return true;
  }

  private static function flush(array $buf): void {
    foreach ($buf as $r) { try { $mm = $r['mmsi']; unset($r['mmsi']); $r['updated_at'] = now(); DB::table('marine_vessels')->updateOrInsert(['mmsi' => $mm], $r); } catch (\Throwable $e) { self::note('Save error: ' . $e->getMessage()); } }
  }

  /** AIS ship type code to a plain name. */
  public static function typeName(?int $t): string {
    if (!$t) return '';
    if ($t >= 70 && $t <= 79) return 'Cargo ship'; if ($t >= 80 && $t <= 89) return 'Tanker'; if ($t >= 60 && $t <= 69) return 'Passenger ship'; if ($t >= 40 && $t <= 49) return 'High-speed craft';
    return [30 => 'Fishing vessel', 31 => 'Towing vessel', 32 => 'Towing vessel', 33 => 'Dredger', 34 => 'Diving vessel', 35 => 'Military vessel', 36 => 'Sailing vessel', 37 => 'Pleasure craft', 50 => 'Pilot vessel', 51 => 'Search and rescue vessel', 52 => 'Tug', 53 => 'Port tender', 54 => 'Anti-pollution vessel', 55 => 'Law enforcement vessel', 58 => 'Medical transport', 59 => 'Non-combatant ship'][$t] ?? 'Other vessel';
  }
  public static function navName(?int $s): string { return [0 => 'Under way using engine', 1 => 'At anchor', 2 => 'Not under command', 3 => 'Restricted manoeuvrability', 4 => 'Constrained by draught', 5 => 'Moored', 6 => 'Aground', 7 => 'Engaged in fishing', 8 => 'Under way sailing'][$s ?? -1] ?? ''; }
  /** Flag state from the first three digits of the MMSI (ITU maritime identification digits) for the common ship registers. */
  public static function flag(int $mmsi): string {
    static $M = null; if ($M === null) { $M = []; foreach (['Panama' => [351, 352, 353, 354, 355, 356, 357, 370, 371, 372, 373, 374], 'Liberia' => [636, 637], 'Marshall Islands' => [538], 'Hong Kong' => [477], 'Singapore' => [563, 564, 565, 566], 'Malta' => [215, 229, 248, 249, 256], 'Bahamas' => [308, 309, 311], 'China' => [412, 413, 414], 'Greece' => [237, 239, 240, 241], 'Japan' => [431, 432], 'Bangladesh' => [405], 'India' => [419], 'South Korea' => [440, 441], 'Cyprus' => [209, 210, 212], 'Norway' => [257, 258, 259], 'United Kingdom' => [232, 233, 234, 235], 'Denmark' => [219, 220], 'Germany' => [211, 218], 'Italy' => [247], 'Portugal' => [255, 263], 'Vietnam' => [574], 'Indonesia' => [525], 'Malaysia' => [533], 'Thailand' => [567], 'Philippines' => [548], 'Turkey' => [271], 'Russia' => [273], 'Netherlands' => [244, 245, 246], 'Belize' => [312], 'St Vincent and the Grenadines' => [375, 376, 377], 'Antigua and Barbuda' => [304, 305], 'Tuvalu' => [572], 'Palau' => [511], 'Comoros' => [616, 620], 'Togo' => [671], 'Sierra Leone' => [667], 'Tanzania' => [674, 677], 'Sri Lanka' => [417], 'Myanmar' => [506], 'United Arab Emirates' => [470, 471], 'Saudi Arabia' => [403], 'Iran' => [422], 'Taiwan' => [416], 'United States' => [338, 366, 367, 368, 369], 'Cook Islands' => [518], 'Barbados' => [314], 'Cayman Islands' => [319], 'Bermuda' => [310], 'Gibraltar' => [236], 'France' => [226, 227, 228], 'Spain' => [224, 225], 'Belgium' => [205], 'Sweden' => [265, 266], 'Finland' => [230], 'Kiribati' => [529], 'Mongolia' => [457], 'Cambodia' => [514, 515], 'Dominica' => [325], 'St Kitts and Nevis' => [341], 'Vanuatu' => [576, 577], 'Honduras' => [334], 'Luxembourg' => [253], 'Pakistan' => [463], 'Maldives' => [455], 'Qatar' => [466], 'Kuwait' => [447], 'Oman' => [461], 'Egypt' => [622], 'Australia' => [503], 'Croatia' => [238], 'Isle of Man' => [], 'Switzerland' => [269], 'Brazil' => [710]] as $n => $ids) foreach ($ids as $i) $M[$i] = $n; }
    return $M[intdiv($mmsi, 1000000)] ?? '';
  }
}
