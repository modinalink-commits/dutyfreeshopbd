<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('sliders', function (Blueprint $t) {
      if (!Schema::hasColumn('sliders', 'media_type')) $t->string('media_type', 10)->default('image');
      if (!Schema::hasColumn('sliders', 'video_url')) $t->string('video_url', 300)->nullable();
      if (!Schema::hasColumn('sliders', 'video_id')) $t->string('video_id', 20)->nullable();
      foreach (['v_autoplay' => true, 'v_mute' => true, 'v_loop' => false, 'v_controls' => false, 'v_mobile' => false] as $c => $d) if (!Schema::hasColumn('sliders', $c)) $t->boolean($c)->default($d);
      if (!Schema::hasColumn('sliders', 'cta_pos')) $t->string('cta_pos', 10)->default('left');
    });
  }
  public function down(): void {}
};
