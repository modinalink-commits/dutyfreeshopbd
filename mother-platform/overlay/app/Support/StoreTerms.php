<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Customer-facing wording of a source store's terms (delivery time, returns, authenticity). Vendor details never leave the admin. */
class StoreTerms {
    public static function forProduct(int $pid): ?array {
        return Cache::remember('storeterms.p' . $pid, 600, function () use ($pid) {
            try {
                if (!Schema::hasTable('watch_source') || !Schema::hasColumn('stores', 'delivery_min')) return null;
                $s = DB::table('watch_source as w')->join('stores as s', 's.id', '=', 'w.store_id')->where('w.product_id', $pid)->first(['s.delivery_min', 's.delivery_max', 's.delivery_style', 's.return_days', 's.return_cost_by', 's.return_note', 's.auth_note', 's.pay_mode']);
                if (!$s) return null;
                $a = (int)$s->delivery_min; $b = (int)$s->delivery_max;
                $del = ($b > 0) ? ($s->delivery_style === 'max' || $a === $b || $a <= 0 ? 'Delivered within ' . $b . ' days' : 'Delivery in ' . $a . '–' . $b . ' days') : null;
                $ret = null;
                if ($s->return_days !== null) $ret = (int)$s->return_days === 0 ? 'No returns on this item' : 'Return within ' . (int)$s->return_days . ' days' . ($s->return_cost_by === 'customer' ? ' (return shipping by customer)' : ($s->return_cost_by === 'us' ? ' (we cover return shipping)' : ''));
                return ['delivery' => $del, 'returns' => $ret, 'note' => $s->return_note ?: null, 'auth' => $s->auth_note ?: null];
            } catch (\Throwable $e) { return null; }
        });
    }
}
