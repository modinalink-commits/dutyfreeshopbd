<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Reads area / thana / city / district out of the ONE address box the customer fills in (owner rule, 24 Nov 2026).
 * The customer's own text is never changed. Nothing is guessed: when the district is not clearly written, the fields stay empty
 * and the order is flagged for a person to check (addr_check = 1). Bangla and English spellings are both read.
 */
class AddressParse
{
  /** Known areas: [area name, thana, city, district, safe] — safe = the word alone is enough (no other place in Bangladesh shares it). */
  const AREAS = [
    // Dhaka district, outside the city
    ['Savar', 'Savar', 'Savar', 'Dhaka', true, 'savar|সাভার|hemayetpur|হেমায়েতপুর'], ['Ashulia', 'Ashulia', 'Savar', 'Dhaka', true, 'ashulia|আশুলিয়া|baipail|বাইপাইল|zirabo|জিরাবো'],
    ['Keraniganj', 'Keraniganj', 'Keraniganj', 'Dhaka', true, 'keranigonj|keraniganj|কেরানীগঞ্জ|কেরানিগঞ্জ'], ['Dhamrai', 'Dhamrai', 'Dhamrai', 'Dhaka', true, 'dhamrai|ধামরাই'], ['Dohar', 'Dohar', 'Dohar', 'Dhaka', true, 'dohar|দোহার'],
    // Dhaka city
    ['Uttara', 'Uttara', 'Dhaka', 'Dhaka', true, 'uttara|উত্তরা|uttarkhan|dakshinkhan|দক্ষিণখান|উত্তরখান|abdullahpur|আব্দুল্লাহপুর'],
    ['Mirpur', 'Mirpur', 'Dhaka', 'Dhaka', false, 'mirpur|মিরপুর'], ['Mirpur', 'Pallabi', 'Dhaka', 'Dhaka', true, 'pallabi|পল্লবী|kazipara|কাজীপাড়া|shewrapara|শেওড়াপাড়া|kafrul|কাফরুল|rupnagar|রূপনগর'],
    ['Mohammadpur', 'Mohammadpur', 'Dhaka', 'Dhaka', false, 'mohammadpur|mohammedpur|মোহাম্মদপুর|মোহাম্মাদপুর'], ['Mohammadpur', 'Adabor', 'Dhaka', 'Dhaka', true, 'adabor|আদাবর|shyamoli|shamoli|শ্যামলী|bosila|বসিলা'],
    ['Dhanmondi', 'Dhanmondi', 'Dhaka', 'Dhaka', true, 'dhanmondi|dhanmandi|ধানমন্ডি|ধানমণ্ডি|kalabagan|কলাবাগান|jigatola|zigatola|জিগাতলা|hazaribag|হাজারীবাগ|elephant road|এলিফ্যান্ট রোড'],
    ['Gulshan', 'Gulshan', 'Dhaka', 'Dhaka', true, 'gulshan|গুলশান|niketan|নিকেতন'], ['Banani', 'Banani', 'Dhaka', 'Dhaka', true, 'banani|বনানী|mohakhali|মহাখালী'],
    ['Baridhara / Bashundhara', 'Vatara', 'Dhaka', 'Dhaka', true, 'bashundhara|basundhara|বসুন্ধরা|baridhara|বারিধারা|nadda|নদ্দা|kuril|কুড়িল|vatara|bhatara|ভাটারা'],
    ['Badda', 'Badda', 'Dhaka', 'Dhaka', true, 'badda|বাড্ডা|aftabnagar|aftab nagar|আফতাবনগর'], ['Rampura / Banasree', 'Rampura', 'Dhaka', 'Dhaka', true, 'rampura|রামপুরা|banasree|banasri|বনশ্রী|malibag|মালিবাগ|mouchak|মৌচাক|hatirjheel|হাতিরঝিল'],
    ['Khilgaon / Bashabo', 'Khilgaon', 'Dhaka', 'Dhaka', true, 'khilgaon|খিলগাঁও|bashabo|basabo|বাসাবো|mugda|মুগদা|sabujbag|সবুজবাগ|shahjahanpur|শাহজাহানপুর'],
    ['Tejgaon / Farmgate', 'Tejgaon', 'Dhaka', 'Dhaka', true, 'tejgaon|তেজগাঁও|farmgate|ফার্মগেট|karwan bazar|kawran bazar|কারওয়ান বাজার|panthapath|পান্থপথ|nakhalpara|নাখালপাড়া'],
    ['Motijheel / Paltan', 'Motijheel', 'Dhaka', 'Dhaka', true, 'motijheel|motijhil|মতিঝিল|paltan|পল্টন|kakrail|কাকরাইল|shantinagar|শান্তিনগর|segunbagicha|সেগুনবাগিচা|shahbag|শাহবাগ|moghbazar|mogbazar|মগবাজার|eskaton|ইস্কাটন|kamalapur|কমলাপুর|fakirapool|ফকিরাপুল'],
    ['Old Dhaka', 'Lalbagh', 'Dhaka', 'Dhaka', true, 'lalbag|লালবাগ|bangshal|বংশাল|sutrapur|সূত্রাপুর|gendaria|গেন্ডারিয়া|nawabpur|নবাবপুর|azimpur|আজিমপুর|puran dhaka|old dhaka|পুরান ঢাকা|armanitola|tikatuli|টিকাটুলি|wari|ওয়ারী'],
    ['Jatrabari / Demra', 'Jatrabari', 'Dhaka', 'Dhaka', true, 'jatrabari|যাত্রাবাড়ী|demra|ডেমরা|shonir akhra|sonir akhra|শনির আখড়া|kadamtali|কদমতলী|matuail|মাতুয়াইল|rayerbag|রায়েরবাগ|jurain|জুরাইন'],
    ['Cantonment / Airport', 'Cantonment', 'Dhaka', 'Dhaka', true, 'kurmitola|কুর্মিটোলা|kochukhet|কচুক্ষেত|khilkhet|খিলক্ষেত|nikunja|নিকুঞ্জ|dhaka cantonment|ঢাকা ক্যান্টনমেন্ট'],
    ['Agargaon / Sher-e-Bangla', 'Sher-e-Bangla Nagar', 'Dhaka', 'Dhaka', true, 'agargaon|আগারগাঁও|sher-e-bangla nagar|sher e bangla nagar|শেরেবাংলা নগর'],
    ['Gabtoli / Aminbazar', 'Darus Salam', 'Dhaka', 'Dhaka', true, 'gabtoli|গাবতলী|aminbazar|amin bazar|আমিনবাজার|kallyanpur|kalyanpur|কল্যাণপুর'],
    // Chattogram
    ['Agrabad', 'Double Mooring', 'Chattogram', 'Chattogram', true, 'agrabad|আগ্রাবাদ'], ['Halishahar', 'Halishahar', 'Chattogram', 'Chattogram', true, 'halishahar|হালিশহর'], ['Pahartali', 'Pahartali', 'Chattogram', 'Chattogram', true, 'pahartali|পাহাড়তলী'],
    ['Khulshi / GEC', 'Khulshi', 'Chattogram', 'Chattogram', true, 'khulshi|খুলশী|gec circle|gec more|জিইসি|nasirabad|নাসিরাবাদ'], ['Panchlaish', 'Panchlaish', 'Chattogram', 'Chattogram', true, 'panchlaish|পাঁচলাইশ|muradpur|মুরাদপুর|sholoshahar|ষোলশহর'],
    ['Chandgaon / Bahaddarhat', 'Chandgaon', 'Chattogram', 'Chattogram', true, 'chandgaon|চান্দগাঁও|bahaddarhat|বহদ্দারহাট'], ['Bayezid / Oxygen', 'Bayezid', 'Chattogram', 'Chattogram', true, 'bayezid|বায়েজিদ|oxygen more|অক্সিজেন'],
    ['Patenga / EPZ', 'Patenga', 'Chattogram', 'Chattogram', true, 'patenga|potenga|পতেঙ্গা|cepz'], ['Bakalia', 'Bakalia', 'Chattogram', 'Chattogram', true, 'bakalia|বাকলিয়া'], ['Reazuddin Bazar', 'Kotwali', 'Chattogram', 'Chattogram', true, 'reazuddin|riazuddin|রিয়াজউদ্দিন'],
    ['Hathazari', 'Hathazari', 'Hathazari', 'Chattogram', true, 'hathazari|hathajari|হাটহাজারী'], ['Mirsarai', 'Mirsarai', 'Mirsarai', 'Chattogram', true, 'mirsarai|mirsharai|মীরসরাই|মিরসরাই'], ['Sitakunda', 'Sitakunda', 'Sitakunda', 'Chattogram', true, 'sitakunda|sitakundo|সীতাকুণ্ড|সীতাকুন্ড'],
    ['Patiya', 'Patiya', 'Patiya', 'Chattogram', true, 'patiya|পটিয়া'], ['Raozan', 'Raozan', 'Raozan', 'Chattogram', true, 'raozan|raujan|রাউজান'], ['Fatikchhari', 'Fatikchhari', 'Fatikchhari', 'Chattogram', true, 'fatikchhari|fatikchari|ফটিকছড়ি'],
    ['Anwara', 'Anwara', 'Anwara', 'Chattogram', true, 'anwara|আনোয়ারা'], ['Boalkhali', 'Boalkhali', 'Boalkhali', 'Chattogram', true, 'boalkhali|বোয়ালখালী'], ['Satkania', 'Satkania', 'Satkania', 'Chattogram', true, 'satkania|সাতকানিয়া'], ['Rangunia', 'Rangunia', 'Rangunia', 'Chattogram', true, 'rangunia|রাঙ্গুনিয়া'],
  ];
  /** Districts people write together with "Dhaka" meaning the division or the nearest big city: the named district wins. */
  const WITH_DHAKA = ['Narayanganj', 'Gazipur', 'Munshiganj', 'Manikganj', 'Narsingdi', 'Tangail', 'Faridpur', 'Kishoreganj', 'Gopalganj', 'Madaripur', 'Rajbari', 'Shariatpur'];

