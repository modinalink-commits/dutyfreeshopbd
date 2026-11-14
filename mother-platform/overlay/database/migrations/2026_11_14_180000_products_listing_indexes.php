<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Speed: the products table (about 124 MB) had no index on category, brand or the published flag, so every storefront
 * listing read the whole table (measured 14 Nov 2026: about 10 full scans a second, 350,000 rows read a second).
 * Indexes only: no column, no data and no behaviour changes.
 */
return new class extends Migration {
  public function up(): void {
    foreach ([
      'create index if not exists products_pub_cat_id_idx on products (category_id, id desc) where published',
      'create index if not exists products_pub_id_idx on products (id desc) where published',
      'create index if not exists products_pub_brand_idx on products (brand_id, id desc) where published',
      'create index if not exists products_category_id_idx on products (category_id)',
      'create index if not exists products_brand_id_idx on products (brand_id)',
    ] as $sql) { try { DB::statement($sql); } catch (\Throwable $e) { report($e); } }
    try { DB::statement('analyze products'); } catch (\Throwable $e) {}
  }
  public function down(): void {
    foreach (['products_pub_cat_id_idx', 'products_pub_id_idx', 'products_pub_brand_idx', 'products_category_id_idx', 'products_brand_id_idx'] as $i) { try { DB::statement('drop index if exists ' . $i); } catch (\Throwable $e) {} }
  }
};
