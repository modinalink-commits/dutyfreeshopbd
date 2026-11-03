<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

class Push
{
  const CATEGORIES = ['orders' => 'Order updates', 'delivery' => 'Delivery updates', 'offers' => 'Offers', 'coupons' => 'Coupons', 'membership' => 'Membership', 'support' => 'Support messages'];
  const TYPES = [
    'transactional' => ['order_received' => ['Order Received', 'orders'], 'order_confirmed' => ['Order Confirmed', 'orders'], 'payment_verified' => ['Payment Verified', 'orders'], 'order_packed' => ['Order Packed', 'orders'], 'order_shipped' => ['Order Shipped', 'delivery'], 'out_for_delivery' => ['Out for Delivery', 'delivery'], 'order_delivered' => ['Order Delivered', 'delivery'], 'support_reply' => ['Support Reply', 'support']],
    'marketing' => ['flash_sale' => ['Flash Sale', 'offers'], 'new_arrival' => ['New Arrival', 'offers'], 'coupon' => ['Coupon', 'coupons'], 'membership_offer' => ['Membership Offer', 'membership'], 'special_discount' => ['Special Discount', 'offers'], 'free_delivery' => ['Free Delivery Campaign', 'offers']],
  ];
  const CRITICAL = ['order_received', 'order_confirmed', 'payment_verified'];

  public static function categoryOf(string $type): string {
    foreach (self::TYPES as $g) if (isset($g[$type])) return $g[$type][1];
    return 'offers';
  }

  public static function prefs(int $cid): array {
    $r = DB::table('customer_notification_prefs')->where('customer_id', $cid)->first();
    $o = []; foreach (array_keys(self::CATEGORIES) as $c) $o[$c] = $r ? (bool)$r->$c : true;
    return $o;
  }

  private static function absUrl(?string $u): string { $u = $u ?: '/'; return $u; }

  /** Store in the customer's notification history and push to their devices (respecting their settings). */
  public static function notify(int $cid, string $type, string $title, string $body, string $url = '/', ?string $image = null, ?int $campaignId = null): int {
    $cat = self::categoryOf($type);
    $allowed = self::prefs($cid)[$cat] ?? true;
    if (!$allowed && !in_array($type, self::CRITICAL, true)) return 0;
    $nid = DB::table('customer_notifications')->insertGetId(['customer_id' => $cid, 'campaign_id' => $campaignId, 'category' => $cat, 'type' => $type, 'title' => mb_substr($title, 0, 160), 'body' => mb_substr($body, 0, 600), 'url' => $url, 'image' => $image, 'created_at' => now()]);
    $sent = 0;
    foreach (DB::table('push_subscriptions')->where('customer_id', $cid)->where('active', true)->get() as $s) {
      $code = WebPush::send($s, ['title' => $title, 'body' => $body, 'url' => $url, 'image' => $image, 'nid' => $campaignId ? 0 : 0, 'tag' => $type . ':' . $nid]);
      if ($code >= 200 && $code < 300) $sent++;
    }
    return $sent;
  }

