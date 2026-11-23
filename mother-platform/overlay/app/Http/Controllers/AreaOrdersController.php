<?php
namespace App\Http\Controllers;

use App\Support\AreaOrders as AO;
use Illuminate\Http\Request;

class AreaOrdersController extends Controller
{
  private function days(Request $r): int { return in_array((int)$r->query('days'), [30, 90, 365], true) ? (int)$r->query('days') : 0; }

  public function index(Request $r) {
    $days = $this->days($r); $by = in_array($r->query('by'), ['customers', 'orders', 'sales'], true) ? $r->query('by') : 'customers';
    $s = AO::summary($days); $rows = $s['rows'];
    $have = array_column($rows, 'name'); foreach (AO::geo() as $g) if (!in_array($g['en'], $have, true)) $rows[] = ['name' => $g['en'], 'bn' => $g['bn'], 'division' => $g['dv'], 'orders' => 0, 'customers' => 0, 'repeat' => 0, 'sales' => 0, 'success' => null, 'last' => null];
    $sorted = AO::top($rows, $by, 64);
    $reached = count(array_filter($rows, fn($x) => $x['orders'] > 0));
    $tot = ['customers' => array_sum(array_column($rows, 'customers')), 'orders' => array_sum(array_column($rows, 'orders')), 'sales' => array_sum(array_column($rows, 'sales'))];
    return view('area_orders', ['rows' => $sorted, 'top' => array_slice($sorted, 0, 10), 'by' => $by, 'days' => $days, 'reached' => $reached, 'tot' => $tot, 'unknown' => $s['unknown'], 'generated' => $s['generated']]);
  }

  public function export(Request $r) {
    $days = $this->days($r); $rows = AO::top(AO::summary($days)['rows'], 'customers', 64);
    return $this->csv('area-wise-orders.csv', ['District', 'Division', 'Customers', 'Repeat customers', 'Orders', 'Sales (BDT)', 'Delivery success %', 'Last order'], array_map(fn($x) => [$x['name'], $x['division'], $x['customers'], $x['repeat'], $x['orders'], $x['sales'], $x['success'] ?? '', $x['last'] ?? ''], $rows));
  }

  /** customer list of one district (name, phone, address) for campaigns */
  public function customers(Request $r) {
    $d = (string)$r->query('district'); abort_unless(collect(AO::geo())->contains('en', $d), 404);
    $c = AO::customers($d, $this->days($r));
    return $this->csv('customers-' . preg_replace('/[^a-z0-9]+/i', '-', $d) . '.csv', ['Name', 'Phone', 'Address', 'Orders', 'Sales (BDT)', 'Last order'], array_map(fn($x) => [$x['name'], $x['phone'], $x['address'], $x['orders'], round($x['sales']), $x['last']], $c));
  }

  /** Read-only check (24 Nov 2026): web checkout saved the "inside city" label as the district. Compares it with the delivery zone that was priced from the address (Pathao city / zone). Numbers only, no customer data. */
  public function audit(Request $r) {
    $days = max(1, min(120, (int)$r->query('days', 30))); $from = now()->subDays($days);
    $city = "case when delivery_zone like 'Pathao · %' then split_part(substr(delivery_zone, 10), ' / ', 1) when delivery_zone is null or delivery_zone = '' then '(no zone saved)' else delivery_zone end";
    $wrong = "(district ilike 'Inside Chattogram%')"; $ctgAddr = "(address ~* '(chattogram|chittagong|chattagram|ctg|চট্টগ্রাম)')";
    $agg = "count(*) as n, count(*) filter (where status in ('Delivered','Partial Delivered')) as delivered, count(*) filter (where status in ('Returned','Return Pending')) as returned, count(*) filter (where status = 'Cancel') as cancelled, coalesce(sum(shipping) filter (where status <> 'Cancel'),0) as charged, coalesce(sum(delivery_original) filter (where status <> 'Cancel'),0) as list_charge, coalesce(sum(delivery_free_discount) filter (where status <> 'Cancel'),0) as waived, count(*) filter (where status <> 'Cancel' and delivery_free_discount > 0) as free_n, count(*) filter (where status <> 'Cancel' and delivery_manual_adj is not null) as manual_n, count(*) filter (where status <> 'Cancel' and delivery_free_discount = 0 and delivery_manual_adj is null and shipping < delivery_original) as under_n, coalesce(sum(delivery_original - shipping) filter (where status <> 'Cancel' and delivery_free_discount = 0 and delivery_manual_adj is null and shipping < delivery_original),0) as under_amt";
    $q = fn(string $sql) => \Illuminate\Support\Facades\DB::select($sql, [$from]);
    return response()->json([
      'days' => $days,
      'by_flow' => $q("select coalesce(source,'(none)') as source, $wrong as wrong_district, $agg from orders where created_at >= ? group by 1, 2 order by 3 desc"),
      'wrong_by_city' => $q("select $city as city, $agg from orders where created_at >= ? and $wrong group by 1 order by 2 desc limit 40"),
      'all_by_city' => $q("select $city as city, $agg from orders where created_at >= ? group by 1 order by 2 desc limit 40"),
      'dhaka_zones' => $q("select delivery_zone as zone, $agg from orders where created_at >= ? and delivery_zone like 'Pathao · Dhaka%' group by 1 order by 3 desc, 2 desc limit 40"),
      'wrong_priced_as_ctg' => $q("select $ctgAddr as address_says_ctg, (address ~* '(dhaka|ঢাকা)') as address_says_dhaka, $agg from orders where created_at >= ? and $wrong and delivery_zone like 'Pathao · Chittagong%' group by 1, 2"),
      'wrong_first_last' => $q("select min(created_at) as first_at, max(created_at) as last_at, count(*) as n from orders where created_at >= ? and $wrong"),
    ])->header('Cache-Control', 'no-store');
  }

