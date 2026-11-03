<?php
namespace App\Http\Controllers;

use App\Support\CouponRules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Coupon portal: create / edit / target / schedule / duplicate / bulk-generate / usage report. */
class CouponController extends Controller
{
    private function usage(): array
    {
        $o = [];
        foreach (DB::table('orders')->whereNotNull('coupon')->where('coupon', '<>', '')->where('status', '<>', 'Cancel')->selectRaw('upper(coupon) c, count(*) n, coalesce(sum(discount),0) d, coalesce(sum(total),0) t, max(created_at) last')->groupByRaw('upper(coupon)')->get() as $r) $o[$r->c] = $r;
        return $o;
    }

    public function index(Request $r)
    {
        $usage = $this->usage(); $q = trim((string)$r->query('q')); $st = (string)$r->query('status'); $au = (string)$r->query('audience'); $ty = (string)$r->query('type');
        $all = DB::table('coupons')->orderByDesc('id')->get();
        $rows = $all->map(function ($c) use ($usage) { $c->_st = CouponRules::status($c); $c->_u = $usage[strtoupper($c->code)] ?? null; return $c; });
        $cnt = ['all' => $rows->count(), 'live' => 0, 'sch' => 0, 'exp' => 0, 'off' => 0, 'used' => 0]; foreach ($rows as $c) $cnt[$c->_st[1]]++;
        $rows = $rows->filter(function ($c) use ($q, $st, $au, $ty) {
            if ($q !== '' && stripos($c->code . ' ' . ($c->name ?? ''), $q) === false) return false;
            if ($st !== '' && $c->_st[1] !== $st) return false;
            if ($au !== '' && ($c->audience ?: 'all') !== $au) return false;
            if ($ty !== '' && $c->type !== $ty) return false;
            return true;
        })->values();
        $given = 0; $uses = 0; foreach ($usage as $u) { $given += (float)$u->d; $uses += (int)$u->n; }
        return view('coupons_index', ['rows' => $rows, 'cnt' => $cnt, 'q' => $q, 'st' => $st, 'au' => $au, 'ty' => $ty, 'given' => $given, 'uses' => $uses]);
    }

    private function formData($c): array
    {
        return ['c' => $c, 'cats' => DB::table('categories')->orderBy('name')->get(['id', 'name', 'parent_id']), 'brands' => DB::table('brands')->orderBy('name')->get(['id', 'name']),
            'picked' => $c && CouponRules::ids($c->product_ids ?? '') ? DB::table('products')->whereIn('id', CouponRules::ids($c->product_ids))->get(['id', 'name', 'sku']) : collect()];
    }
    public function create(Request $r) { return view('coupon_form', $this->formData(null)); }
    public function edit($id) { $c = DB::table('coupons')->find($id); abort_unless($c, 404); return view('coupon_form', $this->formData($c)); }

    private function dt(?string $v): ?Carbon
    {
        if (!$v) return null;
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d'] as $f) { try { $d = Carbon::createFromFormat($f, $v, CouponRules::TZ); if ($f === 'Y-m-d') $d = $d->startOfDay(); return $d->utc(); } catch (\Throwable $e) {} }
        return null;
    }

