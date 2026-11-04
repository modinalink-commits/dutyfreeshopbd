<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Area (district) wise order & customer data. Orders store a free-text district, so it is matched to the 64 districts (spelling variants + Bengali names); blank ones are read from the address text. */
class AreaOrders
{
  private static array $idx = [];

  /** canonical district => [aliases] (lowercase, no apostrophes) */
  private static function aliasMap(): array {
    $extra = [
      'Barishal' => ['barisal'], 'Bogura' => ['bogra'], 'Chattogram' => ['chittagong', 'chattagram', 'ctg'], 'Cumilla' => ['comilla'], 'Jashore' => ['jessore'],
      'Khagrachari' => ['khagrachhari', 'khagrachori'], 'Nawabganj' => ['chapai nawabganj', 'chapainawabganj', 'chapai', 'nawabganj'], 'Sirajgonj' => ['sirajganj', 'sirajgong'],
      'Maulvibazar' => ['moulvibazar', 'maulvibazar', 'moulavibazar'], 'Jhalokati' => ['jhalakathi', 'jhalokathi', 'jhalakati'], 'Netrokona' => ['netrakona'],
      'Narsingdi' => ['narsinghdi', 'narshingdi'], 'Munshiganj' => ['munshigonj'], "Cox's Bazar" => ['coxs bazar', 'coxsbazar', 'cox bazar', 'coxbazar'], 'Habiganj' => ['hobiganj', 'habigonj'],
      'Lakshmipur' => ['laxmipur', 'lakshipur'], 'Joypurhat' => ['jaipurhat', 'joypurhat'], 'Panchagarh' => ['panchagar'], 'Narayanganj' => ['narayangonj', 'narayangong'],
      'Kishoreganj' => ['kishorganj', 'kishoregonj'], 'Pirojpur' => ['perojpur'], 'Sylhet' => ['sylet'], 'Gopalganj' => ['gopalgonj'], 'Lalmonirhat' => ['lalmonirhat'],
      'Manikganj' => ['manikgonj'], 'Brahmanbaria' => ['brahmanbaria', 'brammanbaria'], 'Tangail' => ['tangail'], 'Mymensingh' => ['mymensing', 'momenshahi'],
    ];
    $m = [];
    foreach (self::geo() as $d) {
      $al = [self::norm($d['en']), self::norm($d['bn'])];
      foreach ($extra[$d['en']] ?? [] as $x) $al[] = self::norm($x);
      $m[$d['en']] = array_values(array_unique(array_filter($al)));
    }
    return $m;
  }

  public static function geo(): array {
    static $g = null;
    if ($g === null) { $p = public_path('bd64.json'); $g = is_file($p) ? (json_decode(file_get_contents($p), true)['d'] ?? []) : []; $g = array_map(fn($d) => ['id' => $d['id'], 'en' => $d['en'], 'bn' => $d['bn'], 'dv' => $d['dv'] ?? '', 'dvbn' => $d['dvbn'] ?? ''], $g); }
    return $g;
  }

  public static function norm(string $s): string {
    $s = mb_strtolower(trim($s)); $s = str_replace(["'", '’', '`'], '', $s);
    $s = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', $s);
    return trim((string)$s);
  }

  private static function index(): array {
    if (!self::$idx) { $ex = []; $all = []; foreach (self::aliasMap() as $en => $als) foreach ($als as $a) { $ex[$a] = $en; $all[] = [$a, $en]; } usort($all, fn($x, $y) => mb_strlen($y[0]) <=> mb_strlen($x[0])); self::$idx = ['ex' => $ex, 'all' => $all]; }
    return self::$idx;
  }

  /** district text (or address) -> canonical English name, or null */
  public static function match(string $raw, bool $loose = true): ?string {
    $n = self::norm($raw); if ($n === '') return null; $i = self::index();
    if (isset($i['ex'][$n])) return $i['ex'][$n];
    if (!$loose) return null;
    $pad = ' ' . $n . ' ';
    foreach ($i['all'] as [$a, $en]) { if (mb_strlen($a) >= 4 && str_contains($pad, ' ' . $a . ' ')) return $en; }
    return null;
  }

