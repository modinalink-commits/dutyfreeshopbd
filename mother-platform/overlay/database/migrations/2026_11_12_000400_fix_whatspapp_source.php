<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** "Whatspapp" was a typo of the order source "Whatsapp": merge it so channel filters and reports count them together. */
return new class extends Migration {
  public function up(): void {
    DB::table('orders')->where('source', 'Whatspapp')->update(['source' => 'Whatsapp']);
    if (Schema::hasTable('order_sources')) { $c = Schema::hasColumn('order_sources', 'name') ? 'name' : null; if ($c) DB::table('order_sources')->where($c, 'Whatspapp')->delete(); }
  }
  public function down(): void {}
};
