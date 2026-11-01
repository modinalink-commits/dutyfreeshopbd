<?php
namespace App\Support;

/** Finds the Pathao city (district) and zone from a free-text delivery address (English or Bangla). */
class PathaoLocator
{
  /** Pathao city_id => aliases (English variants + Bangla). Primary English name comes from Pathao's own list. */
  const ALIAS = [
    1 => ['dhaka', 'ঢাকা'], 2 => ['chittagong', 'chattogram', 'chittagram', 'ctg', 'চট্টগ্রাম', 'চাটগাঁ'], 3 => ['sylhet', 'সিলেট'], 4 => ['rajshahi', 'রাজশাহী'], 5 => ['cumilla', 'comilla', 'কুমিল্লা'], 6 => ['feni', 'ফেনী', 'ফেনি'],
    7 => ['noakhali', 'নোয়াখালী'], 8 => ['chandpur', 'চাঁদপুর', 'চাদপুর'], 9 => ['bogra', 'bogura', 'বগুড়া'], 10 => ['sirajganj', 'সিরাজগঞ্জ'], 11 => ["cox's bazar", 'coxs bazar', 'cox bazar', 'coxbazar', 'কক্সবাজার', "কক্স বাজার"],
    12 => ['moulvibazar', 'maulvibazar', 'moulvi bazar', 'মৌলভীবাজার'], 13 => ['tangail', 'টাঙ্গাইল'], 14 => ['natore', 'নাটোর'], 15 => ['chapainawabganj', 'chapai nawabganj', 'চাঁপাইনবাবগঞ্জ', 'চাপাইনবাবগঞ্জ'], 16 => ['manikganj', 'মানিকগঞ্জ'],
    17 => ['barisal', 'barishal', 'বরিশাল'], 18 => ['faridpur', 'ফরিদপুর'], 19 => ['jashore', 'jessore', 'যশোর'], 20 => ['khulna', 'খুলনা'], 21 => ['narayanganj', 'নারায়ণগঞ্জ'], 22 => ['gazipur', 'গাজীপুর'], 23 => ['munsiganj', 'munshiganj', 'মুন্সিগঞ্জ'],
    24 => ['pabna', 'পাবনা'], 25 => ['rangpur', 'রংপুর'], 26 => ['mymensingh', 'ময়মনসিংহ'], 27 => ['jhalokathi', 'jhalakathi', 'ঝালকাঠি'], 28 => ['kushtia', 'কুষ্টিয়া'], 29 => ['patuakhali', 'পটুয়াখালী'], 30 => ['habiganj', 'হবিগঞ্জ'],
    31 => ['pirojpur', 'পিরোজপুর'], 32 => ['brahmanbaria', 'b. baria', 'b.baria', 'ব্রাহ্মণবাড়িয়া', 'ব্রাহ্মণবাড়ীয়া'], 33 => ['sherpur', 'শেরপুর'], 34 => ['barguna', 'বরগুনা'], 35 => ['dinajpur', 'দিনাজপুর'], 36 => ['thakurgaon', 'ঠাকুরগাঁও'],
    37 => ['panchagarh', 'পঞ্চগড়'], 38 => ['gaibandha', 'গাইবান্ধা'], 39 => ['nilphamari', 'নীলফামারী'], 40 => ['lakshmipur', 'laxmipur', 'লক্ষ্মীপুর'], 41 => ['jamalpur', 'জামালপুর'], 42 => ['kishoreganj', 'কিশোরগঞ্জ'], 43 => ['madaripur', 'মাদারীপুর'],
    44 => ['netrakona', 'netrokona', 'নেত্রকোনা'], 45 => ['sunamganj', 'সুনামগঞ্জ'], 46 => ['naogaon', 'নওগাঁ'], 47 => ['narshingdi', 'narsingdi', 'নরসিংদী'], 48 => ['joypurhat', 'জয়পুরহাট'], 49 => ['jhenidah', 'jhenaidah', 'ঝিনাইদহ'],
    50 => ['meherpur', 'মেহেরপুর'], 51 => ['satkhira', 'সাতক্ষীরা'], 52 => ['bagerhat', 'বাগেরহাট'], 53 => ['bhola', 'ভোলা'], 54 => ['narail', 'নড়াইল'], 55 => ['kurigram', 'কুড়িগ্রাম'], 56 => ['gopalgonj', 'gopalganj', 'গোপালগঞ্জ'],
    57 => ['lalmonirhat', 'lalmanirhat', 'লালমনিরহাট'], 58 => ['rajbari', 'রাজবাড়ী'], 59 => ['rangamati', 'রাঙ্গামাটি', 'রাঙামাটি'], 60 => ['magura', 'মাগুরা'], 61 => ['chuadanga', 'চুয়াডাঙ্গা'], 62 => ['bandarban', 'বান্দরবান'],
    63 => ['khagrachari', 'khagrachhari', 'খাগড়াছড়ি'], 64 => ['shariatpur', 'শরীয়তপুর'],
  ];
  private static function n(string $s): string { return mb_strtolower(trim(preg_replace('/[\s,.\-()\/]+/u', ' ', $s))); }

  /** @return array{city:int,zone:int,city_name:string,zone_name:string,matched_zone:bool}|null */
  public static function find(string $text): ?array {
    $t = ' ' . self::n($text) . ' '; if (trim($t) === '') return null;
    $best = null; $pos = -1;
    foreach (self::ALIAS as $id => $al) foreach ($al as $a) {
      $a = self::n($a); $p = mb_strrpos($t, $a); if ($p === false) continue;
      if ($p > $pos || ($p === $pos && mb_strlen($a) > mb_strlen((string)($best[1] ?? '')))) { $pos = $p; $best = [$id, $a]; }
    }
    if (!$best) return null;
    $cid = $best[0]; $cname = $cid; foreach (Pathao::cities() as $c) if ((int)$c['city_id'] === $cid) $cname = trim($c['city_name']);
    $zones = Pathao::zones($cid); if (!$zones) return null;
    $zid = null; $zname = ''; $len = 0;
    foreach ($zones as $z) { $zn = self::n($z['zone_name']); if (mb_strlen($zn) >= 4 && mb_strpos($t, ' ' . $zn . ' ') !== false && mb_strlen($zn) > $len) { $len = mb_strlen($zn); $zid = (int)$z['zone_id']; $zname = trim($z['zone_name']); } }
    $matched = $zid !== null;
    if (!$matched) { $zid = (int)$zones[0]['zone_id']; $zname = trim($zones[0]['zone_name']); }
    return ['city' => $cid, 'zone' => $zid, 'city_name' => (string)$cname, 'zone_name' => $zname, 'matched_zone' => $matched];
  }
}
