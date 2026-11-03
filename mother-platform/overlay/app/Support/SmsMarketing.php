<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
/** Central SMS marketing engine: audiences, templates, cost, campaign runs and automations. */
class SmsMarketing {
  const SEGMENTS = ['vip'=>'VIP','regular'=>'Regular','new'=>'New','returning'=>'Returning','high_value'=>'High Value','membership'=>'Membership Customers','inactive'=>'Inactive Customers','has_orders'=>'Customers with Previous Orders','no_recent'=>'Customers with No Recent Orders','abandoned'=>'Abandoned / Incomplete Orders','all'=>'All Customers'];
  const CATS = ['Offer','Coupon','Membership','Order Confirmation','Payment','Delivery','Return','Follow-up','Promotional','Custom'];
  const VARS = ['customer_name','order_id','invoice','total','discount','coupon_code','membership','delivery_status','tracking_id','expiry_date','checkout_link'];
  public static function set(string $k, string $d = ''): string { $v = DB::table('settings')->where('key', $k)->value('value'); return $v === null ? $d : (string)$v; }
  public static function unit(): float { return max(0, (float)self::set('sms_unit_cost', '0.50')); }

  /** [parts, chars, unicode] */
  public static function parts(string $s): array {
    $n = mb_strlen($s); $uni = (bool)preg_match('/[^\x00-\x7F]/u', $s);
    if ($n === 0) return [0, 0, $uni];
    $one = $uni ? 70 : 160; $multi = $uni ? 67 : 153;
    return [$n <= $one ? 1 : (int)ceil($n / $multi), $n, $uni];
  }
  public static function render(string $tpl, array $v): string {
    return trim(preg_replace_callback('/\{(\w+)\}/', fn($m) => array_key_exists($m[1], $v) ? (string)$v[$m[1]] : $m[0], $tpl));
  }
  public static function balance(bool $fresh = false): ?float {
    if ($fresh) Cache::forget('smsmk.bal');
    $b = Cache::remember('smsmk.bal', 300, fn() => Sms::configured() ? Sms::balance() : '');
    return is_numeric($b) ? (float)$b : null;
  }

  private static function base() {
    $o = DB::table('orders')->whereNotIn('status', ['Cancel', 'Returned', 'Incomplete'])->select('phone', DB::raw('count(*) n'), DB::raw('coalesce(sum(total),0) s'), DB::raw('max(created_at) last'))->groupBy('phone');
    return DB::table('customers as c')->leftJoinSub($o, 'o', 'o.phone', '=', 'c.phone')
      ->where('c.blocked', false)->whereNotNull('c.phone')
      ->select('c.id', 'c.name', 'c.phone', 'c.membership', 'c.created_at', DB::raw('coalesce(o.n,0)+coalesce(c.legacy_orders,0) as orders'), DB::raw('coalesce(o.s,0)+coalesce(c.legacy_spent,0) as spend'), DB::raw('coalesce(c.legacy_orders,0) as legacy'), 'o.last as last_order');
  }
  public static function opted(): array { return DB::table('sms_optouts')->pluck('phone')->all(); }

