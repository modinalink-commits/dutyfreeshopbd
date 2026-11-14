<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner instruction 2026-11-15: make the public policy text match the real rule.
 * Real rule: most products are Cash on Delivery; categories marked prepaid_only (product page shows "Prepaid Orders") need full advance payment.
 * Only exact old sentences are replaced; anything already edited by hand is left alone. Old page bodies are backed up first.
 */
return new class extends Migration {
  private function map(): array {
    return [
      'about-us' => [
        'with no advance payment required — you pay Cash on Delivery.' => 'with Cash on Delivery on most products. Products marked “Prepaid Orders” on their page need full advance payment.',
        'We never ask for advance payment — your trust and safety are our top priorities.' => 'Advance payment is asked only for products marked “Prepaid Orders” on their page; everything else is Cash on Delivery — your trust and safety are our top priorities.',
      ],
      'return-policy' => [
        'Note: We do not accept advance payments. All purchases are delivered Cash on Delivery (COD) to ensure your safety and confidence.' => 'Note: Most products are delivered Cash on Delivery (COD). Products marked “Prepaid Orders” on their page need full advance payment before dispatch.',
      ],
      'delivery-policy' => [
        'No advance payment required – we only offer Cash on Delivery (COD).' => 'Cash on Delivery (COD) on most products. Products marked “Prepaid Orders” on their page need full advance payment.',
      ],
    ];
  }
  public function up(): void {
    if (!Schema::hasTable('pages')) return;
    if (!Schema::hasTable('pages_text_backup')) DB::statement('CREATE TABLE pages_text_backup AS SELECT * FROM pages WHERE false');
    foreach ($this->map() as $slug => $reps) {
      $p = DB::table('pages')->where('slug', $slug)->first(); if (!$p) continue;
      $body = (string)$p->body; $new = $body;
      foreach ($reps as $old => $to) $new = str_replace($old, $to, $new);
      if ($new === $body) continue;
      if (!DB::table('pages_text_backup')->where('id', $p->id)->exists()) DB::statement('INSERT INTO pages_text_backup SELECT * FROM pages WHERE id = ?', [$p->id]);
      DB::table('pages')->where('id', $p->id)->update(['body' => $new, 'updated_at' => now()]);
      try { DB::table('audit_logs')->insert(['user_id'=>null,'user_name'=>'system','action'=>'page.policy_text_aligned','subject'=>'Page '.$slug.': advance/COD wording aligned with prepaid-only rule','meta'=>json_encode(['slug'=>$slug,'backup_table'=>'pages_text_backup','approved_by'=>'Mohammad Faruk'], JSON_UNESCAPED_UNICODE),'ip'=>null,'created_at'=>now(),'updated_at'=>now()]); } catch (\Throwable $e) {}
    }
  }
  public function down(): void {
    if (!Schema::hasTable('pages_text_backup')) return;
    foreach (DB::table('pages_text_backup')->get() as $b) DB::table('pages')->where('id', $b->id)->update(['body' => $b->body]);
  }
};
