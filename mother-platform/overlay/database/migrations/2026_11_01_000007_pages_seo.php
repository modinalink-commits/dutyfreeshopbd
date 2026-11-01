<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  private function cols(string $tbl, array $defs): void {
    if (!Schema::hasTable($tbl)) return;
    foreach ($defs as $c => $f) if (!Schema::hasColumn($tbl, $c)) Schema::table($tbl, fn(Blueprint $t) => $f($t, $c));
  }
  public function up(): void {
    $s = fn(Blueprint $t, $c) => $t->string($c, 500)->nullable(); $x = fn(Blueprint $t, $c) => $t->text($c)->nullable();
    $b = fn(Blueprint $t, $c) => $t->boolean($c)->default(false); $ts = fn(Blueprint $t, $c) => $t->timestamp($c)->nullable();
    $this->cols('pages', ['page_type' => fn($t, $c) => $t->string($c, 20)->default('static'), 'status' => fn($t, $c) => $t->string($c, 14)->default('published'), 'short_desc' => $x, 'featured_image' => $s,
      'keywords' => $s, 'canonical' => $s, 'focus_keyword' => $s, 'robots_index' => fn($t, $c) => $t->boolean($c)->default(true), 'robots_follow' => fn($t, $c) => $t->boolean($c)->default(true),
      'og_title' => $s, 'og_description' => $x, 'og_image' => $s, 'twitter_image' => $s, 'publish_at' => $ts, 'unpublish_at' => $ts, 'updated_by' => fn($t, $c) => $t->string($c, 80)->nullable()]);
    $this->cols('categories', ['focus_keyword' => $s, 'canonical' => $s, 'og_image' => $s, 'noindex' => $b]);
    $this->cols('brands', ['meta_title' => $s, 'meta_description' => $x, 'description' => $x, 'canonical' => $s, 'og_image' => $s, 'focus_keyword' => $s, 'noindex' => $b]);
    $this->cols('products', ['focus_keyword' => $s, 'canonical' => $s, 'og_image' => $s, 'noindex' => $b]);
    if (Schema::hasTable('pages')) DB::table('pages')->where('active', false)->where('status', 'published')->update(['status' => 'inactive']);
    if (!Schema::hasTable('seo_redirects')) Schema::create('seo_redirects', function (Blueprint $t) {
      $t->id(); $t->string('from_path', 500)->unique(); $t->string('to_url', 800); $t->unsignedSmallInteger('type')->default(301); $t->boolean('active')->default(true);
      $t->unsignedInteger('hits')->default(0); $t->string('source', 12)->default('manual'); $t->string('created_by', 80)->nullable(); $t->timestamps();
    });
    if (!Schema::hasTable('page_revisions')) Schema::create('page_revisions', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('page_id')->index(); $t->string('title')->nullable(); $t->string('slug')->nullable(); $t->longText('body')->nullable(); $t->text('meta')->nullable(); $t->string('edited_by', 80)->nullable(); $t->timestamps();
    });
  }
  public function down(): void {}
};
