<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Profit per automatic marketing campaign (owner's plan step 1, 24 Nov 2026).
 *  A customer order counts for a campaign when that customer's phone got the campaign's message / call in the WINDOW hours before the order was placed (last message wins, so one order is never counted twice).
 *  Profit = only DELIVERED orders: items - discount - product cost - courier charge; the message cost (SMS cost from the SMS log) is taken off to give the net. Orders still on the way are counted but not in profit yet. */
class CampaignProfit {
  const WINDOW = 72;
  const NAMES = ['reorder' => ['Reorder Reminder', 'SMS'], 'winback' => ['Win-Back Offers', 'SMS'], 'incomplete_nudge' => ['Unfinished checkout', 'SMS'], 'pub_back' => ['Back in Store', 'SMS'], 'cart_wa' => ['Unfinished checkout', 'WhatsApp'], 'voice' => ['Offer calls (answered)', 'Voice']];
  private static function touches(): string {
    $q = "select right(\"to\", 11) as p, event as c, created_at as at from sms_logs where event in ('reorder','winback','incomplete_nudge','pub_back') and status in ('sent','delivered','queued') and created_at >= ?";
    $q .= " union all select right(phone, 11), 'cart_wa', wa_nudged_at from incomplete_orders where wa_nudged_at is not null and wa_nudged_at >= ?";
    $q .= " union all select right(phone, 11), 'voice', answered_at from voice_call_attempts where answered_at is not null and coalesce(test, false) = false and answered_at >= ?";
    return $q;
  }
  public static function report(int $days): array {
    return Cache::remember('campprofit.' . $days, 600, function () use ($days) {
      $since = now()->subDays($days); $from = $since->copy()->subHours(self::WINDOW);
      $lat = "left join lateral (select coalesce(sum(i.qty*i.price),0) items, coalesce(sum(coalesce(i.cogs, i.qty*coalesce(p.cost,0))),0) cost from order_items i left join products p on p.id = i.product_id where i.order_id = o.id) x on true";
      $sql = "with t as (" . self::touches() . "),
        sent as (select c, count(*) msgs, count(distinct p) people from t where at >= ? group by c),
        ord as (select o.id, o.status, (x.items - coalesce(o.discount,0)) as sales, x.cost, coalesce(o.courier_fee,0) as cf, (select t.c from t where t.p = right(o.phone, 11) and t.at <= o.created_at and t.at >= o.created_at - interval '" . self::WINDOW . " hours' order by t.at desc limit 1) as c
          from orders o $lat where o.created_at >= ? and o.phone is not null),
        a as (select c, count(*) filter (where status <> 'Cancel') orders,
            count(*) filter (where status in ('Delivered','Partial Delivered','Late Delivery')) delivered, count(*) filter (where status in ('Returned','Return Pending')) returned, count(*) filter (where status = 'Cancel') cancelled,
            coalesce(sum(sales) filter (where status in ('Delivered','Partial Delivered','Late Delivery')), 0) sales, coalesce(sum(sales - cost - cf) filter (where status in ('Delivered','Partial Delivered','Late Delivery')), 0) profit
          from ord where c is not null group by c)
        select s.c, s.msgs, s.people, coalesce(a.orders,0) orders, coalesce(a.delivered,0) delivered, coalesce(a.returned,0) returned, coalesce(a.cancelled,0) cancelled, coalesce(a.sales,0) sales, coalesce(a.profit,0) profit from sent s left join a on a.c = s.c";
      $rows = DB::select($sql, [$from, $from, $from, $since, $since]);
      $cost = []; try { foreach (DB::table('sms_logs')->whereIn('event', ['reorder', 'winback', 'incomplete_nudge', 'pub_back'])->where('created_at', '>=', $since)->whereIn('status', ['sent', 'delivered', 'queued'])->selectRaw('event, coalesce(sum(cost),0) as c')->groupBy('event')->get() as $r) $cost[$r->event] = (float)$r->c; } catch (\Throwable $e) {}
      $out = [];
      foreach ($rows as $r) {
        $n = self::NAMES[$r->c] ?? [$r->c, '']; $mc = $cost[$r->c] ?? 0.0; $profit = (float)$r->profit;
        $out[] = ['key' => $r->c, 'name' => $n[0], 'ch' => $n[1], 'msgs' => (int)$r->msgs, 'people' => (int)$r->people, 'orders' => (int)$r->orders, 'delivered' => (int)$r->delivered, 'returned' => (int)$r->returned, 'cancelled' => (int)$r->cancelled,
          'sales' => (float)$r->sales, 'profit' => $profit, 'cost' => $mc, 'net' => $profit - $mc, 'per100' => $r->people > 0 ? round(($profit - $mc) / $r->people * 100) : 0];
      }
      usort($out, fn($a, $b) => $b['net'] <=> $a['net']);
      return $out;
    });
  }
}
