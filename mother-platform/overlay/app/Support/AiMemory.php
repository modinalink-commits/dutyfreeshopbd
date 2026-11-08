<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** One place for everything the AI learns from chats: what customers do (language, intents, moods, products, orders, how long they wait) and how our team answers (reply examples + speed). Fed from the live chat flow, read back into the AI prompt. Never throws: memory must not break a chat. */
class AiMemory {
  public static function key(?object $t): string { return $t && !empty($t->phone) ? 'p:'.$t->phone : 't:'.($t->id ?? 0); }
  public static function mask(string $s): string { return trim(preg_replace('/\d{9,}/', '•••', mb_substr($s, 0, 600))); }
  public static function lang(string $s): ?string {
    $s = trim($s); if (mb_strlen($s) < 2) return null;
    $bn = preg_match_all('/[\x{0980}-\x{09FF}]/u', $s); $la = preg_match_all('/[A-Za-z]/', $s);
    if ($bn >= 2 && $bn > $la) return 'bangla'; if ($bn >= 2 && $la >= 2) return 'mixed'; if ($la < 2) return null;
    return preg_match('/\b(ami|apni|apnar|kore|korben|den|dibo|kto|koto|dam|taka|ache|achhe|nai|nei|lagbe|chai|kobe|pathan|pathaben|ki|kemon|bhai|vai|ta|ei|oi|hobe|hoy|jabe|nibo|nebo|amar|tomar|please bhai|order ta|product ta)\b/i', $s) ? 'banglish' : 'english';
  }
  public static function event(?int $tid, ?string $mkey, string $actor, string $type, ?string $ch = null, array $meta = []): void {
    try { DB::table('ai_behavior_events')->insert(['thread_id'=>$tid, 'mkey'=>$mkey, 'actor'=>$actor, 'type'=>$type, 'channel'=>$ch, 'meta'=>$meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, 'created_at'=>now()]); } catch (\Throwable $e) { report($e); }
  }
  private static function row(object $t): ?object {
    $k = self::key($t);
    $r = DB::table('ai_customer_memory')->where('mkey', $k)->first();
    if (!$r && !empty($t->phone)) { // a conversation that was anonymous before the phone was learned moves to the phone key
      $old = DB::table('ai_customer_memory')->where('mkey', 't:'.$t->id)->first();
      if ($old) { DB::table('ai_customer_memory')->where('id', $old->id)->update(['mkey'=>$k, 'phone'=>$t->phone]); return DB::table('ai_customer_memory')->find($old->id); }
    }
    if (!$r) { DB::table('ai_customer_memory')->insert(['mkey'=>$k, 'phone'=>$t->phone ?? null, 'name'=>$t->name ?? null, 'channel'=>$t->channel ?? null, 'created_at'=>now(), 'updated_at'=>now()]); $r = DB::table('ai_customer_memory')->where('mkey', $k)->first(); }
    return $r;
  }
  /** A customer message arrived. */
  public static function onCustomer(object $t, string $body, ?string $att = null): void {
    try {
      $r = self::row($t); if (!$r) return;
      $l = self::lang($body); $ln = json_decode($r->lang_n ?: '{}', true) ?: []; if ($l) $ln[$l] = (int)($ln[$l] ?? 0) + 1;
      $tot = array_sum($ln); $pref = null; if ($tot >= 3) { arsort($ln); $top = array_key_first($ln); if ($top !== 'mixed' && $ln[$top] / $tot >= 0.7) $pref = $top; }
      DB::table('ai_customer_memory')->where('id', $r->id)->update(['msg_count'=>DB::raw('msg_count + 1'), 'lang_n'=>json_encode($ln), 'lang_pref'=>$pref, 'name'=>$t->name ?? $r->name, 'channel'=>$t->channel ?? $r->channel, 'last_seen_at'=>now(), 'updated_at'=>now()]);
      self::event((int)$t->id, $r->mkey, 'customer', $att ? 'attachment' : 'message', $t->channel ?? null, ['lang'=>$l, 'len'=>mb_strlen($body)]);
      if (!$r->refreshed_at || now()->diffInMinutes($r->refreshed_at) > 60) self::refresh($r->mkey);
    } catch (\Throwable $e) { report($e); }
  }
  /** The AI answered; what it understood (intent, mood) is stored against the customer. */
  public static function onAi(int $tid, array $meta = []): void {
    try {
      $t = DB::table('chat_threads')->find($tid); if (!$t) return; $r = self::row($t); if (!$r) return;
      $in = json_decode($r->intents ?: '{}', true) ?: []; $mo = json_decode($r->moods ?: '{}', true) ?: [];
      if (!empty($t->intent)) $in[$t->intent] = (int)($in[$t->intent] ?? 0) + 1; if (!empty($t->mood)) $mo[$t->mood] = (int)($mo[$t->mood] ?? 0) + 1;
      DB::table('ai_customer_memory')->where('id', $r->id)->update(['intents'=>json_encode($in), 'moods'=>json_encode($mo), 'updated_at'=>now()]);
      self::event($tid, $r->mkey, 'ai', 'reply', $t->channel ?? null, ['intent'=>$t->intent ?? null, 'mood'=>$t->mood ?? null] + $meta);
    } catch (\Throwable $e) { report($e); }
  }
  /** A staff member's reply was accepted by the channel: keep the (customer question, staff answer) pair and how long the customer waited. */
  public static function onStaff(object $t, string $reply, ?string $staff): void {
    try {
      $in = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'in')->orderByDesc('id')->first(['body', 'created_at']);
      $wait = $in ? max(0, (int)\Carbon\Carbon::parse($in->created_at)->diffInSeconds(now())) : null;
      $r = self::row($t); $k = $r->mkey ?? self::key($t);
      if (mb_strlen(trim($reply)) >= 8 && $in && trim((string)$in->body) !== '' && !preg_match('/\d{6,}/u', $reply)) // replies carrying numbers (addresses, ids, amounts) are never kept as examples
        DB::table('ai_staff_examples')->insert(['thread_id'=>$t->id, 'staff'=>$staff, 'intent'=>$t->intent ?? null, 'channel'=>$t->channel ?? null, 'lang'=>self::lang($reply), 'customer_text'=>self::mask((string)$in->body), 'reply_text'=>self::mask($reply), 'wait_sec'=>$wait, 'created_at'=>now()]);
      if ($r && $wait !== null) { $avg = $r->avg_staff_wait_min === null ? (int)round($wait / 60) : (int)round(($r->avg_staff_wait_min * 3 + $wait / 60) / 4); DB::table('ai_customer_memory')->where('id', $r->id)->update(['avg_staff_wait_min'=>$avg, 'updated_at'=>now()]); }
      self::event((int)$t->id, $k, 'staff', 'reply', $t->channel ?? null, ['staff'=>$staff, 'wait_sec'=>$wait, 'len'=>mb_strlen($reply)]);
    } catch (\Throwable $e) { report($e); }
  }
  /** Rebuilds the order-side facts + a one-paragraph summary for one customer. */
  public static function refresh(string $mkey): void {
    try {
      $r = DB::table('ai_customer_memory')->where('mkey', $mkey)->first(); if (!$r) return;
      $upd = ['refreshed_at'=>now(), 'updated_at'=>now()];
      if ($r->phone) {
        $o = DB::table('orders')->where('phone', $r->phone)->selectRaw("count(*) n, coalesce(sum(case when status='Delivered' then 1 else 0 end),0) d")->first();
        $upd['orders_count'] = (int)$o->n; $upd['delivered_count'] = (int)$o->d; $upd['last_order_status'] = DB::table('orders')->where('phone', $r->phone)->orderByDesc('id')->value('status');
        $pr = DB::table('order_items as i')->join('orders as o', 'o.id', '=', 'i.order_id')->where('o.phone', $r->phone)->selectRaw('i.name, count(*) c')->groupBy('i.name')->orderByDesc('c')->limit(5)->pluck('c', 'name')->all();
        $upd['products'] = json_encode($pr, JSON_UNESCAPED_UNICODE);
      }
      DB::table('ai_customer_memory')->where('id', $r->id)->update($upd);
      $r = DB::table('ai_customer_memory')->where('id', $r->id)->first();
      DB::table('ai_customer_memory')->where('id', $r->id)->update(['summary'=>self::summary($r)]);
    } catch (\Throwable $e) { report($e); }
  }
  private static function top(?string $json, int $n = 3): string { $a = json_decode($json ?: '{}', true) ?: []; arsort($a); return implode(', ', array_slice(array_keys($a), 0, $n)); }
  public static function summary(object $r): string {
    $p = [];
    if ($r->lang_pref) $p[] = 'usually writes in '.$r->lang_pref;
    if ($r->orders_count) $p[] = $r->orders_count.' past order(s), '.$r->delivered_count.' delivered'.($r->last_order_status ? ', latest status '.$r->last_order_status : '');
    elseif ($r->phone) $p[] = 'no past orders with us';
    if ($t = self::top($r->products)) $p[] = 'bought before: '.$t;
    if ($t = self::top($r->intents)) $p[] = 'usually asks about: '.str_replace('_', ' ', $t);
    $mo = json_decode($r->moods ?: '{}', true) ?: []; $bad = (int)($mo['angry'] ?? 0) + (int)($mo['upset'] ?? 0); if ($bad >= 2) $p[] = 'has been upset before: be extra careful and polite';
    if ($r->avg_staff_wait_min !== null) $p[] = 'staff usually answer in about '.$r->avg_staff_wait_min.' min';
    return implode('; ', $p);
  }
  /** Text for the AI prompt: this customer's memory + how our team answers similar questions. Facts (price, stock, status) still come only from tools. */
  public static function prompt(int $tid): string {
    try {
      $t = DB::table('chat_threads')->find($tid); if (!$t) return '';
      $out = ''; $r = DB::table('ai_customer_memory')->where('mkey', self::key($t))->first();
      if ($r && $r->summary) $out .= "\nCUSTOMER MEMORY (our own records, may be incomplete; use it to be more helpful, e.g. suggest a usual product or the language they like, but never read private details aloud and never treat it as proof for orders or payments): ".$r->summary;
      $q = DB::table('ai_staff_examples')->where('channel', $t->channel)->orderByDesc('id');
      $ex = !empty($t->intent) ? (clone $q)->where('intent', $t->intent)->limit(3)->get() : collect();
      if ($ex->count() < 3) $ex = $ex->concat($q->limit(3 - $ex->count())->get());
      if ($ex->count()) $out .= "\nHOW OUR TEAM ANSWERS (style and wording reference only; never copy numbers, prices or promises, facts come from tools):\n".$ex->map(fn($e) => '- Customer: '.mb_substr($e->customer_text, 0, 160).' | Team: '.mb_substr($e->reply_text, 0, 220))->implode("\n");
      return $out;
    } catch (\Throwable $e) { return ''; }
  }
}