  /** Customers matching the segments (OR) and the extra filters (AND). Returns rows with a normalised `num`. */
  public static function audience(array $segs = [], array $f = [], array $ids = [], bool $marketing = true): array {
    $vip = (float)self::set('sms_vip_spend', '50000'); $high = (float)self::set('sms_high_spend', '20000');
    $days = max(1, (int)($f['no_order_days'] ?? 30)); $rows = self::base()->get();
    $aband = false && in_array('abandoned', $segs, true) ? DB::table('incomplete_orders')->where('status', 'open')->pluck('phone')->map(fn($p) => Sms::number((string)$p))->filter()->flip()->all() : [];
    $kw = trim((string)($f['keyword'] ?? '')); $kwPh = [];
    if ($kw !== '') $kwPh = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('i.name', 'ilike', '%'.$kw.'%')->pluck('o.phone')->map(fn($p) => Sms::number((string)$p))->filter()->flip()->all();
    $out = []; $seen = []; $opt = $marketing ? array_flip(self::opted()) : [];
    $cut = now()->subDays($days); $c90 = now()->subDays(90); $c30 = now()->subDays(30);
    foreach ($rows as $r) {
      $num = Sms::number((string)$r->phone); if (!$num || isset($seen[$num]) || isset($opt[$num])) continue;
      $n = (int)$r->orders; $sp = (float)$r->spend; $last = $r->last_order ? \Carbon\Carbon::parse($r->last_order) : null;
      $tags = [];
      if (str_contains(strtolower((string)$r->membership), 'vip') || $sp >= $vip) $tags[] = 'vip';
      if ($n >= 1) { $tags[] = 'has_orders'; $tags[] = 'regular'; }
      if ($n >= 2) $tags[] = 'returning';
      if ($sp >= $high) $tags[] = 'high_value';
      if ($r->created_at && \Carbon\Carbon::parse($r->created_at)->gte($c30) && $n <= 1 && (int)$r->legacy === 0) $tags[] = 'new';
      if (trim((string)$r->membership) !== '') $tags[] = 'membership';
      if (($last && $last->lt($c90)) || (!$last && ((int)$r->legacy > 0 || ($r->created_at && \Carbon\Carbon::parse($r->created_at)->lt($c90))))) $tags[] = 'inactive';
      if (!$last || $last->lt($cut)) $tags[] = 'no_recent';
      if (isset($aband[$num])) $tags[] = 'abandoned';
      if ($ids) { if (!in_array((int)$r->id, $ids, true)) continue; }
      else {
        if ($segs && !in_array('all', $segs, true) && !array_intersect($segs, $tags)) continue;
        if (!$segs && !array_filter($f)) continue;
      }
      if (!empty($f['min_orders']) && $n < (int)$f['min_orders']) continue;
      if (!empty($f['min_spend']) && $sp < (float)$f['min_spend']) continue;
      if (!empty($f['max_orders']) && $n > (int)$f['max_orders']) continue;
      if (!empty($f['no_order_days']) && $last && $last->gte($cut)) continue;
      if ($kw !== '' && !isset($kwPh[$num])) continue;
      $seen[$num] = 1;
      $out[] = (object)['id'=>(int)$r->id, 'name'=>$r->name, 'phone'=>$r->phone, 'num'=>$num, 'membership'=>$r->membership, 'orders'=>$n, 'spend'=>$sp, 'last'=>$last, 'tags'=>$tags];
    }
    return $out;
  }

  public static function estimate(array $rec, string $body): array {
    [$parts] = self::parts($body); $n = count($rec); $unit = self::unit();
    return ['recipients'=>$n, 'parts'=>$parts, 'unit'=>$unit, 'cost'=>round($n * $parts * $unit, 2), 'balance'=>self::balance()];
  }

  /** Customer-level variables for a recipient. */
  public static function vars(object $c, array $extra = []): array {
    return ['customer_name'=>trim((string)$c->name) ?: 'Customer', 'membership'=>(string)($c->membership ?? ''), 'total'=>number_format((float)($c->spend ?? 0))] + $extra;
  }

  /** Queue one marketing/transactional message into the SMS log. Returns the log id or null if skipped. */
  public static function enqueue(string $num, string $body, string $event, string $type, array $x = []): ?int {
    [$parts] = self::parts($body);
    return DB::table('sms_logs')->insertGetId(['to'=>$num, 'body'=>$body, 'event'=>$event, 'status'=>'queued', 'type'=>$type, 'parts'=>$parts, 'cost'=>round($parts * self::unit(), 2), 'sender_id'=>Sms::secret('sms_sender_id'), 'created_at'=>now(), 'updated_at'=>now()] + $x);
  }

  /** Materialise one run of a campaign into queued log rows (duplicate-protected). */
  public static function launch(int $id): int {
    $c = DB::table('sms_campaigns')->find($id); if (!$c) return 0;
    $segs = json_decode((string)$c->segments, true) ?: []; $f = json_decode((string)$c->filters, true) ?: []; $ids = array_map('intval', json_decode((string)$c->customer_ids, true) ?: []);
    $vars = json_decode((string)$c->vars, true) ?: []; $rec = self::audience($segs, $f, $ids, true);
    $done = $c->allow_dup ? [] : DB::table('sms_logs')->where('campaign_id', $id)->pluck('to')->flip()->all();
    $n = 0; $cost = 0;
    foreach ($rec as $r) {
      if (isset($done[$r->num])) continue;
      $body = self::render($c->body, self::vars($r, $vars)); if ($body === '') continue;
      $lid = self::enqueue($r->num, $body, 'campaign', 'marketing', ['campaign_id'=>$id, 'run'=>$c->run, 'customer_id'=>$r->id, 'customer_name'=>mb_substr((string)$r->name, 0, 120)]);
      $cost += self::parts($body)[0] * self::unit(); $n++;
    }
    DB::table('sms_campaigns')->where('id', $id)->update(['status'=>'sending', 'total'=>DB::raw('total + '.$n), 'cost'=>DB::raw('cost + '.round($cost, 2)), 'started_at'=>$c->started_at ?: now(), 'updated_at'=>now()]);
    return $n;
  }