  /** Words that are also a person's name, a bank, a bus company or a place in another district: alone they never decide the district. */
  const UNSAFE = ['uttara', 'উত্তরা', 'mouchak', 'মৌচাক', 'kadamtali', 'কদমতলী', 'nasirabad', 'নাসিরাবাদ', 'lalbag', 'লালবাগ', 'anwara', 'আনোয়ারা', 'shyamoli', 'shamoli', 'শ্যামলী', 'wari', 'ওয়ারী', 'rampura', 'রামপুরা',
    'kallyanpur', 'kalyanpur', 'কল্যাণপুর', 'kazipara', 'কাজীপাড়া', 'kalabagan', 'কলাবাগান', 'sabujbag', 'সবুজবাগ', 'shahjahanpur', 'শাহজাহানপুর', 'shantinagar', 'শান্তিনগর', 'nawabpur', 'নবাবপুর', 'gabtoli', 'গাবতলী',
    'hemayetpur', 'হেমায়েতপুর', 'bayezid', 'বায়েজিদ', 'muradpur', 'মুরাদপুর', 'reazuddin', 'riazuddin', 'রিয়াজউদ্দিন', 'pallabi', 'পল্লবী', 'rupnagar', 'রূপনগর', 'badda', 'বাড্ডা', 'bashundhara', 'basundhara', 'বসুন্ধরা', 'pahartali', 'পাহাড়তলী', 'kafrul', 'কাফরুল', 'azimpur', 'আজিমপুর', 'patiya', 'পটিয়া', 'gulshan', 'গুলশান', 'niketan', 'নিকেতন', 'nikunja', 'নিকুঞ্জ', 'kuril', 'কুড়িল'];

