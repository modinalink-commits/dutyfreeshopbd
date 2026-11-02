<?php
namespace App\Support;
use App\Http\Controllers\StoreController;
/** Single source of truth for DutyFreeBD contact details. Edit them once in Admin > Site settings > "Business contact". */
class Biz {
  const DEFAULTS = [
    'hotline' => '09613828081', 'mobile' => '01871600600', 'whatsapp' => '01872404040',
    'email' => 'info@dutyfreebd.com', 'address' => 'Al Sabka, Deira, Dubai, United Arab Emirates', 'business_hours' => '10:00 AM – 10:00 PM',
  ];
  /** setting key behind each field */
  const KEYS = ['hotline' => 'call_number', 'mobile' => 'contact_mobile', 'whatsapp' => 'whatsapp_number', 'email' => 'contact_email', 'address' => 'contact_address', 'business_hours' => 'business_hours'];
  public static function get(string $f): string {
    try { $v = trim((string)(StoreController::set()[self::KEYS[$f]] ?? '')); } catch (\Throwable $e) { $v = ''; }
    return $v !== '' ? $v : self::DEFAULTS[$f];
  }
  public static function hotline(): string { return self::get('hotline'); }
  public static function mobile(): string { return self::get('mobile'); }
  public static function whatsapp(): string { return self::get('whatsapp'); }
  public static function email(): string { return self::get('email'); }
  public static function address(): string { return preg_replace('/\s+/', ' ', self::get('address')); }
  public static function hours(): string { return self::get('business_hours'); }
  /** 01872404040 -> 8801872404040 (wa.me format) */
  public static function waIntl(?string $n = null): string { $d = preg_replace('/\D/', '', $n ?? self::whatsapp()); return str_starts_with($d, '880') ? $d : '88'.ltrim($d, '0'); }
  public static function waUrl(string $text = ''): string { return 'https://wa.me/'.self::waIntl().($text !== '' ? '?text='.rawurlencode($text) : ''); }
  /** 01871600600 -> +8801871600600 (tel: format) */
  public static function tel(?string $n): string { $d = preg_replace('/\D/', '', (string)$n); if ($d === '') return ''; return '+'.(str_starts_with($d, '880') ? $d : '880'.ltrim($d, '0')); }
  public static function telUrl(?string $n): string { $t = self::tel($n); return $t === '' ? '#' : 'tel:'.$t; }
  /** "10:00 AM – 10:00 PM" -> "10:00-22:00" for schema.org */
  public static function hoursSchema(): string {
    if (preg_match_all('/(\d{1,2})(?::(\d{2}))?\s*(AM|PM)/i', self::hours(), $m, PREG_SET_ORDER) && count($m) >= 2) {
      $f = function ($x) { $h = (int)$x[1] % 12; if (strtoupper($x[3]) === 'PM') $h += 12; return sprintf('%02d:%02d', $h, (int)($x[2] ?? 0)); };
      return $f($m[0]).'-'.$f($m[1]);
    }
    return '10:00-22:00';
  }
}
