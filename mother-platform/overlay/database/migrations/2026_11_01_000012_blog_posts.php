<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('posts')) return;
    $f = base_path('database/data/blog_posts.json'); if (!is_file($f)) return;
    $i = 0;
    foreach ((array)json_decode(file_get_contents($f), true) as $p) {
      if (empty($p['slug']) || DB::table('posts')->where('slug', $p['slug'])->exists()) continue;
      DB::table('posts')->insert(['title' => $p['title'], 'slug' => $p['slug'], 'featured_image' => null, 'excerpt' => $p['excerpt'], 'body' => $p['body'], 'meta_title' => $p['meta_title'], 'meta_description' => $p['meta_description'],
        'active' => true, 'published_at' => now()->subMinutes($i++), 'created_at' => now(), 'updated_at' => now()]);
    }
    \Illuminate\Support\Facades\Cache::forget('store.sitemap5');
  }
  public function down(): void {}
};
