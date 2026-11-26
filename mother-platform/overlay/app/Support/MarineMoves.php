<?php
namespace App\Support;

/** Port Arrivals & Departures: rules kept as plain functions so they can be tested without the framework.
 *  A visit carries up to two confirmed events (arrival, departure). Nothing estimated is stored here. */
class MarineMoves {
  const PORTS = ['chattogram' => 'Chattogram', 'mongla' => 'Mongla', 'payra' => 'Payra'];
  const SOURCES = ['port_record' => 'Port record', 'agent' => 'Local agent confirmation', 'feed' => 'Approved feed'];
  const STATUS = ['draft' => 'Draft', 'verified' => 'Verified', 'published' => 'Published'];
  const TZ = 'Asia/Dhaka'; const PER = 24; const MAX_DAYS = 92; const FEED_LATE_MIN = 90;
  const PHOTO_EXT = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']; const PHOTO_MAX = 5242880;

  private static function s($v, int $n): string { return mb_substr(trim(strip_tags((string)$v)), 0, $n); }
  /** Bangladesh local "Y-m-d\TH:i" (or with a space) -> UTC "Y-m-d H:i:s"; null when empty; false when unreadable. */
  public static function toUtc($v) {
    $v = trim((string)$v); if ($v === '') return null;
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1]) || (int)$m[4] > 23 || (int)$m[5] > 59) return false;
    $d = new \DateTime("$m[1]-$m[2]-$m[3] $m[4]:$m[5]:00", new \DateTimeZone(self::TZ)); $d->setTimezone(new \DateTimeZone('UTC')); return $d->format('Y-m-d H:i:s');
  }
  public static function local(?string $utc, string $fmt = 'Y-m-d\TH:i'): string { if (!$utc) return ''; $d = new \DateTime($utc, new \DateTimeZone('UTC')); $d->setTimezone(new \DateTimeZone(self::TZ)); return $d->format($fmt); }
  public static function iso(?string $utc): ?string { if (!$utc) return null; return (new \DateTime($utc, new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'); }

  /** Desk form -> [clean row, list of problems]. $now = current UTC time "Y-m-d H:i:s". */
  public static function clean(array $in, string $now): array {
    $e = []; $d = [];
    $d['vessel'] = mb_strtoupper(self::s($in['vessel'] ?? '', 120)); if (mb_strlen($d['vessel']) < 2) $e[] = 'Enter the vessel name.';
    $d['imo'] = preg_replace('/\D/', '', (string)($in['imo'] ?? '')); if (!preg_match('/^\d{7}$/', $d['imo'])) $e[] = 'The IMO number must be 7 digits.';
    $mm = preg_replace('/\D/', '', (string)($in['mmsi'] ?? '')); if ($mm !== '' && !preg_match('/^\d{9}$/', $mm)) $e[] = 'The MMSI must be 9 digits, or left empty.'; $d['mmsi'] = $mm !== '' ? $mm : null;
    $d['vessel_type'] = self::s($in['vessel_type'] ?? '', 60) ?: null;
    $d['port'] = (string)($in['port'] ?? ''); if (!isset(self::PORTS[$d['port']])) $e[] = 'Choose the port.';
    $d['visit_ref'] = self::s($in['visit_ref'] ?? '', 60) ?: null; $d['berth'] = self::s($in['berth'] ?? '', 120) ?: null;
    $d['internal_note'] = self::s($in['internal_note'] ?? '', 1000) ?: null;
    foreach (['arrival' => 'arrived_at', 'departure' => 'departed_at'] as $k => $col) {
      $t = self::toUtc($in[$col] ?? ''); $src = (string)($in[$k . '_source'] ?? ''); $ref = self::s($in[$k . '_ref'] ?? '', 200);
      if ($t === false) { $e[] = 'The actual ' . $k . ' time is not a valid date and time.'; $t = null; }
      if ($t !== null && $t > date('Y-m-d H:i:s', strtotime($now . ' UTC') + 300)) $e[] = 'The actual ' . $k . ' time is in the future. Only confirmed events are recorded here; keep an expected time in Port Calls.';
      if ($t !== null && !isset(self::SOURCES[$src])) $e[] = 'Choose the confirmation source for the ' . $k . '.';
      $d[$col] = $t; $d[$k . '_source'] = $t !== null && isset(self::SOURCES[$src]) ? $src : null; $d[$k . '_ref'] = $t !== null && $ref !== '' ? $ref : null;
    }
    if ($d['arrived_at'] === null && $d['departed_at'] === null) $e[] = 'Enter the actual arrival or the actual departure time.';
    if ($d['arrived_at'] !== null && $d['departed_at'] !== null && $d['departed_at'] < $d['arrived_at']) $e[] = 'The departure cannot be before the arrival.';
    return [$d, $e];
  }
  /** What still stops a visit from being marked Verified (and so from being published). */
  public static function verifyProblems($v): array {
    $v = (array)$v; $e = []; $any = false;
    foreach (['arrival' => 'arrived_at', 'departure' => 'departed_at'] as $k => $col) {
      if (empty($v[$col])) continue; $any = true;
      if (empty($v[$k . '_source']) || !isset(self::SOURCES[$v[$k . '_source']])) $e[] = 'The ' . $k . ' has no confirmation source.';
      if (trim((string)($v[$k . '_ref'] ?? '')) === '') $e[] = 'The ' . $k . ' has no source evidence (record number, agent name or feed reference).';
    }
    if (!$any) $e[] = 'There is no confirmed event on this visit.';
    return $e;
  }
  /** True when a saved change touches a confirmed fact, so the entry must be verified again. */
  public static function factsChanged($old, array $new): bool {
    $old = (array)$old; foreach (['imo', 'port', 'vessel', 'arrived_at', 'arrival_source', 'arrival_ref', 'departed_at', 'departure_source', 'departure_ref'] as $k) if ((string)($old[$k] ?? '') !== (string)($new[$k] ?? '')) return true; return false;
  }
  /** Finds the visit an incoming entry belongs to, among the existing visits of the same IMO and port, so an edit or a repeated sync never makes a second row.
   *  Order: same feed key; same visit reference; an open visit (arrived, not departed) that this entry closes or repeats; same arrival minute; same departure minute. */
  public static function match(array $rows, array $d, ?string $feedKey = null): ?int {
    $min = fn($t) => $t ? substr((string)$t, 0, 16) : '';
    if ($feedKey) foreach ($rows as $r) { $r = (array)$r; if (($r['feed_key'] ?? null) === $feedKey) return (int)$r['id']; }
    $ref = mb_strtolower(trim((string)($d['visit_ref'] ?? '')));
    if ($ref !== '') { foreach ($rows as $r) { $r = (array)$r; if (mb_strtolower(trim((string)($r['visit_ref'] ?? ''))) === $ref) return (int)$r['id']; } }
    foreach ($rows as $r) { $r = (array)$r;
      if ($ref !== '' && trim((string)($r['visit_ref'] ?? '')) !== '') continue;   // both carry different references: different visits
      if (!empty($d['arrived_at']) && $min($r['arrived_at'] ?? null) === $min($d['arrived_at'])) return (int)$r['id'];
      if (!empty($d['departed_at']) && $min($r['departed_at'] ?? null) === $min($d['departed_at'])) return (int)$r['id'];
      if (!empty($r['arrived_at']) && empty($r['departed_at']) && empty($d['arrived_at']) && !empty($d['departed_at']) && $d['departed_at'] >= $r['arrived_at']) return (int)$r['id'];
    }
    return null;
  }
  /** Merge an incoming entry into an existing visit: a field already confirmed is kept unless the entry gives a new value. */
  public static function merge($old, array $d): array {
    $old = (array)$old; $o = $d;
    foreach (['mmsi', 'vessel_type', 'visit_ref', 'berth', 'internal_note', 'arrived_at', 'arrival_source', 'arrival_ref', 'departed_at', 'departure_source', 'departure_ref'] as $k) if (($d[$k] ?? null) === null || $d[$k] === '') $o[$k] = $old[$k] ?? null;
    return $o;
  }
  public static function active($v): bool { $v = (array)$v; return !empty($v['arrived_at']) && empty($v['departed_at']); }
  /** Date filter (Bangladesh dates "Y-m-d") -> [startUtc, endUtc]. Default: the latest 24 hours. */
  public static function range(?string $from, ?string $to, string $now): array {
    $ok = fn($s) => is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4));
    $nowTs = strtotime($now . ' UTC');
    if (!$ok($from) && !$ok($to)) return [date('Y-m-d H:i:s', $nowTs - 86400), date('Y-m-d H:i:s', $nowTs)];
    if (!$ok($from)) $from = $to; if (!$ok($to)) $to = $from; if ($to < $from) { $x = $from; $from = $to; $to = $x; }
    $a = self::toUtc($from . 'T00:00'); $b = date('Y-m-d H:i:s', strtotime(self::toUtc($to . 'T23:59') . ' UTC') + 59);
    if (strtotime($b . ' UTC') - strtotime($a . ' UTC') > self::MAX_DAYS * 86400) $a = date('Y-m-d H:i:s', strtotime($b . ' UTC') - self::MAX_DAYS * 86400);
    return [$a, $b];
  }
  /** One card for the public list. $photo = ['url' => ..., 'credit' => ...] or null. Internal notes, evidence and staff names never leave the admin. */
  public static function item($v, string $type, ?array $photo): array {
    $v = (array)$v; $col = $type === 'departures' ? 'departed_at' : 'arrived_at';
    return ['id' => (int)$v['id'], 'event' => $type === 'departures' ? 'departure' : 'arrival', 'vessel' => (string)$v['vessel'], 'imo' => (string)$v['imo'], 'vessel_type' => $v['vessel_type'] ?? null,
      'port' => (string)$v['port'], 'port_name' => self::PORTS[$v['port']] ?? (string)$v['port'], 'location' => $v['berth'] ?? null, 'time' => self::iso($v[$col] ?? null),
      'in_port' => self::active($v), 'photo' => $photo ? ['url' => $photo['url'], 'credit' => $photo['credit']] : null];
  }
  /** Feed status for the public page; null while no feed is connected. */
  public static function feed(?array $cfg, string $now): ?array {
    if (!$cfg || empty($cfg['on'])) return null; $last = $cfg['last_ok'] ?? null;
    return ['name' => (string)($cfg['name'] ?? 'Feed'), 'last_ok' => self::iso($last), 'delayed' => !$last || strtotime($now . ' UTC') - strtotime($last . ' UTC') > self::FEED_LATE_MIN * 60];
  }
  public static function photoProblem(string $ext, ?string $mime, int $size): ?string {
    $ext = strtolower($ext); if (!isset(self::PHOTO_EXT[$ext]) || self::PHOTO_EXT[$ext] !== (string)$mime) return 'The photo must be a JPG, PNG or WEBP image.';
    if ($size <= 0 || $size > self::PHOTO_MAX) return 'The photo must be 5 MB or smaller.'; return null;
  }
  public static function sourceUrl($u): ?string { $u = trim((string)$u); return ($u !== '' && strlen($u) <= 400 && preg_match('#^https://[^\s"<>]+\.(jpe?g|png|webp)(\?[^\s"<>]*)?$#i', $u)) ? $u : null; }
}