  /** Folds Bangla spelling variants so that খিলগাও = খিলগাঁও, কাজিপাড়া = কাজীপাড়া, and both ways of typing ড় / য় match. */
  public static function fold(string $s): string {
    return str_replace(["\u{09A1}\u{09BC}", "\u{09A2}\u{09BC}", "\u{09AF}\u{09BC}", "\u{0981}", "\u{09C0}", "\u{09C2}", "\u{09A3}", "\u{200C}", "\u{200D}"], ["\u{09DC}", "\u{09DD}", "\u{09DF}", '', "\u{09BF}", "\u{09C1}", "\u{09A8}", '', ''], $s);
  }

  public static function division(string $district): ?string { foreach (AreaOrders::geo() as $g) if ($g['en'] === $district) return $g['dv'] ?: null; return null; }

  /** Every district named in the text, in the order written. */
  public static function districts(string $addr): array {
    $n = ' ' . self::fold(AreaOrders::norm($addr)) . ' '; $hit = [];
    static $idx = null;
    if ($idx === null) { $idx = []; foreach (AreaOrders::geo() as $g) { $idx[] = [AreaOrders::norm($g['en']), $g['en']]; $idx[] = [AreaOrders::norm($g['bn']), $g['en']]; }
      foreach (PathaoLocator::ALIAS as $id => $al) { $canon = AreaOrders::match($al[0], false) ?? AreaOrders::match($al[0]); if ($canon) foreach ($al as $a) $idx[] = [AreaOrders::norm($a), $canon]; }
      foreach (['barisal' => 'Barishal', 'bogra' => 'Bogura', 'comilla' => 'Cumilla', 'jessore' => 'Jashore', 'chittagong' => 'Chattogram', 'ctg' => 'Chattogram', 'narayangonj' => 'Narayanganj', 'coxs bazar' => "Cox's Bazar", 'cox bazar' => "Cox's Bazar", 'coxbazar' => "Cox's Bazar", 'munshigonj' => 'Munshiganj', 'manikgonj' => 'Manikganj'] as $a => $en) $idx[] = [$a, $en];
      foreach (['chapainawabganj', 'chapai nawabganj', 'chapainababganj', 'chapai', 'চাঁপাইনবাবগঞ্জ', 'চাঁপাই নবাবগঞ্জ', 'চাঁপাই'] as $a) $idx[] = [AreaOrders::norm($a), 'Nawabganj'];
      // the bare word Nawabganj is also an upazila of Dhaka and of Dinajpur: alone it never decides a district
      $idx = array_values(array_filter(array_map(fn($x) => [self::fold($x[0]), $x[1]], $idx), fn($x) => mb_strlen($x[0]) >= 3 && !in_array($x[0], ['nawabganj', 'nababganj', self::fold('নবাবগঞ্জ')], true))); }
    foreach ($idx as [$a, $en]) { $p = mb_strpos($n, ' ' . $a . ' '); if ($p !== false && (!isset($hit[$en]) || $p < $hit[$en])) $hit[$en] = $p; }
    asort($hit); return array_keys($hit);
  }

