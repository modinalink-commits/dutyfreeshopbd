<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void { DB::table('settings')->where('key', 'ai_img_model')->where('value', 'gpt-image-1')->update(['value'=>'gpt-image-2.5-sunburst']); }
  public function down(): void {}
};
