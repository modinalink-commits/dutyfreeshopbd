<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Owner 20 Nov 2026: the neighbouring daily-needs source shop sells every price (0 Tk upwards): it is exempt from the low-price rule (setting low_price_exempt_stores = store ids). */
return new class extends Migration {
  const DOMAIN = 'iqbalstorebd.com';
  private function ids(): array { return array_values(array_filter(array_map('intval', explode(',', (string)DB::table('settings')->where('key', 'low_price_exempt_stores')->value('value'))))); }
  private function sid(): ?int { $s = DB::table('stores')->whereRaw("lower(regexp_replace(regexp_replace(domain, '^https?://(www\\.)?', ''), '/+$', '')) = ?", [self::DOMAIN])->orderBy('id')->first(); return $s ? (int)$s->id : null; }
  public function up(): void {
    if (!Schema::hasTable('settings') || !Schema::hasTable('stores')) return; $sid = $this->sid(); if (!$sid) return;
    $ids = $this->ids(); if (!in_array($sid, $ids, true)) $ids[] = $sid;
    DB::table('settings')->updateOrInsert(['key' => 'low_price_exempt_stores'], ['value' => implode(',', $ids), 'updated_at' => now(), 'created_at' => now()]);
  }
  public function down(): void {
    if (!Schema::hasTable('settings') || !Schema::hasTable('stores')) return; $sid = $this->sid(); if (!$sid) return;
    DB::table('settings')->where('key', 'low_price_exempt_stores')->update(['value' => implode(',', array_diff($this->ids(), [$sid])), 'updated_at' => now()]);
  }
};
