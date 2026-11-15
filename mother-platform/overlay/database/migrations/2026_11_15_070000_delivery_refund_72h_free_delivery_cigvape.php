<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Owner instruction 2026-11-15:
 *  - Delivery and refund time are 72 hours (Delivery Policy, Return Policy).
 *  - Free delivery applies only to Cigarette & Vape; every other product pays the location-wise charge. The "free over 1000" claim is removed.
 * Only exact old sentences are replaced (hand-edited text is left alone). Every changed row is backed up first; down() restores it.
 * Prices, delivery charges and the real delivery rules are not touched; only customer-facing text, the display-only "freedelivery" offer rule and a duplicate override row.
 */
return new class extends Migration {
  private function pages(): array {
    return [
      'delivery-policy' => [
        '<li>Dhaka City: Within 24–48 hours</li><li>Outside Dhaka: Within 48–72 hours</li>' => '<li>Delivery within 72 hours after your order is confirmed, anywhere in Bangladesh.</li>',
        'FREE Delivery across Bangladesh on selected orders – no hidden charges.' => 'Free delivery on Cigarette &amp; Vape products. For all other products the delivery charge depends on your location and is shown at checkout before you confirm – no hidden charges.',
      ],
      'return-policy' => [
        "If a replacement is not available, we'll process a full refund via your preferred method." => "If a replacement is not available, we'll process a full refund via your preferred method. Refunds are processed within 72 hours.",
      ],
    ];
  }
  private function log(string $action, string $subject, array $meta = []): void {
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => 'system', 'action' => $action, 'subject' => $subject, 'meta' => json_encode($meta + ['approved_by' => 'Mohammad Faruk'], JSON_UNESCAPED_UNICODE), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
  }
  public function up(): void {
    // 1. Policy pages
    if (Schema::hasTable('pages')) {
      if (!Schema::hasTable('pages_text_backup_72h')) DB::statement('CREATE TABLE pages_text_backup_72h AS SELECT * FROM pages WHERE false');
      foreach ($this->pages() as $slug => $reps) {
        $p = DB::table('pages')->where('slug', $slug)->first(); if (!$p) continue;
        $body = (string)$p->body; $new = $body;
        foreach ($reps as $old => $to) $new = str_replace($old, $to, $new);
        if ($new === $body) continue;
        if (!DB::table('pages_text_backup_72h')->where('id', $p->id)->exists()) DB::statement('INSERT INTO pages_text_backup_72h SELECT * FROM pages WHERE id = ?', [$p->id]);
        DB::table('pages')->where('id', $p->id)->update(['body' => $new, 'updated_at' => now()]);
        $this->log('page.policy_72h_free_delivery', 'Page ' . $slug . ': delivery/refund 72 hours, free delivery only Cigarette & Vape', ['slug' => $slug, 'backup_table' => 'pages_text_backup_72h']);
      }
    }
    // 2. The display-only "free delivery over 1000" offer rule: switched off (real delivery charges come from Delivery settings, not from this rule)
    if (Schema::hasTable('discount_rules')) {
      if (!Schema::hasTable('discount_rules_backup_fd')) DB::statement('CREATE TABLE discount_rules_backup_fd AS SELECT * FROM discount_rules WHERE false');
      $r = DB::table('discount_rules')->where('key', 'freedelivery')->where('enabled', true)->first();
      if ($r) {
        if (!DB::table('discount_rules_backup_fd')->where('id', $r->id)->exists()) DB::statement('INSERT INTO discount_rules_backup_fd SELECT * FROM discount_rules WHERE id = ?', [$r->id]);
        DB::table('discount_rules')->where('id', $r->id)->update(['enabled' => false, 'updated_at' => now()]);
        $this->log('discount_rule.freedelivery_off', 'Offer rule "freedelivery" (free over a set amount) switched off: free delivery is only for Cigarette & Vape', ['backup_table' => 'discount_rules_backup_fd']);
      }
    }
    // 3. Top-strip announcement "FREE Delivery" (exact text only)
    if (Schema::hasTable('announcements')) {
      if (!Schema::hasTable('announcements_backup_fd')) DB::statement('CREATE TABLE announcements_backup_fd AS SELECT * FROM announcements WHERE false');
      foreach (DB::table('announcements')->whereRaw("lower(trim(text)) = 'free delivery'")->get() as $a) {
        if (!DB::table('announcements_backup_fd')->where('id', $a->id)->exists()) DB::statement('INSERT INTO announcements_backup_fd SELECT * FROM announcements WHERE id = ?', [$a->id]);
        DB::table('announcements')->where('id', $a->id)->update(['text' => 'Free Delivery on Cigarette & Vape', 'updated_at' => now()]);
        $this->log('announcement.free_delivery_text', 'Top strip "FREE Delivery" now says "Free Delivery on Cigarette & Vape"', ['id' => $a->id, 'backup_table' => 'announcements_backup_fd']);
      }
    }
    // 4. Duplicate "Single Packet always charged" override rows: keep the oldest, back up the rest
    if (Schema::hasTable('delivery_overrides')) {
      if (!Schema::hasTable('delivery_overrides_backup_dup')) DB::statement('CREATE TABLE delivery_overrides_backup_dup AS SELECT * FROM delivery_overrides WHERE false');
      $seen = [];
      foreach (DB::table('delivery_overrides')->orderBy('id')->get() as $o) {
        $k = implode('|', [$o->kind, (int)$o->ref_id, mb_strtolower((string)$o->ref_name), $o->mode, (string)$o->charge, (string)$o->starts_at, (string)$o->ends_at, (int)$o->active]);
        if (!isset($seen[$k])) { $seen[$k] = $o->id; continue; }
        DB::statement('INSERT INTO delivery_overrides_backup_dup SELECT * FROM delivery_overrides WHERE id = ?', [$o->id]);
        DB::table('delivery_overrides')->where('id', $o->id)->delete();
        $this->log('delivery_override.duplicate_removed', 'Duplicate delivery override #' . $o->id . ' (' . $o->kind . ' ' . $o->ref_name . ' ' . $o->mode . ') removed; kept #' . $seen[$k], ['backup_table' => 'delivery_overrides_backup_dup']);
      }
    }
    try { \App\Support\Delivery::flush(); } catch (\Throwable $e) {}
  }
  public function down(): void {
    if (Schema::hasTable('pages_text_backup_72h')) foreach (DB::table('pages_text_backup_72h')->get() as $b) DB::table('pages')->where('id', $b->id)->update(['body' => $b->body]);
    if (Schema::hasTable('discount_rules_backup_fd')) foreach (DB::table('discount_rules_backup_fd')->get() as $b) DB::table('discount_rules')->where('id', $b->id)->update(['enabled' => $b->enabled]);
    if (Schema::hasTable('announcements_backup_fd')) foreach (DB::table('announcements_backup_fd')->get() as $b) DB::table('announcements')->where('id', $b->id)->update(['text' => $b->text]);
    if (Schema::hasTable('delivery_overrides_backup_dup')) foreach (DB::table('delivery_overrides_backup_dup')->get() as $b) { if (!DB::table('delivery_overrides')->where('id', $b->id)->exists()) DB::table('delivery_overrides')->insert((array)$b); }
    try { \App\Support\Delivery::flush(); } catch (\Throwable $e) {}
  }
};
