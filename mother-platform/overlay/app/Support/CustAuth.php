<?php
namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Email + Facebook customer authentication helpers. One customer = one account; providers are only ways in. */
class CustAuth
{
  public static function flag(string $k, string $def = '0'): bool { $v = DB::table('settings')->where('key', $k)->value('value'); return ($v ?? $def) === '1'; }
  public static function emailOn(): bool { return self::flag('auth_email_on', '1'); }
  public static function fbConfigured(): bool { return (bool)(Sms::secret('fb_app_id') && Sms::secret('fb_app_secret')); }
  public static function fbOn(): bool { return self::flag('auth_fb_on', '0') && self::fbConfigured(); }
  public static function googleConfigured(): bool { return (bool)(Sms::secret('g_client_id') && Sms::secret('g_client_secret')); }
  public static function googleOn(): bool { return self::flag('auth_google_on', '0') && self::googleConfigured(); }
  public static function on(string $p): bool { return $p === 'google' ? self::googleOn() : self::fbOn(); }
  public static function redirectFor(string $p): string { return rtrim((string)config('app.url', 'https://dutyfreebd.com'), '/') . '/account/' . ($p === 'google' ? 'google' : 'facebook') . '/callback'; }
  public static function redirectUrl(): string { return rtrim((string)config('app.url', 'https://dutyfreebd.com'), '/') . '/account/facebook/callback'; }
  public static function mailReady(): bool { return config('mail.default') && !in_array(config('mail.default'), ['log', 'array'], true) && (config('mail.mailers.smtp.host') || config('mail.default') !== 'smtp'); }

  public static function log(Request $r, ?int $cid, string $provider, string $status = 'success'): void {
    try {
      DB::table('customer_login_history')->insert(['customer_id' => $cid, 'provider' => $provider, 'ip' => $r->ip(), 'device' => mb_substr((string)$r->userAgent(), 0, 160), 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
      if ($cid && $status === 'success') DB::table('customers')->where('id', $cid)->update(['last_login_at' => now()]);
    } catch (\Throwable $e) {}
  }
  public static function link(int $cid, string $provider, string $uid, ?string $email, ?string $name, ?string $avatar = null): void {
    DB::table('customer_auth_providers')->updateOrInsert(['provider' => $provider, 'provider_user_id' => $uid], ['customer_id' => $cid, 'provider_email' => $email, 'provider_name' => $name, 'avatar' => $avatar, 'updated_at' => now(), 'created_at' => now()]);
  }
  public static function methods(int $cid): array { return DB::table('customer_auth_providers')->where('customer_id', $cid)->orderBy('id')->get()->all(); }

  /** Facebook identity -> customer id. Returns [cid|null, state] state: login|linked|created|need_email */
  public static function resolveFacebook(array $me): array {
    return self::resolveSocial('facebook', (string)($me['id'] ?? ''), isset($me['email']) ? (string)$me['email'] : '', (string)($me['name'] ?? ''), $me['picture']['data']['url'] ?? null);
  }
  /** Any OAuth identity (email must already be verified by the provider) -> [customer id|null, state]. */
  public static function resolveSocial(string $prov, string $uid, string $email, string $name, ?string $avatar): array {
    if ($uid === '') return [null, 'error'];
    $email = mb_strtolower(trim($email)); $name = trim($name) ?: 'Customer';
    $p = DB::table('customer_auth_providers')->where('provider', $prov)->where('provider_user_id', $uid)->first();
    if ($p) { DB::table('customer_auth_providers')->where('id', $p->id)->update(['provider_name' => $name, 'avatar' => $avatar, 'updated_at' => now()]); return [(int)$p->customer_id, 'login']; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return [null, 'need_email'];
    $c = DB::table('customers')->whereRaw('lower(email) = ?', [$email])->orderByRaw('password is null')->orderBy('id')->first();
    if ($c) {
      // The provider has verified this email. If the existing account's email was never verified by us, an attacker may have registered it with a password: invalidate it.
      if (!$c->email_verified_at && $c->password) { DB::table('customers')->where('id', $c->id)->update(['password' => null]); DB::table('customer_sessions')->where('customer_id', $c->id)->delete(); DB::table('customer_auth_providers')->where('customer_id', $c->id)->where('provider', 'email')->delete(); }
      DB::table('customers')->where('id', $c->id)->update(['email_verified_at' => $c->email_verified_at ?? now(), 'profile_image' => $c->profile_image ?: $avatar, 'updated_at' => now()]);
      self::link((int)$c->id, $prov, $uid, $email, $name, $avatar); return [(int)$c->id, 'linked'];
    }
    $cid = self::create($name, $email, $avatar, true); self::link($cid, $prov, $uid, $email, $name, $avatar); return [$cid, 'created'];
  }
  public static function create(string $name, ?string $email, ?string $avatar = null, bool $verified = false, ?string $hash = null): int {
    return (int)DB::table('customers')->insertGetId(['name' => mb_substr($name, 0, 80), 'phone' => null, 'email' => $email, 'password' => $hash, 'type' => 'retail', 'profile_image' => $avatar, 'email_verified_at' => $verified ? now() : null, 'registered_at' => now(), 'ip' => request()->ip(), 'created_at' => now(), 'updated_at' => now()]);
  }
  /** Add a mobile number to an account. Returns null on success or an error message (Bangla). */
  public static function addMobile(object $c, string $phone, ?string $otpOk): ?string {
    if (!preg_match('/^01[0-9]{9}$/', $phone)) return 'সঠিক মোবাইল নম্বর দিন (01XXXXXXXXX)';
    $o = DB::table('customers')->where('phone', $phone)->where('id', '!=', $c->id)->orderBy('id')->first();
    $hist = DB::table('orders')->where('phone', $phone)->exists();
    if ($o && $o->password) return 'এই নম্বরটি অন্য একটি অ্যাকাউন্টে ব্যবহৃত।';
    if (($o || $hist) && !$otpOk) return Sms::configured() ? 'NEED_CODE' : 'এই নম্বরে আগের অর্ডার আছে, তাই নিরাপত্তার জন্য কোড লাগবে। সাপোর্টে কল করুন।';
    DB::transaction(function () use ($c, $phone, $o) {
      if ($o) { // merge the old guest record into this account
        $set = []; foreach (['dob', 'address'] as $k) if (empty($c->$k) && !empty($o->$k)) $set[$k] = $o->$k;
        foreach (['points', 'legacy_orders', 'legacy_spent', 'ref_balance'] as $k) if (isset($o->$k) && (float)$o->$k > 0) $set[$k] = (float)($c->$k ?? 0) + (float)$o->$k;
        DB::table('customers')->where('id', $o->id)->update(['phone' => null, 'note' => trim(($o->note ?? '') . ' [merged into #' . $c->id . ']'), 'updated_at' => now()]);
        DB::table('customers')->where('id', $c->id)->update($set);
      }
      DB::table('customers')->where('id', $c->id)->update(['phone' => $phone, 'phone_verified_at' => $otpOk ? now() : null, 'updated_at' => now()]);
      DB::table('orders')->where('phone', $phone)->whereNull('customer_id')->update(['customer_id' => $c->id]);
    });
    return null;
  }
}
