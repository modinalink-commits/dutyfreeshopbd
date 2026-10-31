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
  public static function model(): string { return Sms::secret('ai_model') ?: 'claude-haiku-5-5'; }
  private static function context(string $q): string {
    $words = array_values(array_filter(preg_split('/[\s,]+/u', mb_strtolower($q)), fn($w) => mb_strlen($w) >= 3));
    $rows = collect();
    if ($words) {
      $rows = DB::table('products')->where('published', true)->where(function ($w) use ($words) {
        foreach (array_slice($words, 0, 5) as $t) { $like = '%'.str_replace(['%','_'], ['\\%','\\_'], $t).'%'; $w->orWhere('name','ilike',$like)->orWhere('sub','ilike',$like); }
      })->orderByDesc('stock')->limit(8)->get(['id','ext_id','name','price','stock','sub']);
    }
    if ($rows->isEmpty()) return 'No matching products were found for the latest message.';
    return $rows->map(fn($p) => '- '.$p->name.' | '.number_format($p->price).' TK | '.($p->stock > 0 ? 'in stock' : 'out of stock').' | link: '.parse_url(\App\Http\Controllers\StoreController::url($p), PHP_URL_PATH))->implode("\n");
  }
  public static function reply(array $history): array {
    $key = Sms::secret('ai_key'); if (!$key) return [false, 'not configured'];
    $msgs = [];
    foreach (array_slice($history, -8) as $m) {
      $role = ($m['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
      $c = trim(mb_substr((string)($m['content'] ?? ''), 0, 500)); if ($c === '') continue;
      if ($msgs && end($msgs)['role'] === $role) { $msgs[count($msgs)-1]['content'] .= "\n".$c; continue; }
      $msgs[] = ['role'=>$role, 'content'=>$c];
    }
    while ($msgs && $msgs[0]['role'] !== 'user') array_shift($msgs);
    if (!$msgs || end($msgs)['role'] !== 'user') return [false, 'empty'];
    $call = StoreFallback::phone();
    $sys = "You are the shopping assistant of DutyFree Shop BD (dutyfreeshopbd.com), a Bangladeshi online store for imported and duty-free perfume, skin care, hair care, lifestyle items and cigarettes (cigarettes are sold only by the carton of 200 sticks).\n"
      ."Reply in the customer's language (Bengali or English), short and friendly, at most 4 sentences.\n"
      ."Only state prices, stock and links that appear in the PRODUCT LIST below; never invent a product, price, discount, delivery time or policy. If unsure, say so and give the phone/WhatsApp number $call.\n"
      ."When you recommend a product, include its link path exactly as given (for example /p/123-name/). To order, the customer opens the product and presses ORDER NOW (cash on delivery is available).\n"
      ."You cannot see orders or customer data; for order status send them to /track or the phone number. Never ask for passwords or card numbers.\n\nPRODUCT LIST (live from the store):\n".self::context(end($msgs)['content']);
    try {
      $res = Http::withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])->timeout(30)->post('https://api.anthropic.com/v1/messages', ['model'=>self::model(), 'max_tokens'=>400, 'system'=>$sys, 'messages'=>$msgs]);
    } catch (\Throwable $e) { return [false, 'network']; }
    if (!$res->ok()) return [false, 'HTTP '.$res->status().' '.mb_substr((string)($res->json('error.message') ?? ''), 0, 160)];
    $t = trim(collect($res->json('content') ?? [])->where('type','text')->pluck('text')->implode("\n"));
    return $t === '' ? [false, 'empty reply'] : [true, $t];
  }
}
class StoreFallback { public static function phone(): string { $s = \App\Http\Controllers\StoreController::set(); return '0'.ltrim((string)($s['call_number'] ?? '09613828081'), '0'); } }