  /** Customer ids matching an audience filter, or null for "everyone with a device". */
  public static function audienceCustomerIds(string $type, ?string $val): ?array {
    $paid = "status not in ('Cancel','Returned','Return Pending','Incomplete')";
    $base = "select c.id from customers c left join (select phone, count(*) n, coalesce(sum(total),0) s from orders where $paid group by phone) o on o.phone = c.phone where c.blocked = false";
    $val = trim((string)$val);
    switch ($type) {
      case 'all': return null;
      case 'customer': { $p = preg_replace('/\D/', '', $val); if ($p === '') return [0]; $p = substr($p, -10); return DB::table('customers')->where('phone', 'like', '%' . $p)->pluck('id')->all() ?: [0]; }
      case 'new': return array_map(fn($r) => $r->id, DB::select($base . ' and coalesce(o.n,0) <= 1')) ?: [0];
      case 'returning': return array_map(fn($r) => $r->id, DB::select($base . ' and o.n >= 2')) ?: [0];
      case 'regular': return array_map(fn($r) => $r->id, DB::select($base . ' and o.n >= 3')) ?: [0];
      case 'vip': return array_map(fn($r) => $r->id, DB::select($base . " and (c.membership ilike '%vip%' or o.s >= ?)", [max(1, (int)($val ?: 30000))])) ?: [0];
      case 'membership': return array_map(fn($r) => $r->id, DB::select($base . " and coalesce(c.membership,'') <> ''")) ?: [0];
      case 'store': return array_map(fn($r) => $r->id, DB::select('select distinct c.id from customers c join orders o on o.phone = c.phone where o.store_id = ?', [(int)$val])) ?: [0];
      case 'product': return array_map(fn($r) => $r->id, DB::select('select distinct c.id from customers c join orders o on o.phone = c.phone join order_items i on i.order_id = o.id where i.product_id = ?', [(int)$val])) ?: [0];
      case 'category': return array_map(fn($r) => $r->id, DB::select('select distinct c.id from customers c join orders o on o.phone = c.phone join order_items i on i.order_id = o.id join products p on p.id = i.product_id where p.category_id = ?', [(int)$val])) ?: [0];
    }
    return [0];
  }

  public static function audienceQuery(string $type, ?string $val) {
    $q = DB::table('push_subscriptions')->where('active', true)->whereNull('label');
    $ids = self::audienceCustomerIds($type, $val);
    return $ids === null ? $q : $q->whereIn('customer_id', $ids);
  }

  public static function audienceCount(string $type, ?string $val): int { return (int)self::audienceQuery($type, $val)->count(); }

  /** Send the next batch of a campaign. Returns true when finished. */
  public static function runCampaign(int $id, int $limit = 150): bool {
    $c = DB::table('push_campaigns')->find($id);
    if (!$c || !in_array($c->status, ['scheduled', 'sending'], true)) return true;
    if ($c->status === 'scheduled') DB::table('push_campaigns')->where('id', $id)->update(['status' => 'sending', 'targeted' => self::audienceCount($c->audience_type, $c->audience_value), 'updated_at' => now()]);
    $cat = self::categoryOf((string)$c->type); $critical = in_array($c->type, self::CRITICAL, true);
    $subs = self::audienceQuery($c->audience_type, $c->audience_value)->where('id', '>', $c->last_sub_id)->orderBy('id')->limit($limit)->get();
    $sent = 0; $failed = 0; $last = $c->last_sub_id; $hist = [];
    foreach ($subs as $s) {
      $last = $s->id;
      if ($s->customer_id) {
        $ok = (self::prefs((int)$s->customer_id)[$cat] ?? true) || $critical;
        if (!$ok) continue;
        if (!isset($hist[$s->customer_id])) { $hist[$s->customer_id] = 1; DB::table('customer_notifications')->insert(['customer_id' => $s->customer_id, 'campaign_id' => $id, 'category' => $cat, 'type' => $c->type, 'title' => $c->title, 'body' => $c->body, 'url' => $c->url, 'image' => $c->image, 'created_at' => now()]); }
      }
      $code = WebPush::send($s, ['title' => $c->title, 'body' => $c->body, 'url' => $c->url, 'image' => $c->image ?: null, 'cta' => $c->cta ?: null, 'nid' => $id, 'tag' => 'c' . $id]);
      if ($code >= 200 && $code < 300) $sent++; else $failed++;
    }
    $done = $subs->count() < $limit;
    DB::table('push_campaigns')->where('id', $id)->update(['last_sub_id' => $last, 'sent' => DB::raw('sent + ' . $sent), 'failed' => DB::raw('failed + ' . $failed), 'status' => $done ? 'sent' : 'sending', 'sent_at' => $done ? now() : null, 'updated_at' => now()]);
    return $done;
  }
}
