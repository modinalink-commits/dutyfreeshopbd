<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * AI Brief - phase 1 (read-only).
 * The NUMBERS are computed here with SQL (never by the model). Claude only reads the facts and writes the Bengali brief.
 * Nothing in this class changes an order, a product or a price. No phone number or address is sent to the API (phones are masked).
 */
class AiBrief
{
  const TARGET = 1000;                              // orders per day (owner's target)
  const BAD = ['Cancel', 'Cancelled', 'Incomplete']; // not a real sale
  const RET = ['Returned', 'Return Pending', 'Return'];

  public static function ready(): bool { try { return Schema::hasTable('ai_briefs'); } catch (\Throwable $e) { return false; } }
  public static function on(): bool { return self::setting('ai_brief_on', '1') === '1'; }
  public static function hour(): int { return max(0, min(23, (int)self::setting('ai_brief_hour', '8'))); }
  public static function model(): string { return Sms::secret('ai_brief_model') ?: 'claude-sonnet-5-5'; }
  public static function setting(string $k, string $d = ''): string { try { $v = DB::table('settings')->where('key', $k)->value('value'); return $v === null ? $d : (string)$v; } catch (\Throwable $e) { return $d; } }
  public static function put(string $k, string $v): void { DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $v, 'updated_at' => now(), 'created_at' => now()]); }

  private static function in(array $a): string { return "'" . implode("','", array_map(fn($x) => str_replace("'", '', $x), $a)) . "'"; }
  private static function mask(?string $p): string { $d = preg_replace('/\D/', '', (string)$p); return strlen($d) >= 3 ? '***' . substr($d, -3) : '***'; }
  private static function safe(callable $f) { try { return $f(); } catch (\Throwable $e) { return ['error' => mb_substr($e->getMessage(), 0, 160)]; } }

  /** All deterministic facts for the brief (Asia/Dhaka days). */
  public static function facts(): array {
    $tz = 'Asia/Dhaka'; $now = now($tz);
    $t0 = $now->copy()->startOfDay()->utc(); $y0 = $t0->copy()->subDay(); $d7 = $t0->copy()->subDays(7); $d14 = $t0->copy()->subDays(14); $d30 = $t0->copy()->subDays(30);
    $f = fn($c) => $c->format('Y-m-d H:i:s');
    $bad = self::in(self::BAD); $ret = self::in(self::RET);
    $out = ['generated_at' => $now->format('d M Y, g:i A') . ' (Dhaka)', 'for_day' => $y0->copy()->timezone($tz)->format('d M Y'), 'target_per_day' => self::TARGET];

    $agg = fn($from, $to) => (array)DB::table('orders')->where('created_at', '>=', $f($from))->where('created_at', '<', $f($to))->selectRaw(
      "count(*) as orders_all, sum(case when status not in ($bad) then 1 else 0 end) as orders, coalesce(sum(case when status not in ($bad) then total end),0) as revenue, " .
      "sum(case when status in ('Cancel','Cancelled') then 1 else 0 end) as cancelled, sum(case when status in ($ret) then 1 else 0 end) as returns")->first();

    $out['orders'] = self::safe(function () use ($agg, $t0, $y0, $d7, $f, $bad, $tz) {
      $yday = $agg($y0, $t0); $today = $agg($t0, now()->addDay()); $w = $agg($d7, $t0);
      $num = fn($a) => array_map(fn($v) => is_numeric($v) ? $v + 0 : $v, $a); $yday = $num($yday); $today = $num($today); $w = $num($w);
      $avg = fn($k) => round(($w[$k] ?? 0) / 7, 1);
      $stat = DB::table('orders')->where('created_at', '>=', $f($y0))->where('created_at', '<', $f($t0))->groupBy('status')->selectRaw('status, count(*) n')->orderByDesc('n')->pluck('n', 'status')->all();
      $src = DB::table('orders')->where('created_at', '>=', $f($y0))->where('created_at', '<', $f($t0))->whereRaw("status not in ($bad)")->groupBy('source')->selectRaw('source, count(*) n')->orderByDesc('n')->limit(8)->pluck('n', 'source')->all();
      $pace = null; $h = now($tz)->hour + now($tz)->minute / 60; if ($h >= 6 && ($today['orders'] ?? 0) > 0) $pace = round(($today['orders'] / $h) * 24);
      return ['yesterday' => $yday, 'today_so_far' => $today, 'avg_per_day_last7' => ['orders' => $avg('orders'), 'revenue' => $avg('revenue'), 'cancelled' => $avg('cancelled'), 'returns' => $avg('returns')],
        'yesterday_vs_target_pct' => round((($yday['orders'] ?? 0) / self::TARGET) * 100, 1), 'today_projected_orders' => $pace, 'yesterday_by_status' => $stat, 'yesterday_by_source' => $src,
        'yesterday_cancel_rate_pct' => ($yday['orders_all'] ?? 0) > 0 ? round($yday['cancelled'] / $yday['orders_all'] * 100, 1) : 0];
    });

    $out['couriers_last14d'] = self::safe(function () use ($d14, $t0, $f, $bad, $ret) {
      return DB::table('orders')->where('created_at', '>=', $f($d14))->whereNotNull('courier')->where('courier', '!=', '')->whereRaw("status not in ($bad)")->groupBy('courier')
        ->selectRaw("courier, count(*) as sent, sum(case when status = 'Delivered' then 1 else 0 end) as delivered, sum(case when status in ($ret) then 1 else 0 end) as returned")->orderByDesc('sent')->limit(8)->get()
        ->map(fn($r) => ['courier' => $r->courier, 'sent' => (int)$r->sent, 'delivered' => (int)$r->delivered, 'returned' => (int)$r->returned, 'return_rate_pct' => $r->sent > 0 ? round($r->returned / $r->sent * 100, 1) : 0])->all();
    });

    $out['stuck_orders'] = self::safe(function () {
      $new = DB::table('orders')->whereIn('status', ['New', 'Pending'])->where('created_at', '<', now()->subHours(24));
      $n = (clone $new)->count();
      $rows = (clone $new)->orderBy('created_at')->limit(8)->get(['invoice', 'created_at', 'total'])->map(fn($r) => ['invoice' => $r->invoice, 'hours_old' => (int)round(abs(\Carbon\Carbon::parse($r->created_at)->diffInMinutes(now())) / 60), 'total' => (float)$r->total])->all();
      $ship = DB::table('orders')->where('status', 'Shipment')->whereRaw('coalesce(shipment_at, updated_at) < ?', [now()->subDays(5)->format('Y-m-d H:i:s')]);
      $ns = (clone $ship)->count();
      $sr = (clone $ship)->orderByRaw('coalesce(shipment_at, updated_at)')->limit(8)->get(['invoice', 'courier'])->map(fn($r) => ['invoice' => $r->invoice, 'courier' => $r->courier])->all();
      return ['new_or_pending_over_24h' => $n, 'examples' => $rows, 'in_shipment_over_5_days' => $ns, 'shipment_examples' => $sr];
    });

    $out['risky_orders_last24h'] = self::safe(function () use ($bad, $f) {
      $from = $f(now()->subDay()); $r = [];
      $dups = DB::table('orders')->where('created_at', '>=', $from)->whereRaw("status not in ($bad)")->groupBy('phone')->havingRaw('count(*) >= 2')->selectRaw('phone, count(*) n, string_agg(invoice, \',\') inv')->limit(8)->get();
      $r['same_phone_ordered_2plus_times'] = $dups->map(fn($d) => ['phone' => self::mask($d->phone), 'orders' => (int)$d->n, 'invoices' => explode(',', $d->inv)])->all();
      $avg = (float)DB::table('orders')->where('created_at', '>=', $f(now()->subDays(30)))->whereRaw("status not in ($bad)")->avg('total');
      $r['avg_order_value_30d'] = round($avg);
      $thr = max(5000, $avg * 3);
      $r['unusually_large_orders'] = DB::table('orders')->where('created_at', '>=', $from)->whereRaw("status not in ($bad)")->where('total', '>', $thr)->orderByDesc('total')->limit(6)->get(['invoice', 'total'])->map(fn($o) => ['invoice' => $o->invoice, 'total' => (float)$o->total])->all();
      $r['short_address_new_orders'] = DB::table('orders')->where('created_at', '>=', $from)->whereIn('status', ['New', 'Pending'])->whereRaw("length(trim(coalesce(address,''))) < 10")->limit(8)->pluck('invoice')->all();
      if (Schema::hasColumn('customers', 'blocked')) $r['orders_from_blocked_customers'] = DB::table('orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')->where('o.created_at', '>=', $from)->where('c.blocked', true)->limit(8)->pluck('o.invoice')->all();
      return $r;
    });

    $out['stock'] = self::safe(function () use ($d7, $f, $bad) {
      $sold = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereRaw("o.status not in ($bad)")->where('o.created_at', '>=', $f($d7))->whereNotNull('i.product_id')->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(i.qty) as q')->pluck('q', 'pid')->all();
      $ids = array_keys($sold);
      $prods = $ids ? DB::table('products')->whereIn('id', $ids)->get(['id', 'name', 'stock', 'published', 'category_id'])->keyBy('id') : collect();
      $cover = []; $need = [];
      foreach ($sold as $pid => $q) {
        $p = $prods[$pid] ?? null; if (!$p || !$p->published) continue; $st = (int)($p->stock ?? 0); $q = (int)$q;
        if ($st <= 0 && $q > 0) $need[] = ['product' => $p->name, 'sold_7d' => $q, 'state' => StockGuard::mode($p->category_id) === 'STOCK_CONTROLLED' ? 'Out of Stock' : 'Sourcing Required'];
        elseif ($st > 0 && $q >= 5) { $days = $st / ($q / 7); if ($days < 7) $cover[] = ['product' => $p->name, 'stock' => $st, 'sold_7d' => $q, 'days_left' => round($days, 1)]; }
      }
      usort($cover, fn($a, $b) => $a['days_left'] <=> $b['days_left']); usort($need, fn($a, $b) => $b['sold_7d'] <=> $a['sold_7d']);
      $ci = StockGuard::controlledIds();
      $zero = $ci ? DB::table('products')->where('published', true)->whereRaw('coalesce(stock,0) <= 0')->whereIn('category_id', $ci)->count() : 0;
      return ['will_run_out_within_7_days' => array_slice($cover, 0, 12), 'demand_but_zero_stock' => array_slice($need, 0, 12), 'stock_controlled_published_at_zero' => (int)$zero,
        'published_without_price' => (int)DB::table('products')->where('published', true)->whereRaw('coalesce(price,0) <= 0')->count(),
        'published_without_image' => (int)DB::table('products')->where('published', true)->whereRaw("coalesce(image,'') = ''")->count(),
        'published_total' => (int)DB::table('products')->where('published', true)->count()];
    });

    $out['top_products_7d'] = self::safe(function () use ($d7, $d14, $f, $bad) {
      $q = fn($a, $b) => DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->whereRaw("o.status not in ($bad)")->where('o.created_at', '>=', $f($a))->where('o.created_at', '<', $f($b))->whereNotNull('i.product_id')->groupBy('i.product_id')->selectRaw('i.product_id as pid, sum(i.qty) as q, count(distinct o.id) as o')->orderByDesc('q');
      $now = $q($d7, now()->addDay())->limit(10)->get(); $prev = $q($d14, $d7)->get()->keyBy('pid');
      $names = DB::table('products')->whereIn('id', $now->pluck('pid'))->pluck('name', 'id');
      return $now->map(fn($r) => ['product' => $names[$r->pid] ?? ('#' . $r->pid), 'qty_7d' => (int)$r->q, 'orders_7d' => (int)$r->o, 'qty_previous_7d' => (int)($prev[$r->pid]->q ?? 0)])->all();
    });

    $out['customers'] = self::safe(function () use ($y0, $t0, $f, $bad) {
      $ph = DB::table('orders')->where('created_at', '>=', $f($y0))->where('created_at', '<', $f($t0))->whereRaw("status not in ($bad)")->distinct()->pluck('phone')->all();
      $repeat = $ph ? DB::table('orders')->whereIn('phone', $ph)->where('created_at', '<', $f($y0))->whereRaw("status not in ($bad)")->distinct()->count('phone') : 0;
      $lapsed = DB::table('orders')->whereRaw("status not in ($bad)")->groupBy('phone')->havingRaw('count(*) >= 2 and max(created_at) between ? and ?', [$f(now()->subDays(120)), $f(now()->subDays(45))])->selectRaw('phone')->get()->count();
      $inc = Schema::hasTable('incomplete_orders') ? [
        'abandoned_yesterday' => (int)DB::table('incomplete_orders')->where('created_at', '>=', $f($y0))->where('created_at', '<', $f($t0))->count(),
        'abandoned_value_yesterday' => (float)DB::table('incomplete_orders')->where('created_at', '>=', $f($y0))->where('created_at', '<', $f($t0))->sum('total'),
        'open_last_7_days' => (int)DB::table('incomplete_orders')->where('status', 'open')->where('created_at', '>=', $f(now()->subDays(7)))->count()] : null;
      return ['customers_ordered_yesterday' => count($ph), 'of_which_repeat_customers' => (int)$repeat, 'repeat_share_pct' => count($ph) ? round($repeat / count($ph) * 100, 1) : 0, 'lapsed_45_to_120_days_2plus_orders' => (int)$lapsed, 'incomplete_orders' => $inc];
    });
    return $out;
  }

  /** Plain Bengali brief written without the API (used when the API is not available, so a brief always exists). */
  public static function fallbackText(array $F): string {
    $o = $F['orders'] ?? []; $y = $o['yesterday'] ?? [];
    $L = ['⚠ AI বিশ্লেষণ পাওয়া যায়নি — নিচে শুধু সিস্টেমের সংখ্যা।', ''];
    if ($y) { $L[] = 'গতকাল (' . ($F['for_day'] ?? '') . '): ' . (int)($y['orders'] ?? 0) . ' টি অর্ডার, বিক্রি ' . number_format((float)($y['revenue'] ?? 0)) . ' TK, ক্যান্সেল ' . (int)($y['cancelled'] ?? 0) . ', রিটার্ন ' . (int)($y['returns'] ?? 0) . '।';
      $L[] = 'লক্ষ্যমাত্রা ' . self::TARGET . ' এর ' . ($o['yesterday_vs_target_pct'] ?? 0) . '%; গত ৭ দিনের গড় ' . ($o['avg_per_day_last7']['orders'] ?? 0) . ' অর্ডার/দিন।'; }
    $s = $F['stock'] ?? []; if (!empty($s['will_run_out_within_7_days'])) $L[] = 'স্টক ৭ দিনের মধ্যে শেষ হতে পারে: ' . count($s['will_run_out_within_7_days']) . ' টি পণ্য।';
    if (!empty($s['demand_but_zero_stock'])) $L[] = 'চাহিদা আছে কিন্তু স্টক শূন্য: ' . count($s['demand_but_zero_stock']) . ' টি পণ্য।';
    $st = $F['stuck_orders'] ?? []; if (!empty($st['new_or_pending_over_24h'])) $L[] = '২৪ ঘণ্টার বেশি New/Pending অর্ডার: ' . $st['new_or_pending_over_24h'] . ' টি।';
    return implode("\n", $L);
  }

  private static function system(): string {
    return "তুমি DutyFreeBD (dutyfreebd.com, বাংলাদেশের অনলাইন স্টোর) এর মালিক মোহাম্মদ ফারুকের জন্য ব্যবসায়িক বিশ্লেষক। তোমাকে সিস্টেমের হিসাব করা FACTS (JSON) দেওয়া হবে।\n"
      . "নিয়ম:\n1) পুরো উত্তর সহজ বাংলায় (সংখ্যা ইংরেজিতে), প্লেইন টেক্সট, মার্কডাউন টেবিল/কোড নয়। ছোট শিরোনাম + বুলেট (•) ব্যবহার করো।\n"
      . "2) শুধু FACTS-এর সংখ্যা ব্যবহার করো। কোনো সংখ্যা নিজে বানাবে না, অনুমান করে লিখবে না। কোনো অংশে error বা তথ্য না থাকলে 'তথ্য পাওয়া যায়নি' লেখো।\n"
      . "3) কারণ অনুমান করলে 'সম্ভাব্য কারণ:' লিখে স্পষ্ট করো যে এটি অনুমান।\n4) ফোন নম্বর, ঠিকানা চাইবে না, লিখবেও না। ইনভয়েস নম্বর লিখতে পারো।\n5) তুমি কিছু বদলাতে পারো না; শুধু সুপারিশ করো। টাকা, ডিলিট, বাল্ক এডিট, দাম বদলের কাজ মালিকের সিদ্ধান্ত বলে উল্লেখ করো।\n"
      . "কাঠামো: (১) এক লাইনে সারসংক্ষেপ (২) অর্ডার ও লক্ষ্যমাত্রা (৩) ঝুঁকি/সন্দেহজনক অর্ডার ও আটকে থাকা অর্ডার (৪) স্টক (৫) কাস্টমার আচরণ (৬) আজকের ৫টি করণীয় (গুরুত্ব অনুযায়ী, প্রতিটি কার ও কী)। মোট ৪৫০ শব্দের মধ্যে।";
  }

  /** Generate and store one brief. Returns the saved row. */
  public static function generate(string $kind = 'daily', ?string $by = null): ?object {
    if (!self::ready()) return null;
    @set_time_limit(120);
    $F = self::facts(); $key = Sms::secret('ai_key'); $body = null; $status = 'ok'; $err = null; $in = 0; $outT = 0; $model = self::model();
    if (!$key) { $status = 'fallback'; $err = 'Claude API key is not set'; }
    else {
      try {
        $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])->timeout(90)->post('https://api.anthropic.com/v1/messages', ['model' => $model, 'max_tokens' => 2000, 'system' => self::system(),
          'messages' => [['role' => 'user', 'content' => "FACTS:\n" . json_encode($F, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]]);
        if ($res->ok()) { $body = trim(collect($res->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n")); $in = (int)$res->json('usage.input_tokens'); $outT = (int)$res->json('usage.output_tokens'); if ($body === '') { $status = 'fallback'; $err = 'empty reply'; } }
        else { $status = 'fallback'; $err = 'HTTP ' . $res->status() . ' ' . mb_substr((string)($res->json('error.message') ?? ''), 0, 200); }
      } catch (\Throwable $e) { $status = 'fallback'; $err = 'network: ' . mb_substr($e->getMessage(), 0, 160); }
    }
    if ($status !== 'ok' || !$body) $body = self::fallbackText($F);
    $id = DB::table('ai_briefs')->insertGetId(['kind' => $kind, 'for_date' => now('Asia/Dhaka')->toDateString(), 'facts' => json_encode($F, JSON_UNESCAPED_UNICODE), 'body' => $body, 'model' => $model, 'status' => $status, 'error' => $err,
      'tokens_in' => $in, 'tokens_out' => $outT, 'made_by' => $by ?: 'system', 'created_at' => now(), 'updated_at' => now()]);
    try { DB::table('audit_logs')->insert(['user_id' => null, 'user_name' => $by ?: 'AI Brief', 'action' => 'AI Brief generated', 'subject' => $kind . ' · ' . $status . ($err ? ' · ' . mb_substr($err, 0, 120) : ''), 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable $e) {}
    return DB::table('ai_briefs')->find($id);
  }

  /** Called by the background worker every ~30 s: one daily brief after the chosen hour (Dhaka). */
  public static function tick(): void {
    if (!self::ready() || !self::on()) return;
    $n = now('Asia/Dhaka'); if ($n->hour < self::hour()) return;
    $q = DB::table('ai_briefs')->where('kind', 'daily')->where('for_date', $n->toDateString());
    if ((clone $q)->where('status', 'ok')->exists() || (clone $q)->count() >= 3) return; // done, or the API kept failing (3 tries, 10 minutes apart)
    if (!Cache::add('ai_brief.lock', 1, 600)) return; // one process at a time, and no retry storm when the API is down (retry after 10 min)
    try { self::generate('daily'); } finally { /* the lock expires on its own */ }
  }
}
