<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('products', 'path_slug')) Schema::table('products', function (Blueprint $t) { $t->string('path_slug', 120)->nullable()->index(); });
    \App\Support\Slugs::assignAll();
  }
  public function down(): void {}
};
