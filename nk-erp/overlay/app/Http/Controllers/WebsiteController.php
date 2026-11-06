<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\WebsiteOrder;
use Illuminate\Http\Request;

/**
 * Public website (nklpgbd.com) editing from the back office:
 * notice bar, order form on/off, and the contact details the site shows.
 * The site reads all of it from /api/site, so nothing needs a code change.
 */
class WebsiteController extends Controller
{
    public const KEYS = ['site_notice_en', 'site_notice_bn', 'site_notice_on', 'site_notice_until', 'site_orders_open', 'site_closed_msg', 'site_prices_on', 'site_owners'];

    public static function cfg(): array
    {
        $v = Setting::query()->whereIn('key', array_merge(self::KEYS, ['phone', 'whatsapp', 'address_en', 'address_bn', 'opening_hours', 'email']))->pluck('value', 'key')->all();
        $until = $v['site_notice_until'] ?? null;
        $noticeLive = ($v['site_notice_on'] ?? '0') === '1' && (trim($v['site_notice_en'] ?? '') !== '' || trim($v['site_notice_bn'] ?? '') !== '')
            && (! $until || $until >= now('Asia/Dhaka')->toDateString());
        return $v + ['notice_live' => $noticeLive, 'orders_open' => ($v['site_orders_open'] ?? '1') === '1'];
    }

    public function index()
    {
        $c = self::cfg();
        $since = now('Asia/Dhaka')->startOfDay()->utc();
        $stats = [
            'today' => WebsiteOrder::query()->where('created_at', '>=', $since)->count(),
            'new' => WebsiteOrder::query()->where('status', 'new')->count(),
            'month' => WebsiteOrder::query()->where('created_at', '>=', now('Asia/Dhaka')->startOfMonth()->utc())->count(),
        ];
        $heroAt = Setting::query()->where('key', 'hero_published')->value('updated_at');
        $log = AuditLog::query()->where('action', 'like', 'website_%')->orWhere('action', 'like', 'hero_%')->latest('id')->limit(8)->get();
        return view('website', ['c' => $c, 'stats' => $stats, 'heroAt' => $heroAt, 'log' => $log, 'prices' => self::prices()]);
    }

    public function save(Request $request)
    {
        $d = $request->validate([
            'site_notice_en' => ['nullable', 'string', 'max:200'],
            'site_notice_bn' => ['nullable', 'string', 'max:200'],
            'site_notice_on' => ['required', 'in:0,1'],
            'site_notice_until' => ['nullable', 'date'],
            'site_orders_open' => ['required', 'in:0,1'],
            'site_closed_msg' => ['nullable', 'string', 'max:200'],
            'site_prices_on' => ['required', 'in:0,1'],
            'site_owners' => ['nullable', 'string', 'max:600'],
        ]);
        $old = self::cfg();
        $changed = [];
        foreach (self::KEYS as $k) {
            $nv = $d[$k] ?? null;
            if ((string) ($old[$k] ?? '') !== (string) $nv) $changed[] = $k;
            Setting::put($k, $nv);
        }
        if ($changed) AuditLog::record('website_settings', 'Changed: '.implode(', ', str_replace('site_', '', $changed)));
        return back()->with('ok', $changed ? 'Saved. nklpgbd.com shows the change within a minute.' : 'Nothing changed.');
    }

    /** Retail prices the website shows: the selling brand's active refill and new-cylinder products. */
    public static function prices(): array
    {
        $brand = \App\Models\Brand::query()->where('for_sale', true)->orderBy('id')->value('id');
        $ps = \App\Models\Product::query()->where('is_active', true)->whereIn('category', ['refill', 'new_package'])->when($brand, fn ($q) => $q->where('brand_id', $brand))->whereNotNull('retail_price')->get();
        $out = [];
        foreach ($ps as $p) {
            $out[(int) $p->size_kg][$p->category === 'refill' ? 'refill' : 'pkg'] = (float) $p->retail_price;
            if ($p->image && ($p->category === 'refill' || empty($out[(int) $p->size_kg]['img']))) $out[(int) $p->size_kg]['img'] = url($p->image);
        }
        ksort($out);
        return ['sizes' => $out, 'updated' => $ps->max('updated_at')?->timezone('Asia/Dhaka')->format('d-m-Y')];
    }

    public static function owners(?string $raw): array
    {
        return collect(preg_split('/\r?\n/', (string) $raw))->map(fn ($l) => trim($l))->filter()->take(6)->values()->all();
    }

    /** Public: what nklpgbd.com shows. Only public facts, never anything internal. */
    public function api()
    {
        $c = self::cfg();
        $digits = fn ($p) => preg_replace('/\D+/', '', (string) $p);
        $wa = $digits($c['whatsapp'] ?? $c['phone'] ?? '');
        if ($wa !== '' && str_starts_with($wa, '0')) $wa = '88'.$wa;
        return response()->json([
            'phone' => $c['phone'] ?? null,
            'whatsapp' => $wa ?: null,
            'address' => $c['address_en'] ?? null,
            'address_bn' => $c['address_bn'] ?? null,
            'hours' => $c['opening_hours'] ?? null,
            'email' => $c['email'] ?? null,
            'notice' => $c['notice_live'] ? ['en' => $c['site_notice_en'] ?? '', 'bn' => $c['site_notice_bn'] ?? ''] : null,
            'orders_open' => $c['orders_open'],
            'closed_msg' => $c['orders_open'] ? null : ($c['site_closed_msg'] ?: 'Online orders are paused right now. Please call us.'),
            'prices' => ($c['site_prices_on'] ?? '1') === '1' ? self::prices() : null,
            'owners' => self::owners($c['site_owners'] ?? null),
        ], 200, ['Cache-Control' => 'public, max-age=60']);
    }
}
