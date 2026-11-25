<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Master switch: only order messages the customer needs go out on WhatsApp. Everything automatic and optional is held back. */
class WaEssential {
  /** Always allowed: order confirmation request, shipped / delivered update, cancel notice, replies to customers. */
  const KEEP = ['order_confirm', 'order_update', 'order_cancel', 'reply'];
  public static function on(): bool { try { return Procure::set('wa_essential_only', '1') === '1'; } catch (\Throwable $e) { return true; } }
  /** True when this kind of automatic message must not be sent. */
  public static function blocked(string $kind): bool {
    if (!self::on() || in_array($kind, self::KEEP, true)) return false;
    try { if (Procure::set('wa_blk_log_' . date('Ymd') . $kind, '') === '') { Procure::put('wa_blk_log_' . date('Ymd') . $kind, '1'); } } catch (\Throwable $e) {}
    return true;
  }
  /** Read-only usage numbers for the report. */
  public static function report(int $days = 30): array {
    $since = now()->subDays($days)->toDateTimeString(); $o = ['days' => $days, 'by_tpl' => [], 'by_day' => [], 'total' => 0];
    try {
      foreach (DB::table('wa_messages')->where('direction', 'out')->where('type', 'template')->where('ts', '>=', $since)->selectRaw('template_name, count(*) n, sum(case when status=\'failed\' then 1 else 0 end) f')->groupBy('template_name')->orderByDesc('n')->get() as $r) {
        $cat = DB::table('wa_templates')->where('name', $r->template_name)->value('category');
        $o['by_tpl'][] = ['name' => (string)$r->template_name, 'n' => (int)$r->n, 'failed' => (int)$r->f, 'cat' => (string)$cat]; $o['total'] += (int)$r->n;
      }
    } catch (\Throwable $e) {}
    try { foreach (DB::table('chat_messages')->where('created_at', '>=', $since)->where('direction', 'out')->selectRaw("date(created_at) d, count(*) n")->groupBy('d')->orderByDesc('d')->limit(14)->get() as $r) $o['by_day'][] = [$r->d, (int)$r->n]; } catch (\Throwable $e) {}
    return $o;
  }
}