  /** @return array{area:?string,thana:?string,city:?string,district:?string,division:?string,check:int,why:string} */
  public static function parse(string $addr): array {
    $out = ['area' => null, 'thana' => null, 'city' => null, 'district' => null, 'division' => null, 'check' => 1, 'why' => ''];
    $low = ' ' . self::fold(mb_strtolower(preg_replace('/[\s,.\-()\/:;|]+/u', ' ', $addr))) . ' ';
    if (mb_strlen(trim($low)) < 6) { $out['why'] = 'Address too short'; return $out; }
    $ds = self::districts($addr); $area = null;
    static $unsafe = null; $unsafe ??= array_map([self::class, 'fold'], self::UNSAFE);
    foreach (self::AREAS as $a) if (preg_match('/(?<![\p{L}\p{M}])(' . self::fold($a[5]) . ')(?![a-z])/u', $low, $mm)) { $area = $a; $area[6] = $mm[1]; if (in_array($mm[1], $unsafe, true)) $area[4] = false; break; }
    // "Narayanganj, Dhaka" style: the nearer district is the real one
    if (count($ds) === 2 && in_array('Dhaka', $ds, true)) { $other = $ds[0] === 'Dhaka' ? $ds[1] : $ds[0]; if (in_array($other, self::WITH_DHAKA, true) && !($area && $area[3] === 'Dhaka')) $ds = [$other]; }
    if (count($ds) > 1) { $out['why'] = 'More than one district is written: ' . implode(', ', array_slice($ds, 0, 3)); return $out; }
    $district = $ds[0] ?? null;
    if ($area) {
      if ($district === null) { if ($area[4]) $district = $area[3]; else { $out['why'] = '"' . ($area[6] ?? $area[0]) . '" is written but not the district (the same word is used elsewhere too)'; $out['maybe'] = ['area' => $area[0], 'thana' => $area[1], 'city' => $area[2], 'district' => $area[3]]; return $out; } }
      if ($district === $area[3]) { $out['area'] = $area[0]; $out['thana'] = $area[1]; $out['city'] = $area[2]; }
    }
    if ($district === null) { $out['why'] = 'No district or known area is written'; return $out; }
    $out['district'] = $district; $out['division'] = self::division($district); $out['city'] ??= $district; $out['check'] = 0; return $out;
  }

