<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  private function cols(string $tbl, array $defs): void {
    if (!Schema::hasTable($tbl)) return;
    foreach ($defs as $c => $f) if (!Schema::hasColumn($tbl, $c)) Schema::table($tbl, fn(Blueprint $t) => $f($t, $c));
  }
  public function up(): void {
    $s = fn(Blueprint $t, $c) => $t->string($c, 500)->nullable(); $x = fn(Blueprint $t, $c) => $t->text($c)->nullable();
    $common = ['og_title' => $s, 'og_description' => $x, 'breadcrumb_title' => $s, 'image_alt' => $s, 'schema_type' => fn($t, $c) => $t->string($c, 30)->nullable(), 'seo_modes' => $x, 'seo_auto_at' => fn($t, $c) => $t->timestamp($c)->nullable()];
    // Main categories and brands already have title / description / keyword columns; sub and child categories (category_nodes) had no SEO columns at all.
    $this->cols('categories', $common);
    $this->cols('brands', $common);
    $this->cols('category_nodes', $common + ['meta_title' => $s, 'meta_description' => $x, 'focus_keyword' => $s, 'meta_keywords' => $s, 'canonical' => $s, 'og_image' => $s, 'noindex' => fn($t, $c) => $t->boolean($c)->default(false)]);
  }
  public function down(): void {}
};
