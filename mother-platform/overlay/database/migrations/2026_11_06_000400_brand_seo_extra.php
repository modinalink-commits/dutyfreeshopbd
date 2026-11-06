<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('brands')) return;
    foreach (['short_description' => 'text', 'seo_content' => 'text'] as $c => $t) if (!Schema::hasColumn('brands', $c)) Schema::table('brands', fn(Blueprint $b) => $b->text($c)->nullable());
    if (!Schema::hasColumn('brands', 'nofollow')) Schema::table('brands', fn(Blueprint $b) => $b->boolean('nofollow')->default(false));
    if (!Schema::hasColumn('brands', 'updated_at')) Schema::table('brands', fn(Blueprint $b) => $b->timestamp('updated_at')->nullable());
  }
  public function down(): void {}
};
