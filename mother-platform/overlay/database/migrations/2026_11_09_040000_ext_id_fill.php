<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Imported products (Watch/Gadget/Sunglass) had no ext_id, so the catalog/pixel id fell back to products.id and collided with other products' ext_id (Meta: IDs can't be duplicated). Give them a unique ext_id (1000000 + id). */
return new class extends Migration {
  public function up(): void {
    try { DB::statement('update products set ext_id = 1000000 + id where ext_id is null'); foreach (['store.catalogfeed10', 'store.sitemap14'] as $k) { try { \Cache::forget($k); } catch (\Throwable $e) {} } } catch (\Throwable $e) { report($e); }
  }
  public function down(): void {}
};
