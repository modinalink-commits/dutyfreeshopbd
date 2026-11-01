<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;

/** One place that decides which logo to show for a payment method or a courier. Drop a file into public/couriers/<slug>.(png|webp|svg) to add a courier logo; bank logos come from the payment-method record. */
class Brand
{
  public static function slug(?string $s): string { return trim(preg_replace('/[^a-z0-9]+/', '', strtolower((string)$s))); }
  public static function courierLogo(?string $name): ?string {
    $s = self::slug($name); if ($s === '') return null;
    static $c = []; if (array_key_exists($s, $c)) return $c[$s];
    foreach (['svg', 'webp', 'png'] as $e) if (is_file(public_path("couriers/$s.$e"))) return $c[$s] = "/couriers/$s.$e";
    foreach (['pathao', 'redx', 'steadfast', 'paperfly', 'ecourier', 'sundarban'] as $k) if (str_contains($s, $k)) foreach (['svg', 'webp', 'png'] as $e) if (is_file(public_path("couriers/$k.$e"))) return $c[$s] = "/couriers/$k.$e";
    return $c[$s] = null;
  }
  /** @return array{logo:?string,name:string,sub:?string} */
  public static function payment($o): array {
    $m = trim((string)($o->payment_method ?? '')); $l = strtolower($m);
    static $banks = null; if ($banks === null) $banks = DB::table('payment_methods')->where('kind', 'bank')->get(['title', 'logo'])->all();
    if ($m === '' || $l === 'cod' || str_contains($l, 'cash')) return ['logo' => is_file(public_path('pay/cod.png')) ? '/pay/cod.png' : null, 'name' => 'Cash on Delivery', 'sub' => null, 'cod' => true];
    if (str_contains($l, 'shurjo')) return ['logo' => '/pay/shurjopay-logo.png', 'name' => 'Pay Online', 'sub' => 'via ShurjoPay'];
    if (str_contains($l, 'bkash')) return ['logo' => '/pay/bkash.png', 'name' => $m, 'sub' => null];
    if (str_contains($l, 'nagad')) return ['logo' => '/pay/nagad.png', 'name' => $m, 'sub' => null];
    if (str_contains($l, 'bank')) { foreach ($banks as $b) if (!empty($o->pay_account) && strcasecmp(trim($b->title), trim($o->pay_account)) === 0 && $b->logo && is_file(public_path("pay/{$b->logo}.png"))) return ['logo' => "/pay/{$b->logo}.png", 'name' => 'Bank Transfer', 'sub' => null]; return ['logo' => null, 'name' => 'Bank Transfer', 'sub' => $o->pay_account ?? null]; }
    return ['logo' => null, 'name' => $m, 'sub' => null];
  }
}