  /** Orders placed before this moment (UTC) are old records: they are never touched without the owner's approval. */
  const SINCE = '2026-11-23 22:56:00';
  public static function isNew(object $o): bool { try { return isset($o->created_at) && \Carbon\Carbon::parse($o->created_at, 'UTC')->gte(\Carbon\Carbon::parse(self::SINCE, 'UTC')); } catch (\Throwable $e) { return false; } }
  /** New orders that were not read yet (admin, AI chat, call centre): a few per run. */
  public static function sweep(): void {
    if (!\Illuminate\Support\Facades\Schema::hasColumn('orders', 'addr_prev_district')) return;
    try { self::backfill(); } catch (\Throwable $e) {}
    foreach (DB::table('orders')->whereNull('addr_check')->where('created_at', '>=', self::SINCE)->orderBy('id')->limit(40)->pluck('id') as $id) self::stamp((int)$id);
  }

  /** Old orders (owner approved 24 Nov 2026): newest first, a batch per worker run, each order visited once. Only the new address fields are filled, and a district that is empty or a price label; a district typed by staff, the address text and every charge stay as they are. */
  public static function backfill(int $batch = 400): void {
    $C = \Illuminate\Support\Facades\Cache::class; $cur = $C::get('addr.backfill.cursor'); if ($cur === 'done') return;
    $q = DB::table('orders')->where('created_at', '<', self::SINCE)->orderByDesc('id')->limit($batch); if (is_numeric($cur)) $q->where('id', '<', (int)$cur);
    $ids = $q->pluck('id')->all(); if (!$ids) { $C::forever('addr.backfill.cursor', 'done'); return; }
    foreach ($ids as $id) self::stamp((int)$id, true, true);
    $C::forever('addr.backfill.cursor', (int)min($ids));
  }

  /** Save the parsed fields on one order. The "inside city" label that the checkout used to save as district is replaced only by a clearly read district, else emptied. */
  public static function stamp(int $orderId, bool $fixDistrict = true, bool $old = false): void {
    try {
      $o = DB::table('orders')->where('id', $orderId)->first(['id', 'address', 'district', 'delivery_zone', 'created_at']); if (!$o || (!$old && !self::isNew($o))) return; // old records only through the approved backfill
      $p = self::parse((string)$o->address);
      // Owner rule (24 Nov 2026): an area word that is used elsewhere too is accepted when the courier's own price zone names the same district.
      if ($p['check'] && !empty($p['maybe']) && str_starts_with((string)$o->delivery_zone, 'Pathao · ')) {
        $zc = AreaOrders::match(trim(explode(' / ', substr((string)$o->delivery_zone, strlen('Pathao · ')))[0]));
        if ($zc !== null && $zc === $p['maybe']['district']) $p = ['area' => $p['maybe']['area'], 'thana' => $p['maybe']['thana'], 'city' => $p['maybe']['city'], 'district' => $zc, 'division' => self::division($zc), 'check' => 0, 'why' => 'Area word and courier zone agree'];
      }
      $u = ['addr_area' => $p['area'], 'addr_thana' => $p['thana'], 'addr_city' => $p['city'], 'addr_district' => $p['district'], 'addr_division' => $p['division'], 'addr_check' => $p['check'], 'addr_note' => $p['why'] !== '' ? mb_substr($p['why'], 0, 160) : null];
      if ($fixDistrict && (trim((string)$o->district) === '' || stripos((string)$o->district, 'Inside ') === 0 || stripos((string)$o->district, 'Chattogram to ') === 0)) { if ($p['district'] !== null || !$old) { $u['district'] = $p['district']; if (trim((string)$o->district) !== '') $u['addr_prev_district'] = mb_substr((string)$o->district, 0, 120); } } // on old orders an unread address keeps its old value
      DB::table('orders')->where('id', $orderId)->update($u);
    } catch (\Throwable $e) { /* never blocks an order */ }
  }
}
