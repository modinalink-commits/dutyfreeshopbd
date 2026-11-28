<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
/** Duty Free Dubai (dutyfreedubai.net): owner approved Live on 28 Nov 2026 as a public catalogue. Orders and payments stay closed (orders_open = false) until the owner opens them. Home title set by the owner. Touches this one channel row only. */
return new class extends Migration {
  public function up(): void {
    if (!Schema::hasTable('sales_channels')) return;
    $c = DB::table('sales_channels')->where('slug', 'duty-free-dubai')->first(); if (!$c) return;
    $s = json_decode((string)$c->settings, true) ?: [];
    $s['home_title'] = 'অরিজিনাল সিগারেট অ্যান্ড ভেপ';
    $s['home_desc'] = 'অরিজিনাল সিগারেট অ্যান্ড ভেপ';
    $s['orders_open'] = false;
    $h = $s['history'] ?? [];
    array_unshift($h, ['at' => now()->toDateTimeString(), 'by' => 'owner approval (Mohammad Faruk, 28 Nov 2026)', 'what' => 'Status ' . $c->status . ' → live as public catalogue; orders and payments closed; home title set']);
    $s['history'] = array_slice($h, 0, 40);
    DB::table('sales_channels')->where('id', $c->id)->update(['status' => 'live', 'active' => true, 'settings' => json_encode($s, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    try { Cache::forget('chan.hosts'); Cache::forget('chan.row.' . $c->id); } catch (\Throwable $e) {}
  }
  public function down(): void {
    if (!Schema::hasTable('sales_channels')) return;
    DB::table('sales_channels')->where('slug', 'duty-free-dubai')->where('status', 'live')->update(['status' => 'preview', 'active' => false, 'updated_at' => now()]);
    try { Cache::forget('chan.hosts'); } catch (\Throwable $e) {}
  }
};