    public function save(Request $r, $id = null)
    {
        $c = $id ? DB::table('coupons')->find($id) : null; if ($id) abort_unless($c, 404);
        $d = $r->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', 'unique:coupons,code' . ($c ? ',' . $c->id : '')],
            'name' => 'nullable|string|max:120', 'description' => 'nullable|string|max:1000',
            'type' => 'required|in:percent,fixed,free_delivery', 'value' => 'nullable|numeric|min:0', 'max_discount' => 'nullable|numeric|min:0', 'min_order' => 'nullable|numeric|min:0', 'min_qty' => 'nullable|integer|min:1',
            'usage_limit' => 'nullable|integer|min:1', 'per_customer' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|string|max:20', 'ends_at' => 'nullable|string|max:20',
            'audience' => 'required|in:' . implode(',', array_keys(CouponRules::AUDIENCES)), 'audience_phones' => 'nullable|string|max:20000',
            'min_past_orders' => 'nullable|integer|min:0', 'max_past_orders' => 'nullable|integer|min:0',
            'product_ids' => 'nullable|string|max:5000', 'category_ids' => 'nullable|array', 'category_ids.*' => 'integer', 'brand_ids' => 'nullable|array', 'brand_ids.*' => 'integer',
            'channel' => 'required|in:' . implode(',', array_keys(CouponRules::CHANNELS)),
        ]);
        $type = $d['type']; $val = (float)($d['value'] ?? 0);
        if ($type === 'percent' && ($val <= 0 || $val > 100)) return back()->withInput()->withErrors(['value' => 'Percent must be between 0.01 and 100']);
        if ($type === 'fixed' && $val <= 0) return back()->withInput()->withErrors(['value' => 'Enter the fixed amount in taka']);
        if ($type === 'free_delivery') $val = 0;
        $s = $this->dt($d['starts_at'] ?? null); $e = $this->dt($d['ends_at'] ?? null);
        if (!empty($d['starts_at']) && !$s) return back()->withInput()->withErrors(['starts_at' => 'Start date and time is not valid']);
        if (!empty($d['ends_at']) && !$e) return back()->withInput()->withErrors(['ends_at' => 'End date and time is not valid']);
        if ($s && $e && $e->lte($s)) return back()->withInput()->withErrors(['ends_at' => 'End must be after the start']);
        if ($d['audience'] === 'phones' && !CouponRules::phones($d['audience_phones'] ?? '')) return back()->withInput()->withErrors(['audience_phones' => 'Add at least one valid mobile number (11 digits)']);
        $pids = CouponRules::ids($d['product_ids'] ?? '');
        $row = [
            'code' => strtoupper($d['code']), 'name' => $d['name'] ?? null, 'description' => $d['description'] ?? null, 'type' => $type, 'value' => $val,
            'max_discount' => $type === 'percent' ? ($d['max_discount'] ?? null) : null, 'min_order' => $d['min_order'] ?? 0, 'min_qty' => $d['min_qty'] ?? null,
            'usage_limit' => $d['usage_limit'] ?? null, 'per_customer' => (int)($d['per_customer'] ?? 0),
            'starts_at' => $s, 'ends_at' => $e, 'valid_from' => $s ? $s->copy()->timezone(CouponRules::TZ)->toDateString() : null, 'valid_to' => $e ? $e->copy()->timezone(CouponRules::TZ)->toDateString() : null,
            'audience' => $d['audience'], 'audience_phones' => $d['audience'] === 'phones' ? implode("\n", CouponRules::phones($d['audience_phones'])) : null,
            'min_past_orders' => $d['min_past_orders'] ?? null, 'max_past_orders' => $d['max_past_orders'] ?? null,
            'product_ids' => $pids ? implode(',', $pids) : null, 'category_ids' => !empty($d['category_ids']) ? implode(',', array_map('intval', $d['category_ids'])) : null, 'brand_ids' => !empty($d['brand_ids']) ? implode(',', array_map('intval', $d['brand_ids'])) : null,
            'channel' => $d['channel'], 'is_public' => $r->boolean('is_public'), 'active' => $r->boolean('active'), 'updated_at' => now(),
        ];
        if ($c) { DB::table('coupons')->where('id', $c->id)->update($row); $nid = $c->id; }
        else { $nid = DB::table('coupons')->insertGetId($row + ['created_by' => optional($r->user())->name, 'created_at' => now()]); }
        return redirect('/admin/coupons')->with('ok', 'Coupon ' . $row['code'] . ' saved');
    }

    public function toggle($id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        DB::table('coupons')->where('id', $id)->update(['active' => !$c->active, 'updated_at' => now()]);
        return back()->with('ok', 'Coupon ' . $c->code . ($c->active ? ' turned off' : ' turned on'));
    }

    public function duplicate($id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        $row = (array)$c; unset($row['id']); $base = preg_replace('/-C\d+$/', '', $c->code); $n = 2; do { $code = substr($base, 0, 34) . '-C' . $n++; } while (DB::table('coupons')->where('code', $code)->exists());
        $row['code'] = $code; $row['used_count'] = 0; $row['active'] = false; $row['created_at'] = now(); $row['updated_at'] = now(); $row['created_by'] = optional(request()->user())->name;
        $nid = DB::table('coupons')->insertGetId($row);
        return redirect('/admin/coupons/' . $nid . '/edit')->with('ok', 'Copied as ' . $code . '. It is OFF until you turn it on.');
    }

    public function destroy($id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        if (DB::table('orders')->whereRaw('upper(coupon) = ?', [strtoupper($c->code)])->exists() || DB::table('winback_events')->where('coupon_code', $c->code)->exists())
            return back()->with('bad', 'This coupon already has orders. Turn it off instead of deleting, so the order history stays correct.');
        DB::table('coupons')->where('id', $id)->delete();
        return redirect('/admin/coupons')->with('ok', 'Coupon ' . $c->code . ' deleted');
    }

    /** Many one-off codes with the same rules as an existing coupon (e.g. 200 single-use codes for a campaign). */
    public function bulk(Request $r, $id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        $d = $r->validate(['prefix' => ['required', 'string', 'max:12', 'regex:/^[A-Za-z0-9_-]+$/'], 'count' => 'required|integer|min:1|max:500', 'single_use' => 'nullable']);
        $row = (array)$c; unset($row['id']); $made = []; $pre = strtoupper($d['prefix']);
        for ($i = 0; $i < (int)$d['count']; $i++) {
            do { $code = $pre . '-' . strtoupper(substr(str_replace(['0', 'O', '1', 'I', 'L'], '', strtoupper(bin2hex(random_bytes(6)))), 0, 6)); } while (strlen($code) < strlen($pre) + 5 || DB::table('coupons')->where('code', $code)->exists() || in_array($code, $made));
            $x = $row; $x['code'] = $code; $x['used_count'] = 0; $x['created_at'] = now(); $x['updated_at'] = now(); $x['created_by'] = optional($r->user())->name;
            if ($r->boolean('single_use')) { $x['usage_limit'] = 1; $x['per_customer'] = 1; }
            DB::table('coupons')->insert($x); $made[] = $code;
        }
        return response()->streamDownload(function () use ($made) { $f = fopen('php://output', 'w'); fputcsv($f, ['Code']); foreach ($made as $m) fputcsv($f, [$m]); fclose($f); }, 'coupon-codes-' . $pre . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function usageReport($id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        $o = DB::table('orders')->whereRaw('upper(coupon) = ?', [strtoupper($c->code)])->orderByDesc('id')->limit(300)->get(['id', 'invoice', 'customer_name', 'phone', 'status', 'discount', 'total', 'created_at']);
        $ok = $o->where('status', '<>', 'Cancel');
        return view('coupon_usage', ['c' => $c, 'orders' => $o, 'n' => $ok->count(), 'disc' => (float)$ok->sum('discount'), 'rev' => (float)$ok->sum('total'), 'people' => $ok->pluck('phone')->unique()->count()]);
    }

    public function csv()
    {
        $u = $this->usage();
        return response()->streamDownload(function () use ($u) {
            $f = fopen('php://output', 'w'); fputcsv($f, ['Code', 'Name', 'Status', 'Type', 'Value', 'Max discount', 'Min order', 'Customers', 'Starts (Dhaka)', 'Ends (Dhaka)', 'Usage limit', 'Per customer', 'Orders', 'Discount given', 'Public', 'Channel']);
            foreach (DB::table('coupons')->orderBy('id')->cursor() as $c) { $x = $u[strtoupper($c->code)] ?? null; fputcsv($f, [$c->code, $c->name, CouponRules::status($c)[0], $c->type, $c->value, $c->max_discount, $c->min_order, CouponRules::AUDIENCES[$c->audience ?: 'all'] ?? $c->audience, CouponRules::local(CouponRules::window($c)[0]), CouponRules::local(CouponRules::window($c)[1]), $c->usage_limit, $c->per_customer, $x->n ?? 0, $x->d ?? 0, $c->is_public ? 'yes' : 'no', $c->channel]); }
            fclose($f);
        }, 'coupons.csv', ['Content-Type' => 'text/csv']);
    }

    /** Staff test: would this coupon work for this number and basket? */
    public function test(Request $r, $id)
    {
        $c = DB::table('coupons')->find($id); abort_unless($c, 404);
        $ph = \App\Support\Chat::phone((string)$r->input('phone')) ?: null; $sub = (float)$r->input('sub', 0);
        $items = $sub > 0 ? [['product_id' => 0, 'price' => $sub, 'qty' => 1]] : [];
        $cu = $ph ? \App\Support\OrderDiscount::customer($ph) : null; $st = $ph ? \App\Support\OrderDiscount::stats($ph, $cu) : ['n' => 0, 's' => 0.0];
        $err = CouponRules::error($c, $ph, $cu, $st, $sub, $items, (bool)$r->boolean('web', true), ($ph && $cu && \App\Support\OrderDiscount::tier($ph, $cu)) ? 'm' : null);
        if (!$err && $sub < (float)$c->min_order) $err = 'Minimum order is ৳' . number_format($c->min_order);
        return response()->json(['ok' => !$err, 'message' => $err ?: 'This coupon works for this customer' . ($ph ? ' (' . $ph . ', ' . $st['n'] . ' earlier orders)' : '')]);
    }
}
