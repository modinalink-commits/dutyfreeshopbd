<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
/** Owner request: AI Product Images runs automatically for NEW product images only, with the stricter "no watermark / no logo / standard white 1:1" instruction. Starts now: images added before this migration are never touched automatically. */
return new class extends Migration {
  public function up(): void {
    $put = fn($k, $v) => DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]);
    if (!DB::table('settings')->where('key', 'ai_auto_cutoff')->exists()) $put('ai_auto_cutoff', now()->toDateTimeString());
    foreach (['ai_auto_on' => '1', 'ai_auto_daily_cap' => '60'] as $k => $v) if (!DB::table('settings')->where('key', $k)->exists()) $put($k, $v);
    // the old built-in instruction is replaced by the new default; a custom instruction written by the owner is kept
    $old = <<<'TXT'
Edit only the supplied product photograph. Remove the existing background completely. Preserve the original product exactly: branding, logo, printed text, packaging, shape, proportions and colors. Do not redesign or alter the product. Place the complete product vertically and horizontally centered on a clean pure white e-commerce background. The entire product must remain visible; do not crop any part of the product. Leave balanced white space around the product. Use clean studio lighting. A very subtle realistic shadow may be added under the product. Do not add promotional text, extra products, decorative objects or watermarks. Create a professional standard e-commerce product photograph (1:1 square).
TXT;
    $cur = DB::table('settings')->where('key', 'ai_img_prompt')->value('value');
    if ($cur !== null && trim($cur) === trim($old)) DB::table('settings')->where('key', 'ai_img_prompt')->delete();
  }
  public function down(): void {}
};
