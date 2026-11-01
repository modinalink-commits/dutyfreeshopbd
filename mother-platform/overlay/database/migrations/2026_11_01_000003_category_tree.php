<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Additive: category tree nodes (sub / sub sub), main category description + homepage flag, brand slug / logo / description. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('category_nodes')) Schema::create('category_nodes', function (Blueprint $t) {
      $t->id(); $t->unsignedBigInteger('category_id')->index(); $t->unsignedBigInteger('parent_id')->nullable()->index(); $t->unsignedSmallInteger('level')->default(2);
      $t->string('name'); $t->string('slug'); $t->string('image')->nullable(); $t->text('description')->nullable();
      $t->integer('position')->default(0); $t->boolean('active')->default(true); $t->boolean('show_home')->default(false); $t->boolean('featured')->default(false); $t->timestamps();
    });
    Schema::table('categories', function (Blueprint $t) {
      if (!Schema::hasColumn('categories', 'description')) $t->text('description')->nullable();
      if (!Schema::hasColumn('categories', 'show_home')) $t->boolean('show_home')->default(true);
    });
    Schema::table('brands', function (Blueprint $t) {
      if (!Schema::hasColumn('brands', 'slug')) $t->string('slug')->nullable();
      if (!Schema::hasColumn('brands', 'logo')) $t->string('logo')->nullable();
      if (!Schema::hasColumn('brands', 'description')) $t->text('description')->nullable();
    });
  }
  public function down(): void {}
};
