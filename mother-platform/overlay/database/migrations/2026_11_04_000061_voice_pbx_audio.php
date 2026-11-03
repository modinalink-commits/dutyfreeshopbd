<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// A voice campaign can play a recording that already lives on the PBX (FusionPBX → Recordings) instead of a text-to-speech/uploaded file fetched from the website.
return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('voice_campaigns') && !Schema::hasColumn('voice_campaigns', 'pbx_audio')) Schema::table('voice_campaigns', fn($t) => $t->string('pbx_audio', 300)->nullable());
  }
  public function down(): void {}
};
