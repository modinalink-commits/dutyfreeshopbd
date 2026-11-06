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

    private static function lookup(string $q): ?array
    {
        $key = 'geo:'.md5(mb_strtolower($q));
        $cached = Cache::get($key);
        if ($cached !== null) return $cached ?: null;
        try {
            $res = Http::timeout(8)->withHeaders(['User-Agent' => 'NK-Corporation-ERP/1.0 (admin.nklpgbd.com)', 'Accept-Language' => 'en,bn'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $q, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'bd',
                    // Prefer results near the Mirsarai / Chattogram area without excluding the rest of the country.
                    'viewbox' => '91.20,23.10,91.95,22.30', 'bounded' => 0,
                ]);
            if (! $res->ok()) return null; // do not cache a failed call; try again next save
            $r = $res->json()[0] ?? null;
            $out = $r ? ['lat' => round((float) $r['lat'], 7), 'lng' => round((float) $r['lon'], 7), 'label' => (string) ($r['display_name'] ?? $q)] : [];
            Cache::put($key, $out, now()->addDays(30));
            return $out ?: null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
