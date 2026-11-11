<?php
namespace App\Http\Controllers;
use App\Support\Sms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/** Checkout auto-fill for returning customers. Details are only released after an SMS code sent to that very number. */
class CheckoutLookupController extends Controller {
  /** SMS-code auto-fill is OFF unless the setting checkout_otp is exactly '1' (it was costing SMS money). */
  private function on(): bool { return (string)DB::table('settings')->where('key', 'checkout_otp')->value('value') === '1'; }
  private function phone(Request $r): ?string {
    $p = substr(preg_replace('/\D/', '', (string)$r->input('phone')), -11);
    return preg_match('/^01\d{9}$/', $p) ? $p : null;
  }
  private function last(string $p) {
    return DB::table('orders')->where('phone', $p)->whereNotNull('address')->where('address', '!=', '')->orderByDesc('id')->first();
  }
  public function lookup(Request $r) {
    $p = $this->phone($r); if (!$p) return response()->json(['found'=>false]);
    $o = $this->last($p); if (!$o) return response()->json(['found'=>false]);
    if (!$this->on()) {
      // Free auto-fill (no SMS): name, address and district of the last order for this number. Capped per visitor so the list of customers cannot be harvested.
      $k = 'co.lk.'.md5((string)$r->ip()); Cache::add($k, 0, 3600); if ((int)Cache::get($k, 0) >= 20) return response()->json(['found'=>false]);
      Cache::increment($k);
      return response()->json(['found'=>true, 'otp'=>false, 'd'=>['name'=>$o->customer_name, 'address'=>$o->address, 'district'=>$o->district]]);
    }
    $n = trim((string)$o->customer_name);
    return response()->json(['found'=>true, 'hint'=>mb_substr($n, 0, 1).'•••', 'otp'=>Sms::configured()]);
  }
  public function otp(Request $r) {
    if (!$this->on()) return response()->json(['sent'=>false]);
    $p = $this->phone($r); if (!$p || !Sms::configured() || !$this->last($p)) return response()->json(['sent'=>false]);
    if (Cache::has("co.otp.cool.$p")) return response()->json(['sent'=>false, 'wait'=>true]);
    Cache::add('sms.otp.day', 0, 86400); if ((int)Cache::get('sms.otp.day', 0) >= 3000) return response()->json(['sent'=>false]);
    Cache::increment('sms.otp.day');
    $c = (string)random_int(100000, 999999);
    Cache::put("co.otp.$p", ['h'=>hash('sha256', $c.$p.config('app.key')), 't'=>0], 300);
    Cache::put("co.otp.cool.$p", 1, 60);
    Sms::queue(Sms::number($p), "DutyFree BD: আপনার ভেরিফিকেশন কোড {$c} । কোডটি কাউকে দেবেন না।", 'checkout_otp');
    return response()->json(['sent'=>true]);
  }
  public function verify(Request $r) {
    if (!$this->on()) return response()->json(['ok'=>false]);
    $p = $this->phone($r); $code = preg_replace('/\D/', '', (string)$r->input('code'));
    $s = $p ? Cache::get("co.otp.$p") : null;
    if (!$s || $s['t'] >= 5) return response()->json(['ok'=>false]);
    if (!hash_equals($s['h'], hash('sha256', $code.$p.config('app.key')))) { $s['t']++; Cache::put("co.otp.$p", $s, 300); return response()->json(['ok'=>false]); }
    Cache::forget("co.otp.$p");
    $o = $this->last($p); if (!$o) return response()->json(['ok'=>false]);
    $email = $o->email ?: (DB::table('customers')->where('phone', $p)->value('email') ?: '');
    return response()->json(['ok'=>true, 'd'=>['name'=>$o->customer_name, 'address'=>$o->address, 'district'=>$o->district, 'email'=>$email]]);
  }
}
