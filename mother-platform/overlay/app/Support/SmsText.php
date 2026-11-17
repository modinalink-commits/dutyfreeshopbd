<?php
namespace App\Support;

/**
 * Owner 17 Nov 2026: every automatic SMS is short and written in Bangla (BTRC: A2P SMS in Bangla).
 * The brand name, order number, OTP, phone numbers and links stay as they are. One place for the texts
 * that used to be written inline, so the sender and the template screen (/admin/sms/templates) use the same code.
 */
class SmsText {
  const BRAND = 'DutyFreeBD';
  public static function tk($n): string { $n = (float)$n; return '৳' . number_format($n, $n == floor($n) ? 0 : 2); }
  /** Short tracking link for SMS: dutyfreebd.com/t/<invoice>/<key> (redirects to the tracking page). */
  public static function track($inv): string { $i = Inv::show($inv); return preg_replace('#^https?://#', '', url('/t/' . rawurlencode($i) . '/' . Inv::trackKey($i))); }
  public static function help(): string { return 'সহায়তা: ' . Biz::hotline(); }
  private static function pay(?string $s): string {
    $s = trim((string)$s); if ($s === '') return 'ক্যাশ অন ডেলিভারি';
    $m = ['cash on delivery' => 'ক্যাশ অন ডেলিভারি', 'paid' => 'পরিশোধিত', 'partial paid' => 'আংশিক পরিশোধ', 'partially paid' => 'আংশিক পরিশোধ', 'verification pending' => 'পেমেন্ট যাচাই চলছে', 'unpaid' => 'ক্যাশ অন ডেলিভারি'];
    return $m[strtolower($s)] ?? '';
  }

  public static function orderPlaced(object $o): string {
    $disc = (float)($o->discount ?? 0);
    return self::BRAND . ' অর্ডার ' . Inv::show($o->invoice) . ' পেয়েছি। মোট ' . self::tk($o->total) . ($disc > 0 ? ', ছাড় ' . self::tk($disc) : '') . '।'
      . ((string)($o->pay_status ?? '') === 'Verification Pending' ? ' পেমেন্ট যাচাই চলছে।' : '')
      . ' কল করে নিশ্চিত করব। ' . self::track($o->invoice) . ' ' . self::help();
  }
  public static function orderApproved(object $o): string {
    $p = self::pay($o->pay_status ?? '');
    return self::BRAND . ' আপনার অর্ডার ' . Inv::show($o->invoice) . ' নিশ্চিত হয়েছে। মোট ' . self::tk($o->total) . ($p !== '' ? ' (' . $p . ')' : '') . '। ট্র্যাক: ' . self::track($o->invoice);
  }
  const CANCEL_WHY = [
    'it was cancelled at your request' => 'আপনার অনুরোধে',
    'we could not reach you by phone to confirm it' => 'ফোনে যোগাযোগ করা যায়নি',
    'the address or phone number could not be verified' => 'ঠিকানা/নম্বর যাচাই করা যায়নি',
    'the product is currently out of stock' => 'পণ্যটি স্টকে নেই',
    'of a price or offer issue' => 'মূল্য/অফার সংক্রান্ত',
    'we are unable to deliver to your address' => 'আপনার ঠিকানায় ডেলিভারি সম্ভব নয়',
    'we could not complete the order verification' => 'অর্ডার যাচাই সম্পন্ন হয়নি',
    'the order was cancelled' => '',
  ];
  /** $why is the English reason used by the order screens; a reason typed by staff ("Other") is sent as typed. */
  public static function orderCancelled(object $o, string $why): string {
    $w = rtrim(trim($why), '. '); $bn = array_key_exists($w, self::CANCEL_WHY) ? self::CANCEL_WHY[$w] : $w;
    return self::BRAND . ' আপনার অর্ডার ' . Inv::show($o->invoice) . ' বাতিল হয়েছে।' . ($bn !== '' ? ' কারণ: ' . $bn . '।' : '') . ' ' . self::help();
  }
  public static function orderUpdated($invoice, $total): string { return self::BRAND . ' আপনার অর্ডার ' . Inv::show($invoice) . ' আপডেট হয়েছে। নতুন মোট ' . self::tk($total) . '।'; }
  public static function payVerified($invoice): string { return self::BRAND . ' অর্ডার ' . Inv::show($invoice) . '-এর পেমেন্ট পেয়েছি। ধন্যবাদ।'; }
  public static function payRejected($invoice): string { return self::BRAND . ' অর্ডার ' . Inv::show($invoice) . '-এর পেমেন্ট যাচাই হয়নি। ' . self::help(); }
  public static function otp(string $code): string { return self::BRAND . ' আপনার কোড ' . $code . '। কাউকে দেবেন না।'; }
  public static function incomplete(string $wa, string $call): string { return self::BRAND . ' অর্ডার অসম্পূর্ণ আছে। WhatsApp ' . $wa . ', কল ' . $call; }
  const NO_ANSWER = 'DutyFreeBD থেকে কল দিয়েছিলাম, পাইনি। কল দিন: {hotline}';
  const NO_ANSWER_OLD = 'আসসালামু আলাইকুম। DutyFreeBD থেকে আপনাকে কল দিয়েছিলাম, কিন্তু পাইনি। আপনার সাথে কথা বলা দরকার। সুবিধামতো {hotline} নম্বরে কল দিন।';

