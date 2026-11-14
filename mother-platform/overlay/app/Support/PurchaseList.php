<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Daily purchase list for the purchase manager (owner request, 14 Nov 2026): what to buy today, how it is selling, as a PDF sent on WhatsApp at a fixed time.
 *  Every number comes from the database (stock, order lines, purchase lines). The WhatsApp message carries a private link to the PDF that stops working after four days. */
class PurchaseList {
  const API = 'https://graph.facebook.com/v21.0';
  const BODY = "আসসালামু আলাইকুম {{1}},\nআজকের ক্রয়-তালিকা তৈরি হয়েছে ({{2}})।\n\nজরুরি কিনতে হবে: {{3}}টি পণ্য\nস্টক একদম শেষ: {{4}}টি পণ্য\n\nকোন পণ্য কত দরকার আর কেমন বিক্রি হচ্ছে, বিস্তারিত নিচের PDF-এ দেখুন।";
  const BAD = ['Cancel', 'Cancelled', 'Returned', 'Return'];

  public static function set(string $k, string $d = ''): string { return Procure::set('purchase_list_' . $k, $d); }
  public static function tpl(): string { return self::set('tpl', 'daily_purchase_list'); }
  public static function on(): bool { return self::set('enabled', '1') === '1'; }
  public static function sendAt(): string { $t = trim(self::set('send_at', '12:00')); return preg_match('/^\d{1,2}:\d{2}$/', $t) ? $t : '12:00'; }
  public static function days(): int { return max(3, min(30, (int)self::set('cover_days', '7'))); }
  /** [[name, 8801XXXXXXXXX], ...] from the "Name|phone" lines in settings. */
  public static function recipients(): array {
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', self::set('to', "Mamun|01824522800\nFaruk|01884808080")) as $l) {
      [$n, $p] = array_pad(array_map('trim', explode('|', $l, 2)), 2, ''); $wa = WhatsApp::norm($p);
      if (preg_match('/^8801[3-9]\d{8}$/', $wa)) $out[] = [$n !== '' ? $n : 'Team', $wa];
    }
    return $out;
  }

  /** The figures behind the list. */
  public static function data(): array {
    $cover = self::days(); $since30 = now()->subDays(30); $since7 = now()->subDays(7);
    $sold = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereNotNull('i.product_id')->where('o.created_at', '>=', $since30)->whereNotIn('o.status', self::BAD)
      ->groupBy('i.product_id')->selectRaw('i.product_id, sum(i.qty) q30, sum(case when o.created_at >= ? then i.qty else 0 end) q7', [$since7])->get()->keyBy('product_id');
    $ids = $sold->keys()->all();
    $prods = $ids ? DB::table('products')->whereIn('id', $ids)->get(['id', 'name', 'sku', 'stock', 'published'])->keyBy('id') : collect();
    $cost = []; $sup = [];
    if ($ids) {
      $last = DB::table('purchase_items as pi')->join('purchases as pu', 'pu.id', '=', 'pi.purchase_id')->leftJoin('suppliers as s', 's.id', '=', 'pu.supplier_id')->whereIn('pi.product_id', $ids)->where('pi.cost', '>', 0)
        ->whereRaw('pi.id in (select max(x.id) from purchase_items x where x.product_id = pi.product_id and x.cost > 0 group by x.product_id)')->get(['pi.product_id', 'pi.cost', 's.name']);
      foreach ($last as $l) { $cost[$l->product_id] = (float)$l->cost; $sup[$l->product_id] = (string)$l->name; }
    }
    $urgent = []; $out = 0;
    foreach ($sold as $pid => $s) {
      $p = $prods[$pid] ?? null; if (!$p) continue; $stock = (int)$p->stock; $q30 = (int)$s->q30; $q7 = (int)$s->q7; if ($q30 <= 0) continue;
      $perDay = max($q30 / 30, $q7 / 7); if ($perDay <= 0) continue; // the faster of the two speeds, so a product that just took off is not missed
      $left = $stock <= 0 ? 0 : $stock / $perDay;
      if ($left > $cover) continue;
      if ($stock <= 0) $out++;
      $urgent[] = ['name' => (string)$p->name, 'sku' => (string)($p->sku ?? ''), 'stock' => $stock, 'q7' => $q7, 'q30' => $q30, 'left' => $left, 'buy' => max(1, (int)ceil($perDay * 15) - max(0, $stock)), 'cost' => $cost[$pid] ?? null, 'sup' => $sup[$pid] ?? ''];
    }
    usort($urgent, fn($a, $b) => [$a['left'], -$a['q7']] <=> [$b['left'], -$b['q7']]);
    $top = $sold->sortByDesc('q7')->filter(fn($s) => (int)$s->q7 > 0)->take(15)->map(function ($s) use ($prods) { $p = $prods[$s->product_id] ?? null; return $p ? ['name' => (string)$p->name, 'q7' => (int)$s->q7, 'q30' => (int)$s->q30, 'stock' => (int)$p->stock] : null; })->filter()->values()->all();
    $dead = DB::table('products')->where('stock', '>', 0)->when($ids, fn($q) => $q->whereNotIn('id', $ids))->orderByDesc('stock')->limit(15)->get(['name', 'stock'])->map(fn($p) => ['name' => (string)$p->name, 'stock' => (int)$p->stock])->all();
    return ['urgent' => array_slice($urgent, 0, 120), 'urgent_total' => count($urgent), 'out' => $out, 'top' => $top, 'dead' => $dead, 'cover' => $cover, 'at' => now('Asia/Dhaka')];
  }

  public static function pdf(array $d): string {
    $when = $d['at']->format('d M Y, h:i A'); $p = new TinyPdf('DutyFreeBD · Daily Purchase List · ' . $when . ' · Internal use only');
    $p->y = TinyPdf::H - 46; $p->text($p->m, $p->y, 'DutyFreeBD', 17, true, [0.976, 0.451, 0.086]); $p->text($p->m + 104, $p->y, 'Daily Purchase List', 17, true);
    $p->y -= 15; $p->text($p->m, $p->y, $when . '   |   Buy now: ' . $d['urgent_total'] . ' products   |   Out of stock: ' . $d['out'], 9, false, [0.29, 0.33, 0.39]);
    $p->y -= 6; $p->line($p->m, $p->y, TinyPdf::W - $p->m, $p->y, [0.976, 0.451, 0.086]);
    $p->heading('1. Buy now (stock will not last ' . $d['cover'] . ' days)');
    if (!$d['urgent']) $p->note('Nothing urgent today: every selling product has more than ' . $d['cover'] . ' days of stock.');
    else {
      $rows = []; $tint = [];
      foreach ($d['urgent'] as $i => $u) { $rows[] = [$i + 1, $u['name'], $u['stock'], $u['q7'], $u['q30'], $u['stock'] <= 0 ? 'OUT' : number_format($u['left'], 1), $u['buy'], $u['cost'] !== null ? number_format($u['cost']) : '-', $u['sup']]; if ($u['stock'] <= 0) $tint[$i] = [1, 0.95, 0.95]; }
      $p->table([['#', 22, 'L'], ['Product', 196, 'L'], ['Stock', 36, 'R'], ['Sold 7d', 40, 'R'], ['Sold 30d', 44, 'R'], ['Days left', 44, 'R'], ['Buy qty', 40, 'R'], ['Last cost', 48, 'R'], ['Supplier', 53, 'L']], $rows, $tint);
      $p->note('Days left = stock / daily sale (faster of the 7-day and 30-day speed). Buy qty covers 15 days of sale. Red rows are out of stock.');
      if ($d['urgent_total'] > count($d['urgent'])) $p->note('Showing the ' . count($d['urgent']) . ' most urgent of ' . $d['urgent_total'] . '.');
    }
    $p->heading('2. Best sellers, last 7 days');
    if ($d['top']) $p->table([['#', 22, 'L'], ['Product', 330, 'L'], ['Sold 7d', 56, 'R'], ['Sold 30d', 58, 'R'], ['Stock now', 57, 'R']], array_map(fn($t, $i) => [$i + 1, $t['name'], $t['q7'], $t['q30'], $t['stock']], $d['top'], array_keys($d['top'])));
    else $p->note('No sales recorded in the last 7 days.');
    $p->heading('3. Do not buy: in stock but no sale in 30 days');
    if ($d['dead']) $p->table([['#', 22, 'L'], ['Product', 430, 'L'], ['Stock', 71, 'R']], array_map(fn($t, $i) => [$i + 1, $t['name'], $t['stock']], $d['dead'], array_keys($d['dead'])));
    else $p->note('None.');
    return $p->output();
  }

  private static function dir(): string { $d = storage_path('app/purchase-lists'); if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
  /** Build today's file and return [token, data]. The token is the only way to open the file. */
  public static function make(): array {
    $d = self::data(); $tok = bin2hex(random_bytes(20)); file_put_contents(self::dir() . '/' . $tok . '.pdf', self::pdf($d));
    foreach (glob(self::dir() . '/*.pdf') ?: [] as $f) if (filemtime($f) < time() - 4 * 86400) @unlink($f); // old lists disappear
    return [$tok, $d];
  }
  public static function path(string $tok): ?string { if (!preg_match('/^[0-9a-f]{40}$/', $tok)) return null; $f = self::dir() . '/' . $tok . '.pdf'; return is_file($f) && filemtime($f) > time() - 4 * 86400 ? $f : null; }

  public static function call(string $to, string $name, array $d, string $tok): array {
    $t = Chat::sec('chat_wa_token'); $pid = Chat::sec('chat_wa_phone_id'); if (!$t || !$pid) return [false, 'WhatsApp is not connected.'];
    try {
      $r = Http::timeout(20)->withToken($t)->post(self::API . '/' . $pid . '/messages', ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'template', 'template' => ['name' => self::tpl(), 'language' => ['code' => 'bn'], 'components' => [
        ['type' => 'body', 'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => (string)$v], [$name, $d['at']->format('d M Y'), $d['urgent_total'], $d['out']])],
        ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $tok]]]]]]);
      if ($r->successful()) return [true, (string)$r->json('messages.0.id')];
      return [false, mb_substr('(' . (int)$r->json('error.code') . ') ' . ($r->json('error.error_data.details') ?: $r->json('error.message') ?: $r->body()), 0, 250)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . mb_substr($e->getMessage(), 0, 120)]; }
  }
  /** Send today's list to everyone on the list (or to one number for a test). Returns a line per recipient. */
  public static function send(?array $only = null): array {
    [$tok, $d] = self::make(); $res = [];
    foreach ($only ?? self::recipients() as [$name, $wa]) { [$ok, $m] = self::call($wa, $name, $d, $tok); $res[] = $name . ' …' . substr($wa, -4) . ': ' . ($ok ? 'sent' : 'failed · ' . $m); }
    try { DB::table('audit_logs')->insert(['user_id' => auth()->id(), 'user_name' => auth()->user()->name ?? 'System', 'action' => 'purchase.daily_list', 'subject' => 'Daily purchase list: ' . $d['urgent_total'] . ' to buy, ' . $d['out'] . ' out · ' . implode('; ', $res), 'ip' => null, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return $res;
  }
  /** Worker: once a day, at the set Dhaka time, when Meta has approved the template. */
  public static function tick(): void {
    try {
      if (!self::on() || self::set('tpl_status', 'NOT SUBMITTED') !== 'APPROVED') { if (self::set('tpl_status', '') === 'PENDING' && Cache::add('pl_sync', 1, 600)) self::syncTemplate(); return; }
      $now = now('Asia/Dhaka'); $day = $now->format('Ymd'); [$h, $m] = array_map('intval', explode(':', self::sendAt()));
      if ($now->hour * 60 + $now->minute < $h * 60 + $m || self::set('sent_day', '') === $day) return;
      if (!Cache::add('pl_send_' . $day, 1, 3600)) return;
      Procure::put('purchase_list_sent_day', $day); self::send();
    } catch (\Throwable $e) { report($e); }
  }

  public static function submitTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(25)->withToken($t)->post(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::tpl(), 'language' => 'bn', 'category' => 'UTILITY', 'components' => [
        ['type' => 'BODY', 'text' => self::BODY, 'example' => ['body_text' => [['মামুন', '14 Nov 2026', '18', '4']]]],
        ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'PDF খুলুন', 'url' => url('/pl') . '/{{1}}', 'example' => [url('/pl') . '/' . str_repeat('a1', 20)]]]]]]);
      if ($r->successful()) { Procure::put('purchase_list_tpl_status', strtoupper((string)$r->json('status', 'PENDING'))); return [true, 'Submitted: ' . $r->json('status', 'PENDING')]; }
      return [false, mb_substr((string)($r->json('error.error_user_msg') ?: $r->json('error.message') ?: $r->body()), 0, 300)];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
  public static function syncTemplate(): array {
    $t = Chat::sec('chat_wa_token'); if (!$t) return [false, 'WhatsApp token missing'];
    try {
      $r = Http::timeout(20)->withToken($t)->get(self::API . '/' . OrderWaConfirm::waba() . '/message_templates', ['name' => self::tpl(), 'fields' => 'name,status,language,rejected_reason,category']);
      if (!$r->successful()) return [false, mb_substr((string)($r->json('error.message') ?: $r->body()), 0, 300)];
      $row = collect($r->json('data', []))->first(fn($x) => ($x['name'] ?? '') === self::tpl());
      $s = $row ? strtoupper((string)$row['status']) : 'NOT_FOUND'; Procure::put('purchase_list_tpl_status', $s);
      return [true, $s . (!empty($row['rejected_reason']) && $row['rejected_reason'] !== 'NONE' ? ' (' . $row['rejected_reason'] . ')' : '')];
    } catch (\Throwable $e) { return [false, 'Network error: ' . $e->getMessage()]; }
  }
}
