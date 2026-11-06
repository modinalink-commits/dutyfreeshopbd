<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Turns a customer's address into a map point, server-side (OpenStreetMap Nominatim, no secret key).
 * Tries the full address first, then the area, so a shop at least lands in the right bazar/union.
 * Results are cached; nothing here ever overwrites a pin someone placed by hand.
 */
class Geo
{
    /** Region added when the address has no district, so "Bishworoad" means the one near our office. */
    private const REGION = 'Mirsarai, Chattogram, Bangladesh';

    /** @return array{lat: float, lng: float, level: string, label: string}|null */
    public static function find(?string $address, ?string $area): ?array
    {
        $address = trim((string) $address); $area = trim((string) $area);
        $tries = [];
        if ($address !== '') {
            $tries[] = [$address.($area && stripos($address, $area) === false ? ', '.$area : ''), 'address'];
            if (! preg_match('/chatt?o?gram|chittagong|dhaka|bangladesh/i', $address)) $tries[] = [$address.', '.self::REGION, 'address'];
        }
        if ($area !== '') {
            $tries[] = [$area.', '.self::REGION, 'area'];
            $tries[] = [$area.', Bangladesh', 'area'];
        }
        foreach ($tries as [$q, $level]) {
            $hit = self::lookup($q);
            if ($hit) return $hit + ['level' => $level];
        }
        return null;
    }

    /** Last problem talking to the map services (shown in the deploy log). */
    public static ?string $lastError = null;

    private static function lookup(string $q): ?array
    {
        $key = 'geo2:'.md5(mb_strtolower($q));
        $cached = Cache::get($key);
        if ($cached !== null) return $cached ?: null;
        $answered = false; $out = null;
        // OpenStreetMap Nominatim first, then Photon (also OpenStreetMap data) if Nominatim is unreachable.
        foreach (['nominatim', 'photon'] as $svc) {
            try {
                $http = Http::timeout(8)->withHeaders(['User-Agent' => 'NK-Corporation-ERP/1.0 (admin.nklpgbd.com; nkcorporation25@gmail.com)', 'Accept-Language' => 'en']);
                if ($svc === 'nominatim') {
                    $res = $http->get('https://nominatim.openstreetmap.org/search', ['q' => $q, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'bd',
                        'viewbox' => '91.20,23.10,91.95,22.30', 'bounded' => 0]);
                    if (! $res->ok()) { self::$lastError = "nominatim HTTP {$res->status()}"; continue; }
                    $answered = true;
                    $r = $res->json()[0] ?? null;
                    if ($r) { $out = ['lat' => round((float) $r['lat'], 7), 'lng' => round((float) $r['lon'], 7), 'label' => (string) ($r['display_name'] ?? $q)]; break; }
                } else {
                    $res = $http->get('https://photon.komoot.io/api/', ['q' => $q, 'limit' => 5, 'lat' => 22.77, 'lon' => 91.56]);
                    if (! $res->ok()) { self::$lastError = trim(self::$lastError.' · photon HTTP '.$res->status()); continue; }
                    $answered = true;
                    foreach (($res->json()['features'] ?? []) as $f) {
                        if (strtoupper((string) ($f['properties']['countrycode'] ?? '')) !== 'BD') continue;
                        [$lng, $lat] = $f['geometry']['coordinates'];
                        $p = $f['properties'];
                        $out = ['lat' => round((float) $lat, 7), 'lng' => round((float) $lng, 7), 'label' => implode(', ', array_filter([$p['name'] ?? null, $p['city'] ?? ($p['county'] ?? null), $p['state'] ?? null, 'Bangladesh']))];
                        break 2;
                    }
                }
            } catch (\Throwable $e) {
                self::$lastError = trim(self::$lastError.' · '.$svc.': '.mb_substr($e->getMessage(), 0, 160));
            }
        }
        if (! $answered) return null; // nothing reachable: do not cache, try again next time
        Cache::put($key, $out ?? [], now()->addDays(30));
        return $out;
    }
}
