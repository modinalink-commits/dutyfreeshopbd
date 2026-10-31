<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasColumn('sliders', 'type')) Schema::table('sliders', function (Blueprint $t) { $t->string('type', 20)->default('main'); });
  }
  public function down(): void {}
};
