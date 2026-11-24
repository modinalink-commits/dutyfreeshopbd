<?php
namespace App\Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Where visitors come from and where they stop (owner 24 Nov 2026). Only browsers that ran our script are counted, so crawlers that never run scripts drop out by themselves.
 *  The source is decided ONCE, on the first page of the visit (later pages have our own site as referrer). Steps: 0 looked, 1 product page, 2 added to cart, 3 checkout, 4 order placed. */
class VisitTrack {
  const STEPS = [0 => 'Only looked', 1 => 'Saw a product', 2 => 'Added to cart', 3 => 'Opened checkout', 4 => 'Ordered'];
  public static function ready(): bool { static $r = false; if (!$r) { try { $r = \Illuminate\Support\Facades\Schema::hasTable('visit_sessions'); } catch (\Throwable $e) {} } return $r; }
  /** [source, campaign] from the landing page's referrer and click tags. */
  public static function classify(string $ref, array $q, string $me): array {
    $us = strtolower(trim((string)($q['us'] ?? ''))); $um = strtolower(trim((string)($q['um'] ?? ''))); $uc = mb_substr(trim((string)($q['uc'] ?? '')), 0, 80) ?: null;
    $h = strtolower((string)(parse_url($ref, PHP_URL_HOST) ?: '')); if ($h === '' && str_starts_with($ref, 'android-app://')) $h = strtolower(substr($ref, 14));
    $n = fn($x) => preg_replace('/^www\./', '', (string)$x);
    if (!empty($q['g']) || ($us === 'google' && in_array($um, ['cpc', 'paid', 'ppc'], true))) return ['Google Ads', $uc];
    if (!empty($q['tt']) || $us === 'tiktok') return ['TikTok', $uc];
    if (in_array($us, ['sms', 'winback', 'reorder'], true) || $um === 'sms') return ['SMS', $uc];
    if (in_array($us, ['whatsapp', 'wa'], true)) return ['WhatsApp', $uc];
    if (in_array($us, ['email', 'brevo', 'newsletter'], true) || $um === 'email') return ['Email', $uc];
    $meta = (bool)preg_match('/(^|\.)(facebook\.com|fb\.com|fb\.me|instagram\.com|messenger\.com)$|facebook\.katana|facebook\.orca|instagram\.android/', $h);
    if (in_array($us, ['meta', 'facebook', 'fb', 'instagram', 'ig'], true) && in_array($um, ['paid', 'cpc', 'ads', 'paid_social'], true)) return ['Meta Ads (tagged)', $uc];
    if (!empty($q['f']) || $meta || in_array($us, ['meta', 'facebook', 'fb', 'instagram', 'ig'], true)) return ['Facebook / Instagram', $uc];
    if (preg_match('/(^|\.)google\.[a-z.]+$|googlequicksearchbox|google\.android/', $h)) return ['Google search', $uc];
    if (preg_match('/(^|\.)(bing\.com|yahoo\.com|duckduckgo\.com|yandex\.[a-z]+|baidu\.com)$/', $h)) return ['Other search', $uc];
    if (preg_match('/whatsapp|wa\.me/', $h)) return ['WhatsApp', $uc];
    if (preg_match('/(^|\.)(youtube\.com|youtu\.be)$/', $h)) return ['YouTube', $uc];
    if (preg_match('/chatgpt|openai|perplexity|claude\.ai|gemini\.google|copilot/', $h)) return ['AI assistants', $uc];
    if ($h === '' ) return [$us !== '' ? 'Tagged link' : 'Direct / unknown', $uc];
    if ($n($h) === $n($me) || preg_match('/dutyfree(shop)?bd\.(com|shop|store)$|dutyfreedubai\.net$/', $h)) return ['Our own sites', $uc];
    return ['Other websites', $uc];
  }
  public static function beacon(Request $r): void {
    try {
      if (!self::ready()) return; $ua = (string)$r->userAgent(); if (ProductTrack::isBot($ua)) return;
      $in = json_decode((string)$r->getContent(), true); if (!is_array($in)) return;
      $sid = (string)($in['s'] ?? ''); if (!preg_match('/^[a-z0-9]{10,24}$/', $sid)) return;
      $t = (string)($in['t'] ?? ''); $st = max(0, min(4, (int)($in['st'] ?? 0))); $oos = !empty($in['oos']);
      if ($t === 'land') {
        [$src, $camp] = self::classify(mb_substr((string)($in['ref'] ?? ''), 0, 500), (array)($in['q'] ?? []), (string)$r->getHost());
        $pt = (string)($in['pt'] ?? ''); if (!in_array($pt, ['home', 'product', 'list', 'checkout', 'done', 'search', 'blog', 'missing'], true)) $pt = 'list';
        $ref = (string)($in['ref'] ?? ''); $host = strtolower((string)(parse_url($ref, PHP_URL_HOST) ?: (str_starts_with($ref, 'android-app://') ? substr($ref, 14) : '')));
        $vid = preg_replace('/[^a-z0-9]/i', '', (string)($in['v'] ?? '')); $cc = strtoupper(substr((string)$r->headers->get('CF-IPCountry', ''), 0, 2));
        $ms = (int)($in['ms'] ?? 0);
        DB::table('visit_sessions')->insertOrIgnore(['day' => now('Asia/Dhaka')->toDateString(), 'sid' => $sid, 'vid' => $vid !== '' ? substr($vid, 0, 40) : null, 'source' => $src, 'campaign' => $camp, 'ref_host' => $host !== '' ? mb_substr($host, 0, 120) : null,
          'land_type' => $pt, 'land_path' => mb_substr((string)parse_url((string)($in['path'] ?? '/'), PHP_URL_PATH), 0, 160), 'device' => ProductTrack::device($ua), 'cc' => preg_match('/^[A-Z]{2}$/', $cc) ? $cc : null,
          'pages' => 1, 'step' => $st, 'missing' => $pt === 'missing', 'oos' => $oos, 'load_ms' => $ms > 0 && $ms < 120000 ? $ms : null, 'created_at' => now(), 'updated_at' => now()]);
        if (Cache::add('vt.prune.' . date('Ymd'), 1, 90000)) DB::table('visit_sessions')->where('day', '<', now()->subDays(60)->toDateString())->delete();
        return;
      }
      $set = ['updated_at' => now()];
      if ($t === 'pg') { $set['pages'] = DB::raw('least(pages + 1, 60000)'); $set['step'] = DB::raw('greatest(step, ' . $st . ')'); if ($oos) $set['oos'] = true; if (($in['pt'] ?? '') === 'missing') $set['missing'] = true; }
      elseif ($t === 'st') $set['step'] = DB::raw('greatest(step, ' . $st . ')');
      elseif ($t === 'bye') { $s = max(0, min(86400, (int)($in['secs'] ?? 0))); $set['secs'] = DB::raw('greatest(secs, ' . $s . ')'); }
      elseif ($t === 'err') $set['err'] = mb_substr(trim((string)($in['err'] ?? '')), 0, 120) ?: null;
      else return;
      DB::table('visit_sessions')->where('sid', $sid)->where('created_at', '>', now()->subDay())->update($set);
    } catch (\Throwable $e) {}
  }
  /** Everything the Sales Funnel page shows about visits between two Dhaka dates. */
  public static function report(string $from, string $to): array {
    if (!self::ready()) return [];
    try {
      $b = [$from, $to];
      $src = DB::select("select source, count(*) n, count(*) filter (where pages = 1 and secs < 10) bounce, count(*) filter (where step >= 1) s1, count(*) filter (where step >= 2) s2, count(*) filter (where step >= 3) s3, count(*) filter (where step >= 4) s4,
        round(avg(nullif(secs, 0))) secs, round(avg(load_ms) filter (where load_ms is not null)) ms, count(*) filter (where device = 'mobile') mob from visit_sessions where day between ? and ? group by source order by n desc", $b);
      $tot = DB::selectOne("select count(*) n, count(distinct vid) people, count(*) filter (where pages = 1 and secs < 10) bounce, count(*) filter (where missing) missing, count(*) filter (where oos) oos, count(*) filter (where err is not null) err,
        count(*) filter (where load_ms > 4000) slow, count(*) filter (where load_ms is not null) timed, count(*) filter (where cc is not null and cc <> 'BD') abroad, count(*) filter (where step >= 3 and step < 4) co_left from visit_sessions where day between ? and ?", $b);
      $stop = DB::select("select step, count(*) n from visit_sessions where day between ? and ? group by step order by step", $b);
      $land = DB::select("select land_type, count(*) n, count(*) filter (where pages = 1 and secs < 10) bounce, count(*) filter (where step >= 4) ord from visit_sessions where day between ? and ? group by land_type order by n desc", $b);
      $errs = DB::select("select err, count(*) n from visit_sessions where day between ? and ? and err is not null group by err order by n desc limit 8", $b);
      $refs = DB::select("select ref_host, count(*) n from visit_sessions where day between ? and ? and source = 'Other websites' group by ref_host order by n desc limit 8", $b);
      $miss = DB::select("select land_path, count(*) n from visit_sessions where day between ? and ? and land_type = 'missing' group by land_path order by n desc limit 8", $b);
      $today = now('Asia/Dhaka'); $srv = ($to >= $today->toDateString() && $from <= $today->toDateString()) ? (int)Cache::get('lb.vdc.' . $today->format('Ymd'), 0) : null;
      $js = $srv !== null ? (int)DB::table('visit_sessions')->where('day', $today->toDateString())->count() : null;
      $first = DB::table('visit_sessions')->min('created_at');
      return ['src' => $src, 'tot' => $tot, 'stop' => $stop, 'land' => $land, 'errs' => $errs, 'refs' => $refs, 'miss' => $miss, 'srv' => $srv, 'js' => $js, 'first' => $first];
    } catch (\Throwable $e) { report($e); return []; }
  }
}
