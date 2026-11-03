<?php
namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Coupon portal rules: time window, customer targeting, product scope, channel. Used by checkout, Create Order and the admin portal. */
class CouponRules
{
    const TZ = 'Asia/Dhaka';
    const TYPES = ['percent' => 'Percent off (%)', 'fixed' => 'Fixed amount off (৳)', 'free_delivery' => 'Free delivery'];
    const AUDIENCES = [
        'all' => 'Every customer',
        'new' => 'New customers only (no earlier order)',
        'returning' => 'Returning customers only (at least one earlier order)',
        'vip' => 'VIP customers only',
        'member' => 'Active members only (any membership level)',
        'phones' => 'Specific mobile numbers only',
    ];
    const CHANNELS = ['any' => 'Website and staff-created orders', 'web' => 'Website checkout only', 'admin' => 'Staff Create Order only'];

    public static function ids(?string $csv): array
    {
        return array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,;]+/', (string)$csv, -1, PREG_SPLIT_NO_EMPTY)))));
    }
    public static function phones(?string $txt): array
    {
        $o = [];
        foreach (preg_split('/[\s,;]+/', (string)$txt, -1, PREG_SPLIT_NO_EMPTY) as $p) { $d = substr(preg_replace('/\D/', '', $p), -10); if (strlen($d) === 10) $o[$d] = true; }
        return array_keys($o);
    }
    private static function last10(string $p): string { return substr(preg_replace('/\D/', '', $p), -10); }

    /** [start, end] as UTC Carbon (or null). The exact date-time wins; older coupons fall back to their whole valid_from / valid_to days (Asia/Dhaka). */
    public static function window($cp): array
    {
        $s = !empty($cp->starts_at) ? Carbon::parse($cp->starts_at, 'UTC') : (!empty($cp->valid_from) ? Carbon::parse(substr((string)$cp->valid_from, 0, 10), self::TZ)->startOfDay()->utc() : null);
        $e = !empty($cp->ends_at) ? Carbon::parse($cp->ends_at, 'UTC') : (!empty($cp->valid_to) ? Carbon::parse(substr((string)$cp->valid_to, 0, 10), self::TZ)->endOfDay()->utc() : null);
        return [$s, $e];
    }
    public static function local($utc, string $fmt = 'd M Y, h:i A'): string { return $utc ? Carbon::parse($utc, 'UTC')->timezone(self::TZ)->format($fmt) : ''; }

    /** Off / Scheduled / Live / Expired / Used up */
    public static function status($cp): array
    {
        [$s, $e] = self::window($cp); $now = now();
        if (!$cp->active) return ['Off', 'off'];
        if ($cp->usage_limit && (int)$cp->used_count >= (int)$cp->usage_limit) return ['Used up', 'used'];
        if ($e && $now->gt($e)) return ['Expired', 'exp'];
        if ($s && $now->lt($s)) return ['Scheduled', 'sch'];
        return ['Live', 'live'];
    }

    /** Lines of the cart that this coupon applies to (all lines when no product/category/brand scope is set). */
    public static function eligibleLines($cp, array $items): array
    {
        $pid = self::ids($cp->product_ids ?? ''); $cat = self::ids($cp->category_ids ?? ''); $br = self::ids($cp->brand_ids ?? '');
        if (!$pid && !$cat && !$br) return $items;
        $need = array_values(array_unique(array_filter(array_map(fn($i) => (int)($i['product_id'] ?? 0), $items))));
        $meta = $need ? DB::table('products')->whereIn('id', $need)->get(['id', 'category_id', 'sub_category_id', 'sub_sub_category_id', 'brand_id'])->keyBy('id') : collect();
        return array_values(array_filter($items, function ($i) use ($pid, $cat, $br, $meta) {
            $id = (int)($i['product_id'] ?? 0); if (!$id) return false; $m = $meta[$id] ?? null;
            if (in_array($id, $pid)) return true;
            if ($m && $cat && (in_array((int)$m->category_id, $cat) || in_array((int)$m->sub_category_id, $cat) || in_array((int)$m->sub_sub_category_id, $cat))) return true;
            if ($m && $br && in_array((int)$m->brand_id, $br)) return true;
            return false;
        }));
    }
    public static function scoped($cp): bool { return (bool)(self::ids($cp->product_ids ?? '') || self::ids($cp->category_ids ?? '') || self::ids($cp->brand_ids ?? '')); }

    /** Money the percent / fixed discount is worked out on (eligible lines only when the coupon is scoped). */
    public static function base($cp, float $sub, array $items): float
    {
        if (!self::scoped($cp)) return $sub;
        $b = 0.0; foreach (self::eligibleLines($cp, $items) as $i) $b += (float)($i['price'] ?? 0) * (int)($i['qty'] ?? 1);
        return min($b, $sub);
    }

    /** Customer-targeting rules only (no cart needed). */
    public static function audienceError($cp, ?string $phone, $c, array $st, ?string $tier): ?string
    {
        $a = (string)($cp->audience ?? 'all');
        $who = 'This coupon is not available for this customer';
        if ($a !== 'all') {
            if (!$phone) return 'Enter the customer mobile number to use this coupon';
            if ($a === 'new' && $st['n'] > 0) return 'This coupon is for new customers only';
            if ($a === 'returning' && $st['n'] < 1) return 'This coupon is for returning customers only';
            if ($a === 'vip' && !($c && strtolower((string)($c->type ?? '')) === 'vip')) return $who;
            if ($a === 'member' && !$tier) return $who;
            if ($a === 'phones' && !in_array(self::last10($phone), self::phones($cp->audience_phones ?? ''))) return $who;
        }
        return null;
    }

    /** Null when the coupon can be used, otherwise the reason (shown to staff / customer). $c = customers row or null, $st = ['n'=>orders,'s'=>spent]. */
    public static function error($cp, ?string $phone, $c, array $st, float $sub, array $items, bool $web, ?string $tier = null): ?string
    {
        [$s, $e] = self::window($cp); $now = now();
        if ($s && $now->lt($s)) return 'Coupon starts on ' . self::local($s);
        if ($e && $now->gt($e)) return 'Coupon has expired';
        if ($cp->usage_limit && (int)$cp->used_count >= (int)$cp->usage_limit) return 'Coupon usage limit reached';
        $ch = (string)($cp->channel ?? 'any');
        if ($ch === 'web' && !$web) return 'This coupon works on the website checkout only';
        if ($ch === 'admin' && $web) return 'This coupon is for staff-created orders only';
        if ($m = self::audienceError($cp, $phone, $c, $st, $tier)) return $m;
        if ($cp->min_past_orders !== null && $cp->min_past_orders !== '' && $st['n'] < (int)$cp->min_past_orders) return 'Needs at least ' . (int)$cp->min_past_orders . ' earlier order(s)';
        if ($cp->max_past_orders !== null && $cp->max_past_orders !== '' && $st['n'] > (int)$cp->max_past_orders) return 'Only for customers with up to ' . (int)$cp->max_past_orders . ' earlier order(s)';
        if (self::scoped($cp)) {
            $lines = self::eligibleLines($cp, $items);
            if (!$lines) return 'This coupon applies to selected products only. None of them is in the cart';
            $q = 0; foreach ($lines as $i) $q += (int)($i['qty'] ?? 1);
            if (!empty($cp->min_qty) && $q < (int)$cp->min_qty) return 'Needs at least ' . (int)$cp->min_qty . ' of the coupon products';
        } elseif (!empty($cp->min_qty)) {
            $q = 0; foreach ($items as $i) $q += (int)($i['qty'] ?? 1);
            if ($q < (int)$cp->min_qty) return 'Needs at least ' . (int)$cp->min_qty . ' items';
        }
        return null;
    }

    /** One-line summary for lists. */
    public static function summary($cp): string
    {
        if ($cp->type === 'percent') $v = rtrim(rtrim(number_format((float)$cp->value, 2), '0'), '.') . '% off' . ($cp->max_discount ? ' (max ৳' . number_format((float)$cp->max_discount) . ')' : '');
        elseif ($cp->type === 'fixed') $v = '৳' . number_format((float)$cp->value) . ' off';
        else $v = 'Free delivery';
        return $v;
    }

    /** Public coupons a given website customer may see (time window, channel and targeting). */
    public static function visibleTo($cp, ?string $phone, $c): bool
    {
        [$s, $e] = self::window($cp); $now = now();
        if (($s && $now->lt($s)) || ($e && $now->gt($e))) return false;
        if (($cp->channel ?? 'any') === 'admin') return false;
        $a = (string)($cp->audience ?? 'all'); if ($a === 'all' || $a === '') return true;
        if (!$phone) return false;
        $st = OrderDiscount::stats($phone, $c);
        return self::audienceError($cp, $phone, $c, $st, ($c && OrderDiscount::tier($phone, $c)) ? 'm' : null) === null;
    }
}