  // Staff and supplier messages
  public static function supplierOtp(string $code): string { return 'DutyFreeBD Supplier Portal: আপনার OTP ' . $code . ' (১০ মিনিট)। কাউকে জানাবেন না।'; }
  public static function supplierInvite(string $name, string $brand, string $user, string $url): string { return "$name,\nআপনার $brand অ্যাকাউন্ট প্রস্তুত।\nইউজারনেম: $user\nচালু করুন: $url\nলিংকটি একবারই কাজ করবে।"; }
  public static function supplierTempPw(string $name, string $brand, string $user, string $pw, string $url): string { return "$name,\nআপনার $brand লগইন:\nইউজারনেম: $user\nঅস্থায়ী পাসওয়ার্ড: $pw\nলগইন: $url\nপ্রথম লগইনে নতুন পাসওয়ার্ড দিন।"; }
  public static function supplierLogin(string $url, string $code, string $mobile, string $pw): string { return "DutyFreeBD Supplier Portal: $url লগইন: $code / $mobile পাসওয়ার্ড: $pw (প্রথম লগইনে বদলান)"; }
  public static function reorder(bool $pre, string $product, $qty, string $url): string { return ($pre ? 'প্রি-অর্ডার অনুরোধ' : 'কম স্টক: ক্রয় অনুরোধ') . "\nপণ্য: $product\nপরিমাণ: $qty\nদাম ও পরিমাণ জানান: $url"; }
  public static function productStatus(string $what, bool $published, string $by, string $time, string $store): string { return "পণ্য $what " . ($published ? 'প্রকাশ' : 'অপ্রকাশ') . " করেছেন {$by}, {$time}। স্টোর: {$store}"; }
  public static function salary(string $name, string $month, $amount): string { return self::BRAND . ': ' . $name . ', ' . $month . ' মাসের বেতন/কমিশন ৳' . number_format((float)$amount) . ' পরিশোধ হয়েছে।'; }
  public static function test(string $time): string { return self::BRAND . ' পরীক্ষামূলক এসএমএস। সময়: ' . $time; }

  /** [parts, chars, unicode] by the gateway's own counting: 70 / 67 per part for Bangla (UCS-2, counted in UTF-16 units), 160 / 153 for plain English. */
  public static function count(string $s): array {
    $uni = (bool)preg_match('/[^\x00-\x7F]/u', $s);
    $n = $uni ? (int)(strlen(mb_convert_encoding($s, 'UTF-16LE', 'UTF-8')) / 2) : strlen($s);
    if ($n === 0) return [0, 0, $uni];
    $one = $uni ? 70 : 160; $multi = $uni ? 67 : 153;
    return [$n <= $one ? 1 : (int)ceil($n / $multi), $n, $uni];
  }
}
