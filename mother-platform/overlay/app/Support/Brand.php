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
  /** Logo URL of a payment_methods row (uploaded logo first, then the legacy /pay/<logo>.png). */
  public static function logoUrl($m): ?string {
    if (!$m) return null;
    if (!empty($m->logo_url)) return $m->logo_url;
    if (!empty($m->logo) && is_file(public_path("pay/{$m->logo}.png"))) return "/pay/{$m->logo}.png";
    return null;
  }
  /** Master payment method row for an order (resolved from the stored label; order history keeps its own label). */
  public static function method($o, ?string $kind = null) {
    static $rows = null; if ($rows === null) { try { $rows = DB::table('payment_methods')->orderBy('position')->get()->all(); } catch (\Throwable $e) { $rows = []; } }
    if ($kind === null) {
      $l = strtolower(trim((string)($o->payment_method ?? '')));
      $kind = ($l === '' || $l === 'cod' || str_contains($l, 'cash')) ? 'cod' : (str_contains($l, 'shurjo') ? 'shurjopay' : (str_contains($l, 'bkash') ? 'bkash' : (str_contains($l, 'nagad') ? 'nagad' : (str_contains($l, 'bank') ? 'bank' : ''))));
    }
    if ($kind === 'bank') { foreach ($rows as $b) if ($b->kind === 'bank' && !empty($o->pay_account) && strcasecmp(trim($b->title), trim($o->pay_account)) === 0) return $b; return null; }
    foreach ($rows as $b) if ($b->kind === $kind) return $b;
    return null;
  }
  /** @return array{logo:?string,name:string,sub:?string,cod?:bool} */
  public static function payment($o): array {
    $m = trim((string)($o->payment_method ?? '')); $l = strtolower($m);
    $row = self::method($o);
    if ($m === '' || $l === 'cod' || str_contains($l, 'cash')) return ['logo' => self::logoUrl($row), 'name' => $row->title ?? 'Cash on Delivery', 'sub' => null, 'cod' => true];
    if (str_contains($l, 'shurjo')) return ['logo' => self::logoUrl($row) ?: '/pay/shurjopay-logo.png', 'name' => $row->title ?? 'Pay Online', 'sub' => 'via ShurjoPay'];
    if (str_contains($l, 'bkash') || str_contains($l, 'nagad')) return ['logo' => self::logoUrl($row) ?: '/pay/' . (str_contains($l, 'bkash') ? 'bkash' : 'nagad') . '.png', 'name' => $row->title ?? $m, 'sub' => null];
    if (str_contains($l, 'bank')) return ['logo' => self::logoUrl($row), 'name' => 'Bank Transfer', 'sub' => $row ? null : ($o->pay_account ?? null)];
    return ['logo' => null, 'name' => $m, 'sub' => null];
  }
}