  /** Read-only trial of the address reader on past orders. Nothing is saved or changed. Addresses are shown without name or phone. */
  public function addrSample(Request $r) {
    $days = max(1, min(60, (int)$r->query('days', 30))); $P = \App\Support\AddressParse::class;
    $rows = \Illuminate\Support\Facades\DB::table('orders')->where('created_at', '>=', now()->subDays($days))->orderByDesc('id')->limit(20000)->get(['id', 'address', 'district', 'delivery_zone', 'status', 'shipping', 'delivery_original']);
    $o = ['days' => $days, 'total' => 0, 'identified' => 0, 'with_area' => 0, 'need_check' => 0, 'reasons' => [], 'zone' => ['agree' => 0, 'differ' => 0, 'no_zone' => 0], 'district_field' => ['label' => 0, 'same' => 0, 'different' => 0, 'empty' => 0], 'charge_on_differ' => ['orders' => 0, 'shown' => 0.0, 'charged' => 0.0], 'priced_ctg_reader_other' => 0, 'priced_other_reader_ctg' => 0, 'pairs' => [], 'by_district' => [], 'check_words' => [], 'samples' => ['ok' => [], 'check' => [], 'differ' => []]];
    $cut = fn($s) => mb_substr(preg_replace('/\s+/u', ' ', (string)$s), 0, 80);
    foreach ($rows as $x) {
      $o['total']++; $p = $P::parse((string)$x->address);
      $zc = null; if (str_starts_with((string)$x->delivery_zone, 'Pathao · ')) $zc = \App\Support\AreaOrders::match(trim(explode(' / ', substr((string)$x->delivery_zone, strlen('Pathao · ')))[0]));
      if ($p['check']) {
        $o['need_check']++; $why = preg_replace('/^"[^"]*"/', '"…"', preg_replace('/:.*$/', '', $p['why'])); $o['reasons'][$why] = ($o['reasons'][$why] ?? 0) + 1;
        if (preg_match('/^"([^"]*)"/', $p['why'], $m)) $o['check_words'][$m[1]] = ($o['check_words'][$m[1]] ?? 0) + 1;
        if (count($o['samples']['check']) < 40 && $o['need_check'] % 7 === 0) $o['samples']['check'][] = [$cut($x->address), $p['why'], $zc];
        continue;
      }
      $o['identified']++; if ($p['area']) $o['with_area']++; $o['by_district'][$p['district']] = ($o['by_district'][$p['district']] ?? 0) + 1;
      if (count($o['samples']['ok']) < 25 && $o['identified'] % 40 === 0) $o['samples']['ok'][] = [$cut($x->address), $p['area'], $p['thana'], $p['district']];
      $d = trim((string)$x->district); $dc = $d === '' ? null : \App\Support\AreaOrders::match($d, false);
      if ($d === '') $o['district_field']['empty']++; elseif (stripos($d, 'Inside ') === 0 || stripos($d, 'Chattogram to ') === 0) $o['district_field']['label']++; elseif ($dc === $p['district']) $o['district_field']['same']++; else $o['district_field']['different']++;
      if ($zc === null) { $o['zone']['no_zone']++; continue; }
      if ($zc === $p['district']) { $o['zone']['agree']++; continue; }
      $o['zone']['differ']++; $k = $p['district'] . ' → priced as ' . $zc; $o['pairs'][$k] = ($o['pairs'][$k] ?? 0) + 1;
      if ($x->status !== 'Cancel') { $o['charge_on_differ']['orders']++; $o['charge_on_differ']['shown'] += (float)$x->delivery_original; $o['charge_on_differ']['charged'] += (float)$x->shipping; }
      if ($zc === 'Chattogram') $o['priced_ctg_reader_other']++; if ($p['district'] === 'Chattogram') $o['priced_other_reader_ctg']++;
      if (count($o['samples']['differ']) < 40) $o['samples']['differ'][] = [$cut($x->address), $p['district'], (string)$x->delivery_zone];
    }
    arsort($o['pairs']); arsort($o['by_district']); arsort($o['reasons']); arsort($o['check_words']); $o['pairs'] = array_slice($o['pairs'], 0, 30, true); $o['check_words'] = array_slice($o['check_words'], 0, 30, true);
    return response()->json($o, 200, [], JSON_UNESCAPED_UNICODE)->header('Cache-Control', 'no-store');
  }

