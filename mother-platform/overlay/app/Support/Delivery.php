<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** One delivery-charge engine for website orders and admin (manual) orders. All amounts come from Admin settings / zones / overrides. */
class Delivery
{
  const KEYS = ['delv_on', 'delv_default', 'delv_inside', 'delv_outside', 'delv_express', 'delv_free_on', 'delv_free_min', 'delv_inside_label', 'delv_outside_label', 'delv_express_label', 'delv_inside_names'];
  public static function cfg(): array {
    return Cache::remember('delv.cfg', 60, function () {
      $s = DB::table('settings')->where('key', 'like', 'delv\_%')->pluck('value', 'key')->all(); $g = fn($k, $d = '') => array_key_exists($k, $s) && $s[$k] !== null ? (string)$s[$k] : $d;
      return ['on' => $g('delv_on', '1') === '1', 'default' => (float)$g('delv_default', '130'), 'inside' => (float)$g('delv_inside', '70'), 'outside' => (float)$g('delv_outside', '130'), 'express' => (float)$g('delv_express', '0'),
        'free_on' => $g('delv_free_on', '0') === '1', 'free_min' => (float)$g('delv_free_min', '0'), 'l_in' => $g('delv_inside_label', 'Inside Chattogram'), 'l_out' => $g('delv_outside_label', 'Outside Chattogram'), 'l_ex' => $g('delv_express_label', 'Express / Same Day'),
        'names' => array_values(array_filter(array_map('trim', explode(',', $g('delv_inside_names', 'Chattogram,Chittagong'))))) ];
    });
  }
  public static function flush(): void { Cache::forget('delv.cfg'); Cache::forget('delv.zones'); Cache::forget('store.rules'); }
  public static function zones() {
    $rows = Cache::remember('delv.zones', 60, fn() => Schema::hasTable('delivery_zones') ? DB::table('delivery_zones')->where('active', true)->get()->map(fn($z) => (array)$z)->all() : []);
    return collect($rows)->map(fn($z) => (object)(array)$z);
  }
  /** checkout radio options: 1 inside, 2 outside, 3 express (only if an express charge is set). Shown charge is the base charge. */
  public static function options(): array {
    $c = self::cfg(); $o = [1 => ['n' => $c['l_in'], 'c' => $c['on'] ? $c['inside'] : 0], 2 => ['n' => $c['l_out'], 'c' => $c['on'] ? $c['outside'] : 0]];
    if ($c['express'] > 0 && $c['on']) $o[3] = ['n' => $c['l_ex'], 'c' => $c['express']];
    return $o;
  }
  private static function norm(string $s): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s))); }
  /** most specific active zone found in the address text: area > thana > district > division */
  public static function match(string $text): ?object {
    $t = self::norm($text); if ($t === '') return null; $rank = ['area' => 4, 'thana' => 3, 'district' => 2, 'division' => 1]; $best = null;
    foreach (self::zones() as $z) {
      $n = self::norm($z->name); if ($n === '' || mb_strpos($t, $n) === false) continue;
      if (!$best || ($rank[$z->level] ?? 0) > ($rank[$best->level] ?? 0) || (($rank[$z->level] ?? 0) === ($rank[$best->level] ?? 0) && mb_strlen($z->name) > mb_strlen($best->name))) $best = $z;
    }
    return $best;
  }
  public static function overrides(array $pids): array {
    if (!Schema::hasTable('delivery_overrides')) return [];
    $now = now(); $rows = DB::table('delivery_overrides')->where('active', true)->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))->get();
    if (!$rows->count()) return [];
    $cats = $pids && Schema::hasColumn('products', 'category_id') ? DB::table('products')->whereIn('id', $pids)->pluck('category_id')->filter()->all() : [];
    return $rows->filter(fn($r) => $r->kind === 'campaign' || ($r->kind === 'product' && in_array((int)$r->ref_id, array_map('intval', $pids), true)) || ($r->kind === 'category' && in_array((int)$r->ref_id, array_map('intval', $cats), true)))->values()->all();
  }

  /**
   * @param int $area checkout choice (1 inside, 2 outside, 3 express, 0 = detect from address only)
   * @return array zone, original (before free/override), free_discount, charge (final), free, label, unlocked, reason
   */
  public static function compute(float $sub, int $area, string $addr = '', string $district = '', array $pids = []): array {
    $c = self::cfg(); $text = trim($district . ' ' . $addr); $z = self::match($text);
    $r = ['zone' => null, 'original' => 0.0, 'free_discount' => 0.0, 'charge' => 0.0, 'free' => false, 'unlocked' => false, 'reason' => null, 'zone_id' => $z->id ?? null];
    if (!$c['on']) { $r['zone'] = 'Delivery charge off'; return $r; }
    $express = $area === 3 && $c['express'] > 0;
    if ($express) { $r['zone'] = $c['l_ex'] . ($z ? ' · ' . $z->name : ''); $orig = $z && $z->express_charge !== null ? (float)$z->express_charge : $c['express']; }
    elseif ($z) { $r['zone'] = $z->name . ' (' . $z->level . ')'; $orig = (float)$z->charge; }
    elseif ($area === 1) { $r['zone'] = $c['l_in']; $orig = $c['inside']; }
    elseif ($area === 2) { $r['zone'] = $c['l_out']; $orig = $c['outside']; }
    else {
      $in = false; foreach ($c['names'] as $nm) if ($nm !== '' && mb_strpos(self::norm($text), self::norm($nm)) !== false) { $in = true; break; }
      if ($in) { $r['zone'] = $c['l_in']; $orig = $c['inside']; } elseif ($text !== '') { $r['zone'] = $c['l_out']; $orig = $c['outside']; } else { $r['zone'] = 'Default delivery'; $orig = $c['default']; }
    }
    $r['original'] = $orig; $final = $orig; $ov = self::overrides($pids);
    $custom = null; foreach ($ov as $o) if ($o->mode === 'free') { $r['free'] = true; $r['reason'] = $o->label ?: ('Free delivery · ' . $o->kind); break; } elseif ($o->charge !== null) $custom = max((float)$custom, (float)$o->charge);
    if (!$r['free'] && $custom !== null) { $final = $custom; $r['reason'] = 'Custom delivery charge (' . $ov[0]->kind . ')'; }
    if (!$r['free'] && !$express && $c['free_on'] && $c['free_min'] > 0 && $sub >= $c['free_min']) { $r['free'] = true; $r['unlocked'] = true; $r['reason'] = 'Free delivery over ৳' . number_format($c['free_min']); }
    if ($r['free']) { $r['free_discount'] = $orig; $final = 0; }
    $r['charge'] = round(max(0, $final), 2); return $r;
  }
  /** columns stored permanently on the order */
  public static function cols(array $d, ?float $final = null): array {
    return ['delivery_zone' => mb_substr((string)$d['zone'], 0, 160), 'delivery_original' => $d['original'], 'delivery_free_discount' => $d['free_discount'], 'delivery_note' => $d['reason'] ? mb_substr($d['reason'], 0, 200) : null];
  }
}
