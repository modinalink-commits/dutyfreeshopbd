<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
// (second pass, query fixed) Owner (8 Nov 2026): remove everything that came from startech.com.bd (products, photos, links, scan data, the store itself). A new Gadget & Electronics source will be added instead. Products that already appear on an order are only unpublished.
return new class extends Migration {
    public function up(): void {
        try {
            $stores = DB::table('stores')->whereRaw("lower(domain) in ('startech.com.bd','www.startech.com.bd')")->pluck('id')->all();
            $pids = [];
            if (Schema::hasTable('watch_source')) $pids = DB::table('watch_source')->whereIn('store_id', $stores ?: [0])->pluck('product_id')->all();
            $cat = DB::table('categories')->where('slug', 'gadget-electronics')->value('id');
            if ($cat) $pids = array_values(array_unique(array_merge($pids, DB::table('products')->where('category_id', $cat)->pluck('id')->all())));
            foreach (array_chunk($pids, 1000) as $ch) {
                $ordered = Schema::hasTable('order_items') ? DB::table('order_items')->whereIn('product_id', $ch)->pluck('product_id')->all() : [];
                if ($ordered) DB::table('products')->whereIn('id', $ordered)->update(['published' => false, 'updated_at' => now()]);
                $del = array_values(array_diff($ch, $ordered));
                if (!$del) continue;
                if (Schema::hasTable('product_images')) DB::table('product_images')->whereIn('product_id', $del)->delete();
                if (Schema::hasTable('watch_source')) DB::table('watch_source')->whereIn('product_id', $del)->delete();
                DB::table('products')->whereIn('id', $del)->delete();
            }
            if ($stores) {
                if (Schema::hasTable('watch_source')) DB::table('watch_source')->whereIn('store_id', $stores)->delete();
                DB::table('store_products')->whereIn('store_id', $stores)->delete();
                foreach ($stores as $sid) { @unlink(storage_path('app/scan-' . (int)$sid . '.json')); }
                DB::table('stores')->whereIn('id', $stores)->delete();
            }
            $dir = public_path('uploads/gadget');
            if (is_dir($dir)) foreach (new \FilesystemIterator($dir) as $f) { if ($f->isFile()) @unlink($f->getPathname()); }
            foreach (['store.cats', 'store.catslugs', 'store.home13', 'stock.modes', 'stock.parents', 'prepaid.catset', 'store.hsec3.gadget-electronics'] as $k) { try { Cache::forget($k); } catch (\Throwable $e) {} }
        } catch (\Throwable $e) { report($e); }
    }
    public function down(): void {}
};