  /** Called every 30 s by the worker. */
  public static function tick(): void {
    if (!\Illuminate\Support\Facades\Schema::hasTable('sms_campaigns')) return;
    foreach (DB::table('sms_campaigns')->where('status', 'scheduled')->where('schedule_at', '<=', now())->orderBy('id')->limit(3)->pluck('id') as $id) {
      $claim = DB::table('sms_campaigns')->where('id', $id)->where('status', 'scheduled')->update(['status'=>'sending']); if (!$claim) continue;
      self::launch((int)$id);
    }
    if (Sms::configured()) self::pump(30);
    foreach (DB::table('sms_campaigns')->where('status', 'sending')->pluck('id') as $id) {
      if (DB::table('sms_logs')->where('campaign_id', $id)->whereIn('status', ['queued', 'sending'])->exists()) continue;
      $c = DB::table('sms_campaigns')->find($id); $next = null;
      if ($c->repeat !== 'none' && $c->schedule_at) {
        $t = \Carbon\Carbon::parse($c->schedule_at, 'UTC');
        $next = match ($c->repeat) { 'daily' => $t->addDay(), 'weekly' => $t->addWeek(), 'monthly' => $t->addMonth(), default => null };
        while ($next && $next->lt(now())) $next = match ($c->repeat) { 'daily' => $next->addDay(), 'weekly' => $next->addWeek(), default => $next->addMonth() };
      }
      if ($next) DB::table('sms_campaigns')->where('id', $id)->update(['status'=>'scheduled', 'schedule_at'=>$next, 'run'=>$c->run + 1, 'updated_at'=>now()]);
      else DB::table('sms_campaigns')->where('id', $id)->update(['status'=>'completed', 'completed_at'=>now(), 'updated_at'=>now()]);
    }
  }
  /** Send queued rows, campaign rows first, skipping paused campaigns. */
  public static function pump(int $limit): int {
    $n = 0;
    $rows = DB::table('sms_logs as l')->leftJoin('sms_campaigns as c', 'c.id', '=', 'l.campaign_id')->where('l.status', 'queued')
      ->where(fn($q) => $q->whereNull('l.campaign_id')->orWhere('c.status', 'sending'))->orderBy('l.id')->limit($limit)->select('l.id', 'l.to', 'l.body')->get();
    foreach ($rows as $r) {
      if (!DB::table('sms_logs')->where('id', $r->id)->where('status', 'queued')->update(['status'=>'sending'])) continue;
      [$ok, $msg] = Sms::send($r->to, $r->body);
      DB::table('sms_logs')->where('id', $r->id)->update(['status'=>$ok ? 'sent' : 'failed', 'provider_response'=>mb_substr($msg, 0, 500), 'sent_at'=>$ok ? now() : null, 'updated_at'=>now()]); $n++;
    }
    return $n;
  }

  /** Automation: send the stored template for a trigger, once per (trigger, key). Transactional unless it is a win-back. */
  public static function auto(string $trigger, string $phone, array $vars, string $key, ?int $orderId = null, ?int $customerId = null): void {
    try {
      $a = DB::table('sms_automations')->where('trigger', $trigger)->where('active', true)->first(); if (!$a) return;
      $num = Sms::number($phone); if (!$num) return;
      $type = in_array($trigger, ['welcome', 'inactive', 'incomplete_recovery'], true) ? 'marketing' : 'transactional';
      if ($type === 'marketing' && DB::table('sms_optouts')->where('phone', $num)->exists()) return;
      $ev = 'auto_'.$trigger;
      if (DB::table('sms_logs')->where('event', $ev)->where('ref', $key)->exists()) return;
      $body = self::render($a->body, $vars); if ($body === '') return;
      self::enqueue($num, $body, $ev, $type, ['order_id'=>$orderId, 'customer_id'=>$customerId, 'customer_name'=>mb_substr((string)($vars['customer_name'] ?? ''), 0, 120), 'ref'=>$key]);
    } catch (\Throwable $e) {}
  }
}
