<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Weekly automatic audit (26 Nov 2026): every Sunday after 07:00 (Dhaka) the system counts the things that quietly go wrong and raises ONE Issues Center item with the numbers. Read-only: it changes nothing. */
class WeeklyAudit {
  public static function run(): array {
    $o = [];
    $c = fn(string $k, callable $f) => $o[$k] = (function () use ($f) { try { return $f(); } catch (\Throwable $e) { return null; } })();
    $c('Open high issues', fn() => Schema::hasTable('deploy_issues') ? DB::table('deploy_issues')->whereIn('status', ['open', 'investigating'])->where('severity', 'high')->count() : null);
    $c('Live products without an image', fn() => DB::table('products')->where('published', true)->where(fn($q) => $q->whereNull('image')->orWhere('image', ''))->count());
    $c('Live products with price 0', fn() => DB::table('products')->where('published', true)->where('price', '<=', 0)->count());
    $c('Live products below the protected margin', function () {
      $n = 0; foreach (DB::table('products')->where('published', true)->where('price', '>', 0)->get() as $p) { $cost = ProfitFloor::base($p); if ($cost > 0 && !ProfitFloor::ok($cost, (float)$p->price)) $n++; } return $n;
    });
    $c('Live products out of stock', fn() => Schema::hasColumn('products', 'stock') ? DB::table('products')->where('published', true)->where('stock', '<=', 0)->count() : null);
    $c('Broken links seen (30 days)', fn() => NotFoundWatch::top(500)->count());
    $c('Delivered Pathao parcels not on an invoice', fn() => count(PathaoSettleCheck::rows(500)));
    $c('Services low / expired / failed', function () { $n = 0; foreach (DB::table('service_accounts')->where('active', true)->get() as $r) if (in_array(ServiceBalances::state($r)[0], ['low', 'expired', 'failed'], true)) $n++; return $n; });
    $c('Orders pending over 2 days', fn() => DB::table('orders')->where('status', 'Pending')->where('created_at', '<', now()->subDays(2))->count());
    return $o;
  }
  public static function tick(): void {
    $d = now('Asia/Dhaka'); if ($d->dayOfWeek !== 0 || $d->hour < 7) return;
    if (!Cache::add('weekly.audit.' . $d->format('o-W'), 1, 8 * 86400)) return;
    $r = self::run(); $bad = 0; $lines = [];
    foreach ($r as $k => $v) { if ($v === null) continue; $lines[] = $k . ': ' . $v; if ($v > 0 && $k !== 'Live products out of stock') $bad++; }
    try { DB::table('settings')->updateOrInsert(['key' => 'weekly_audit_last'], ['value' => json_encode(['at' => now()->toIso8601String(), 'r' => $r], JSON_UNESCAPED_UNICODE)]); } catch (\Throwable $e) {}
    DeployHealth::raise('weekly_audit_' . $d->format('oW'), $bad ? 'medium' : 'low', 'Audit', 'Weekly audit ' . $d->format('d M Y') . ': ' . ($bad ? $bad . ' item(s) need a look' : 'all clear'), implode(' | ', $lines), 'Automatic weekly check', 'Open the items above; each count comes from the live database.');
  }
}
