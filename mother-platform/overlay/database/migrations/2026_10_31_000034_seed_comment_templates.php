<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('order_comments')) return;
    foreach (\App\Support\CommentTemplates::defaults() as $kind => $list) foreach ($list as $t) {
      if (!DB::table('order_comments')->where('text', $t)->exists()) DB::table('order_comments')->insert(['text'=>$t, 'kind'=>$kind, 'created_at'=>now(), 'updated_at'=>now()]);
    }
  }
  public function down(): void {}
};