  /** Read-only: Dhaka orders split by the area written in the address (numbers only). Dhaka = priced as Pathao Dhaka, or the address names Dhaka. */
  public function dhaka(Request $r) {
    $days = max(1, min(120, (int)$r->query('days', 30)));
    $areas = [ // [name, part, regex] — first match wins; outside-city places first so "Savar, Dhaka" is not counted as the city
      ['Savar', 'out', 'savar|সাভার|hemayetpur|হেমায়েতপুর'], ['Ashulia', 'out', 'ashulia|আশুলিয়া|baipail|zirabo|জিরাবো'], ['Keraniganj', 'out', 'keranigonj|keraniganj|কেরানীগঞ্জ|কেরানিগঞ্জ'], ['Dhamrai', 'out', 'dhamrai|ধামরাই'], ['Dohar', 'out', 'dohar|দোহার'], ['Nawabganj', 'out', 'nawabganj|nababganj|নবাবগঞ্জ'],
      ['Uttara', 'city', 'uttara|উত্তরা|uttarkhan|dakshinkhan|দক্ষিণখান|উত্তরখান|abdullahpur|azampur|আজমপুর'], ['Mirpur', 'city', 'mirpur|মিরপুর|pallabi|পল্লবী|kazipara|কাজীপাড়া|shewrapara|শেওড়াপাড়া|kafrul|কাফরুল|rupnagar|রূপনগর'], ['Mohammadpur', 'city', 'mohammadpur|mohammedpur|মোহাম্মদপুর|মোহাম্মাদপুর|adabor|আদাবর|shyamoli|shamoli|শ্যামলী|bosila|বসিলা'], ['Dhanmondi', 'city', 'dhanmondi|dhanmandi|ধানমন্ডি|ধানমণ্ডি|kalabagan|কলাবাগান|jigatola|zigatola|জিগাতলা|হাজারীবাগ|hazaribag|science lab|new market|নিউ মার্কেট|elephant road|এলিফ্যান্ট'],
      ['Gulshan', 'city', 'gulshan|গুলশান|niketan|নিকেতন'], ['Banani', 'city', 'banani|বনানী|mohakhali|মহাখালী|dohs'], ['Baridhara / Bashundhara', 'city', 'bashundhara|basundhara|বসুন্ধরা|baridhara|বারিধারা|nadda|নদ্দা|kuril|কুড়িল|vatara|bhatara|ভাটারা|notun bazar|নতুন বাজার'], ['Badda', 'city', 'badda|বাড্ডা|aftabnagar|aftab nagar|আফতাবনগর'], ['Rampura / Banasree', 'city', 'rampura|রামপুরা|banasree|banasri|বনশ্রী|malibag|মালিবাগ|mouchak|মৌচাক|hatirjheel|হাতিরঝিল'], ['Khilgaon / Bashabo', 'city', 'khilgaon|খিলগাঁও|bashabo|basabo|বাসাবো|goran|গোড়ান|mugda|মুগদা|sabujbag|সবুজবাগ|shahjahanpur|শাহজাহানপুর'],
      ['Tejgaon / Farmgate', 'city', 'tejgaon|তেজগাঁও|farmgate|ফার্মগেট|karwan|কারওয়ান|kawran|panthapath|পান্থপথ|monipuripara|nakhalpara|নাখালপাড়া'], ['Motijheel / Paltan', 'city', 'motijheel|motijhil|মতিঝিল|paltan|পল্টন|kakrail|কাকরাইল|shantinagar|শান্তিনগর|segunbagicha|সেগুনবাগিচা|ramna|রমনা|shahbag|শাহবাগ|moghbazar|mogbazar|মগবাজার|eskaton|ইস্কাটন|bailey|বেইলি|kamalapur|কমলাপুর|fakirapool|ফকিরাপুল'], ['Old Dhaka', 'city', 'lalbag|লালবাগ|chawk|চকবাজার|bangshal|বংশাল|sutrapur|সূত্রাপুর|wari|ওয়ারী|gendaria|গেন্ডারিয়া|kotwali|sadarghat|সদরঘাট|islampur|nawabpur|নবাবপুর|azimpur|আজিমপুর|puran dhaka|old dhaka|পুরান ঢাকা|armanitola|tikatuli|টিকাটুলি'], ['Jatrabari / Demra', 'city', 'jatrabari|যাত্রাবাড়ী|demra|ডেমরা|shonir akhra|sonir akhra|শনির আখড়া|kadamtali|কদমতলী|shyampur|শ্যামপুর|dania|দনিয়া|matuail|মাতুয়াইল|rayerbag|রায়েরবাগ|jurain|জুরাইন|postogola'], ['Cantonment / Airport', 'city', 'cantonment|ক্যান্টনমেন্ট|kurmitola|kochukhet|কচুক্ষেত|airport|বিমানবন্দর|khilkhet|খিলক্ষেত|nikunja|নিকুঞ্জ|ecb|manikdi'], ['Agargaon / Sher-e-Bangla', 'city', 'agargaon|আগারগাঁও|sher-e-bangla|sher e bangla|শেরেবাংলা|taltola|তালতলা|bijoy sarani'], ['Gabtoli / Aminbazar', 'city', 'gabtoli|গাবতলী|aminbazar|amin bazar|আমিনবাজার|kallyanpur|kalyanpur|কল্যাণপুর|darussalam|দারুস সালাম|technical'], ['Purbachal / 300 feet', 'city', 'purbachal|পূর্বাচল|300 feet|৩০০ ফিট'],
    ];
    $rows = \Illuminate\Support\Facades\DB::table('orders')->where('created_at', '>=', now()->subDays($days))->where(fn($q) => $q->where('delivery_zone', 'like', 'Pathao · Dhaka%')->orWhereRaw("address ~* '(dhaka|ঢাকা)'"))
      ->where(fn($q) => $q->whereNull('delivery_zone')->orWhere('delivery_zone', 'not like', 'Pathao · %')->orWhere('delivery_zone', 'like', 'Pathao · Dhaka%'))->get(['address', 'status', 'district']);
    $out = []; $tot = ['n' => 0, 'delivered' => 0, 'returned' => 0, 'cancelled' => 0, 'label_ctg' => 0];
    foreach ($rows as $o) {
      $a = mb_strtolower((string)$o->address); $key = 'Dhaka (area not written)'; $part = 'unknown';
      foreach ($areas as [$nm, $pt, $re]) if (preg_match('/(' . $re . ')/u', $a)) { $key = $nm; $part = $pt; break; }
      $b = &$out[$key]; $b ??= ['area' => $key, 'part' => $part, 'n' => 0, 'delivered' => 0, 'returned' => 0, 'cancelled' => 0, 'label_ctg' => 0];
      $st = (string)$o->status; $d = in_array($st, ['Delivered', 'Partial Delivered'], true); $rt = in_array($st, ['Returned', 'Return Pending'], true); $cn = $st === 'Cancel'; $lc = stripos((string)$o->district, 'Inside Chattogram') === 0;
      foreach ([['n', true], ['delivered', $d], ['returned', $rt], ['cancelled', $cn], ['label_ctg', $lc]] as [$k, $v]) if ($v) { $b[$k]++; $tot[$k]++; } unset($b);
    }
    usort($out, fn($x, $y) => $y['delivered'] <=> $x['delivered']);
    if ($r->query('view')) return view('dhaka_areas', ['days' => $days, 'total' => (int)($tot['n'] ?? 0), 'areas' => $out]);
    return response()->json(['days' => $days, 'total' => $tot, 'areas' => $out])->header('Cache-Control', 'no-store');
  }

  private function csv(string $name, array $head, array $rows) {
    $fh = fopen('php://temp', 'w+'); fwrite($fh, "\xEF\xBB\xBF"); fputcsv($fh, $head);
    foreach ($rows as $row) fputcsv($fh, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $row));
    rewind($fh); $out = stream_get_contents($fh); fclose($fh);
    return response($out, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="' . $name . '"']);
  }
}
