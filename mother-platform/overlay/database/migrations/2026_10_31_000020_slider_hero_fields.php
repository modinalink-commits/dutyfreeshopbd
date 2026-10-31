<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::table('sliders', function (Blueprint $t) {
      foreach (['title'=>'string','subtitle'=>'string','button_text'=>'string','mobile_image'=>'string'] as $c=>$ty) if (!Schema::hasColumn('sliders', $c)) $t->string($c)->nullable();
      foreach (['starts_at','ends_at'] as $c) if (!Schema::hasColumn('sliders', $c)) $t->timestamp($c)->nullable();
    });
  }
  public function down(): void {}
};
