<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('products') && !Schema::hasColumn('products', 'image_alt')) Schema::table('products', fn(Blueprint $t) => $t->string('image_alt', 300)->nullable());
    if (Schema::hasTable('product_images') && !Schema::hasColumn('product_images', 'alt')) Schema::table('product_images', fn(Blueprint $t) => $t->string('alt', 300)->nullable());
    if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'meta_keywords')) Schema::table('categories', fn(Blueprint $t) => $t->string('meta_keywords', 500)->nullable());
    if (Schema::hasTable('brands') && !Schema::hasColumn('brands', 'meta_keywords')) Schema::table('brands', fn(Blueprint $t) => $t->string('meta_keywords', 500)->nullable());
    // robots.txt is now generated dynamically from Pages & SEO settings; remove the old static file so the route answers.
    $f = public_path('robots.txt'); if (is_file($f)) @unlink($f);
  }
  public function down(): void {}
};