  /** all-districts summary. $days = 0 for all time. Cached. */
  public static function summary(int $days = 0): array {
    return Cache::remember('area.orders.v1.' . $days, 600, fn() => self::build($days));
  }

  private static function build(int $days): array {
    $q = DB::table('orders')->whereNotIn('status', ['Cancel', 'Incomplete'])->whereNotNull('phone');
    if ($days > 0) $q->where('created_at', '>=', now()->subDays($days));
    $rows = $q->selectRaw("coalesce(trim(district),'') as d, case when coalesce(trim(district),'')='' then left(coalesce(address,''),300) else '' end as a, phone, count(*) as o, coalesce(sum(total),0) as s, sum(case when status in ('Delivered','Partial Delivered') then 1 else 0 end) as dl, sum(case when status in ('Returned','Return Pending') then 1 else 0 end) as rt, max(created_at) as last_at")->groupByRaw("1,2,phone")->get();
    $by = []; $unk = ['orders' => 0, 'customers' => []];
    foreach ($rows as $r) {
      $en = self::match((string)$r->d) ?? self::match((string)$r->a);
      if (!$en) { $unk['orders'] += (int)$r->o; $unk['customers'][$r->phone] = 1; continue; }
      $b = &$by[$en]; $b ??= ['name' => $en, 'orders' => 0, 'sales' => 0.0, 'delivered' => 0, 'returned' => 0, 'ph' => [], 'last' => null];
      $b['orders'] += (int)$r->o; $b['sales'] += (float)$r->s; $b['delivered'] += (int)$r->dl; $b['returned'] += (int)$r->rt;
      $b['ph'][$r->phone] = ($b['ph'][$r->phone] ?? 0) + (int)$r->o; if (!$b['last'] || $r->last_at > $b['last']) $b['last'] = $r->last_at; unset($b);
    }
    $bn = []; foreach (self::geo() as $g) $bn[$g['en']] = [$g['bn'], $g['dv'], $g['dvbn']];
    $out = [];
    foreach ($by as $en => $b) {
      $cust = count($b['ph']); $rep = count(array_filter($b['ph'], fn($c) => $c > 1)); $fin = $b['delivered'] + $b['returned'];
      $out[] = ['name' => $en, 'bn' => $bn[$en][0] ?? $en, 'division' => $bn[$en][1] ?? '', 'orders' => $b['orders'], 'customers' => $cust, 'repeat' => $rep, 'sales' => round($b['sales']),
        'success' => $fin > 0 ? round($b['delivered'] / $fin * 100) : null, 'last' => $b['last']];
    }
    return ['rows' => $out, 'unknown' => ['orders' => $unk['orders'], 'customers' => count($unk['customers'])], 'generated' => now()->toIso8601String()];
  }

  public static function top(array $rows, string $by = 'customers', int $n = 10): array {
    $by = in_array($by, ['customers', 'orders', 'sales'], true) ? $by : 'customers';
    usort($rows, fn($a, $b) => [$b[$by], $b['orders']] <=> [$a[$by], $a['orders']]);
    return array_slice($rows, 0, $n);
  }

  /** customers (name, phone, address, orders, sales, last order) of one district, for campaign lists */
  public static function customers(string $district, int $days = 0): array {
    $q = DB::table('orders')->whereNotIn('status', ['Cancel', 'Incomplete'])->whereNotNull('phone');
    if ($days > 0) $q->where('created_at', '>=', now()->subDays($days));
    $rows = $q->selectRaw("phone, coalesce(trim(district),'') as d, left(coalesce(address,''),300) as a, customer_name as n, id, total, created_at")->orderByDesc('id')->get();
    $c = [];
    foreach ($rows as $r) {
      $en = self::match((string)$r->d) ?? self::match((string)$r->a); if ($en !== $district) continue;
      $x = &$c[$r->phone]; $x ??= ['name' => $r->n, 'phone' => $r->phone, 'address' => $r->a, 'orders' => 0, 'sales' => 0.0, 'last' => $r->created_at]; $x['orders']++; $x['sales'] += (float)$r->total; unset($x);
    }
    usort($c, fn($a, $b) => [$b['orders'], $b['sales']] <=> [$a['orders'], $a['sales']]);
    return $c;
  }
}
