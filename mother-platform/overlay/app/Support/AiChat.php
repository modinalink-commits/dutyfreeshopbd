<?php
namespace App\Support;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
/** Shop assistant powered by the Claude API. The API key is stored encrypted and never shown again. */
class AiChat {
  public static function on(): bool {
    return Cache::remember('ai.on', 300, fn() => Sms::secret('ai_enabled') === '1' && (bool) Sms::secret('ai_key'));
  }
  /** Automatic replies inside the website live chat (can be switched off in Chat Settings). */
  public static function chatOn(): bool { return self::on() && Sms::secret('ai_chat_auto') !== '0'; }
  public static function model(): string { return Sms::secret('ai_model') ?: 'claude-haiku-5-5'; }
  /** Store-wide facts the assistant may quote (cached 5 min): contact, delivery, payment, policies, offers, categories. */
  private static function facts(): string {
    return Cache::remember('ai.facts', 300, function () {
      $s = \App\Http\Controllers\StoreController::set(); $o = [];
      $o[] = 'STORE: DutyFree Shop BD (dutyfreeshopbd.com), Child City. Phone/WhatsApp: '.StoreFallback::phone().'. Customer care: 09613828081. Email: '.($s['contact_email'] ?? '').'. Address: '.preg_replace('/\s+/', ' ', (string)($s['contact_address'] ?? '')).'. Hours: '.($s['business_hours'] ?? '').'.';
      $d = []; foreach ([1,2,3,4] as $i) if (!empty($s["ship_{$i}_name"])) $d[] = $s["ship_{$i}_name"].' = '.$s["ship_{$i}_charge"].' TK';
      if ($d) $o[] = 'DELIVERY CHARGES: '.implode('; ', $d).'.';
      $pay = [];
      if (($s['co_cod'] ?? '1') === '1') $pay[] = 'Cash on delivery';
      try { foreach (DB::table('payment_methods')->where('active', true)->orderBy('position')->get() as $m) $pay[] = $m->title.' ('.$m->kind.')'.($m->account_no ? ' account '.$m->account_no.($m->account_name ? ' / '.$m->account_name : '') : '').($m->instruction ? ' - '.mb_substr(strip_tags($m->instruction), 0, 160) : ''); } catch (\Throwable $e) {}
      if ($pay) $o[] = 'PAYMENT OPTIONS: '.implode('; ', $pay).'.';
      try { foreach (DB::table('discount_rules')->where('enabled', true)->get(['title', 'help', 'config']) as $r) $o[] = 'OFFER: '.$r->title.' - '.mb_substr(strip_tags((string)$r->help), 0, 160).' '.mb_substr((string)$r->config, 0, 200); } catch (\Throwable $e) {}
      try { foreach (DB::table('pages')->where('active', true)->limit(14)->get(['title', 'body']) as $pg) { $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$pg->body))); if ($t !== '') $o[] = 'PAGE "'.$pg->title.'": '.mb_substr($t, 0, 700); } } catch (\Throwable $e) {}
      try { $o[] = 'CATEGORIES: '.DB::table('categories')->orderBy('name')->limit(60)->pluck('name')->implode(', ').'.'; $o[] = 'BRANDS: '.DB::table('brands')->where('active', true)->orderBy('name')->limit(80)->pluck('name')->implode(', ').'.'; } catch (\Throwable $e) {}
      return implode("\n", $o);
    });
  }
  /** Whole published catalogue in compact form (cached 5 min). */
  private static function catalog(): array {
    return Cache::remember('ai.catalog', 300, function () {
      $cat = DB::table('categories')->pluck('name', 'id');
      $rows = DB::table('products')->where('published', true)->orderByDesc('stock')->orderBy('id')->limit(900)
        ->get(['id', 'ext_id', 'name', 'price', 'offer_price', 'compare_price', 'stock', 'sub', 'category_id', 'path_slug', 'size']);
      return $rows->map(function ($p) use ($cat) {
        $now = (float)($p->offer_price ?: $p->price); $was = (float)$p->compare_price > $now ? ' (was '.number_format($p->compare_price).')' : '';
        return ['id'=>$p->id, 'line'=>$p->name.' | '.($cat[$p->category_id] ?? '').($p->sub ? '/'.$p->sub : '').($p->size ? ' | '.$p->size : '').' | '.number_format($now).' TK'.$was.' | '.($p->stock > 0 ? 'in stock' : 'OUT OF STOCK').' | '.parse_url(\App\Http\Controllers\StoreController::url($p), PHP_URL_PATH)];
      })->all();
    });
  }
  /** Descriptions of the products that best match the question, plus verified order status when invoice + phone are both given. */
  private static function detail(string $q, string $all): string {
    $out = '';
    $words = array_values(array_filter(preg_split('/[\s,.?!]+/u', mb_strtolower($q)), fn($w) => mb_strlen($w) >= 3));
    if ($words) {
      $rows = DB::table('products')->where('published', true)->where(function ($w) use ($words) {
        foreach (array_slice($words, 0, 6) as $t) { $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $t).'%'; $w->orWhere('name','ilike',$like)->orWhere('sub','ilike',$like)->orWhere('description','ilike',$like); }
      })->limit(4)->get(['name', 'description', 'price']);
      foreach ($rows as $p) { $d = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$p->description))); if ($d !== '') $out .= '- '.$p->name.': '.mb_substr($d, 0, 450)."\n"; }
      if ($out !== '') $out = "PRODUCT DETAILS:\n".$out;
    }
    if (preg_match('/\bINV-?\s?(\d{4,8})\b/i', $all, $m) && preg_match('/(?<!\d)(?:\+?88)?(01[3-9]\d{8})(?!\d)/', $all, $ph)) {
      $o = DB::table('orders')->where('invoice', 'INV-'.$m[1])->where('phone', $ph[1])->first(['invoice', 'status', 'total', 'courier_status', 'tracking_code', 'created_at']);
      if ($o) $out .= "VERIFIED ORDER (the customer proved ownership with the phone number; you may share this): ".$o->invoice.' status '.$o->status.', total '.number_format($o->total).' TK'.($o->courier_status ? ', courier status '.$o->courier_status : '').($o->tracking_code ? ', tracking code '.$o->tracking_code : '').', placed '.\Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y').".\n";
      else $out .= "No order matches that invoice and phone number together.\n";
    }
    return $out;
  }
  public static function reply(array $history, string $extra = ''): array {
    $key = Sms::secret('ai_key'); if (!$key) return [false, 'not configured'];
    $msgs = []; $all = '';
    foreach (array_slice($history, -12) as $m) {
      $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
      $c = trim(mb_substr((string)($m['content'] ?? ''), 0, 800)); if ($c === '') continue;
      if ($role === 'user') $all .= ' '.$c;
      if ($msgs && end($msgs)['role'] === $role) { $msgs[count($msgs)-1]['content'] .= "\n".$c; continue; }
      $msgs[] = ['role'=>$role, 'content'=>$c];
    }
    while ($msgs && $msgs[0]['role'] !== 'user') array_shift($msgs);
    if (!$msgs || end($msgs)['role'] !== 'user') return [false, 'empty'];
    $call = StoreFallback::phone();
    $sys = "You are the customer assistant of DutyFree Shop BD (dutyfreeshopbd.com), a Bangladeshi online store for imported and duty-free perfume, skin care, hair care, lifestyle items and cigarettes (cigarettes are sold only by the carton of 200 sticks, never single packs).\n"
      ."LANGUAGE: customers write in Bengali (বাংলা), English, or Banglish (Bengali typed in English letters, e.g. 'dam koto', 'delivery koto din lagbe', 'ei perfume ache?'). Understand all three, including spelling mistakes, and reply in the SAME style the customer used (Banglish in -> Banglish or Bengali out; Bengali in -> Bengali out; English in -> English out).\n"
      ."STYLE: warm, short and direct, at most 6 short lines. Answer exactly what was asked first. Use plain text only (no markdown, no asterisks).\n"
      ."TRUTH: answer ONLY from the STORE FACTS, CATALOGUE, PRODUCT DETAILS and VERIFIED ORDER blocks below. Quote prices, stock, delivery charges, payment options and links exactly as written. Never invent a product, price, discount, delivery time, policy or stock. If the data does not contain the answer, say you are not sure and give the phone/WhatsApp number $call.\n"
      ."PRODUCTS: when the customer asks about a product or category, search the whole CATALOGUE (match Bengali/Banglish names to the English product names, e.g. 'পারফিউম'/'perfume', 'সিগারেট'/'cigarette'). Recommend up to 3 matching items, each with name, price, stock status and its link path exactly as given (e.g. /p/123-name/). To order: open the product and press ORDER NOW (cash on delivery is available where listed). If an item is OUT OF STOCK say so and suggest in-stock alternatives.\n"
      ."ORDERS: you can only show an order's status if a VERIFIED ORDER block is present. Otherwise ask the customer for the invoice number (INV-xxxxxx) and the phone number used for the order, or send them to /track. Never share any customer's personal data. Never ask for passwords, OTP or card numbers.\n"
      .$extra."\nSTORE FACTS:\n".self::facts()."\n\nCATALOGUE (name | category | price | stock | link):\n".implode("\n", array_column(self::catalog(), 'line'));
    $detail = self::detail(end($msgs)['content'], $all);
    $sys2 = $detail !== '' ? $detail : '';
    try {
      $res = Http::withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])->timeout(40)->post('https://api.anthropic.com/v1/messages', ['model'=>self::model(), 'max_tokens'=>700, 'system'=>array_values(array_filter([['type'=>'text', 'text'=>$sys, 'cache_control'=>['type'=>'ephemeral']], $sys2 !== '' ? ['type'=>'text', 'text'=>$sys2] : null])), 'messages'=>$msgs]);
    } catch (\Throwable $e) { return [false, 'network']; }
    if (!$res->ok()) return [false, 'HTTP '.$res->status().' '.mb_substr((string)($res->json('error.message') ?? ''), 0, 160)];
    $t = trim(collect($res->json('content') ?? [])->where('type','text')->pluck('text')->implode("\n"));
    return $t === '' ? [false, 'empty reply'] : [true, $t];
  }
}
class StoreFallback { public static function phone(): string { $s = \App\Http\Controllers\StoreController::set(); return '0'.ltrim((string)($s['call_number'] ?? '09613828081'), '0'); } }
