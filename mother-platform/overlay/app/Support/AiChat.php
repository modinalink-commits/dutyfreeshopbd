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
  /** Language rule shared by the website chat and the commerce agent: reply in the customer's own style (Bangla / English / Banglish / mixed). $st may hold lang_override (staff setting) and lang_pref (learned). Only wording changes, never facts. */
  public static function langRule(array $st = []): string {
    $names = ['bangla'=>'Bangla (Bengali script)', 'english'=>'English', 'banglish'=>'Banglish (Bengali typed in English letters)'];
    $ov = (string)($st['lang_override'] ?? ''); $pf = (string)($st['lang_pref'] ?? '');
    $r = "LANGUAGE: customers write in Bengali script, English, Banglish (Bengali typed in English letters, e.g. 'dam koto', 'amar order ta kothay') or a mix; understand all of them including spelling mistakes. A message written in Bengali script MUST be answered in Bengali script (never Banglish); a message in English letters that is Bengali words is Banglish. When searching products for a Bengali-script message, search with the English / Latin spelling of the brand or product (e.g. ডিওর সোভাজ -> Dior Sauvage, ডানহিল -> Dunhill), and try more than one spelling before saying a product was not found. Reply in the SAME language and style as the customer's latest message: Bangla -> natural polite Bengali script (আপনি-form); English -> simple polite English; Banglish -> natural everyday Bangladeshi customer-service Banglish in English letters (e.g. 'Ji, apnar order ta ekhon courier e In Transit ache. Notun update ashle janano hobe.'), never stiff literary words like 'bahon prokriyay royeche'; mixed -> match their natural mix. If the latest message is too short to tell (e.g. 'ok', 'dam?'), follow the style of the earlier customer messages in this chat, otherwise reply in Bengali script. A customer who clearly asks for a language (e.g. 'English please', 'বাংলায় বলুন') gets that language. "
      ."Common business words may stay in English where natural (Order, Approved, Pending, Courier, Tracking, Payment, Membership, Discount, Delivery, Refund, Return, Invoice). When replying in Bengali script, write statuses in simple Bengali (Processing -> আপনার অর্ডারটি বর্তমানে প্রস্তুত করা হচ্ছে; Ready to Ship -> আপনার অর্ডারটি কুরিয়ারে পাঠানোর জন্য প্রস্তুত; Payment Pending -> আপনার পেমেন্টটি এখনো যাচাইয়ের অপেক্ষায় আছে). "
      ."The language changes only the wording: never change or add any fact, price, discount, stock, order / payment / courier / delivery status, membership or promise, and never promise more in one language than in another. Use the customer's name naturally, not in every reply. NEVER use the words ভাইয়া, ভাই, আপু, বোন, bhai, bon, apu (any spelling) to address anyone; without a known name just reply politely with no address word. Tone: respectful, warm, short, natural, never robotic.\n";
    if (isset($names[$ov])) $r .= "STAFF LANGUAGE SETTING: a staff member fixed the reply language for this chat to {$names[$ov]}. Use it for every reply, whatever language the customer writes in.\n";
    elseif (isset($names[$pf])) $r .= "LEARNED CUSTOMER PREFERENCE: this customer mostly writes {$names[$pf]}; use it when the latest message does not make the style clear.\n";
    return $r;
  }
  /** Claude AI answers WhatsApp and Facebook Messenger too (on by default; Chat Settings has the switch). */
  public static function chanOn(): bool { return self::on() && Sms::secret('ai_chat_chan') !== '0'; }
  /** Is the AI held back in this conversation? A staff reply or a hand-over pauses it; a staff reply only holds it for 30 minutes, while a hand-over (needs_human) or the manual OFF switch hold it until staff turn it on. */
  public static function paused($t): bool {
    if (!$t || !$t->ai_paused) return false;
    if (!empty($t->needs_human) || Cache::has('ai.manual_off.'.$t->id)) return true;
    $last = DB::table('chat_messages')->where('thread_id', $t->id)->where('dir', 'out')->where(fn($q) => $q->whereNull('is_ai')->orWhere('is_ai', false))->max('created_at');
    return $last && \Illuminate\Support\Carbon::parse($last)->gt(now()->subMinutes(30));
  }
  public static function chatOn(): bool { return self::on() && Sms::secret('ai_chat_auto') !== '0'; }
  public static function model(): string { return Sms::secret('ai_model') ?: 'claude-haiku-5-5'; }
  /** Store-wide facts the assistant may quote (cached 5 min): contact, delivery, payment, policies, offers, categories. */
  public static function facts(): string {
    return Cache::remember('ai.facts4', 300, function () {
      $s = \App\Http\Controllers\StoreController::set(); $o = [];
      $o[] = 'STORE: DutyFreeBD (dutyfreebd.com), Child City. Hotline: '.Biz::hotline().'. Mobile: '.Biz::mobile().'. WhatsApp: '.Biz::whatsapp().'. Email: '.($s['contact_email'] ?? '').'. Address: '.preg_replace('/\s+/', ' ', (string)($s['contact_address'] ?? '')).'. Hours: '.($s['business_hours'] ?? '').'.';
      // the live delivery engine (Admin > Delivery) is the single source of truth, not the old ship_N settings
      try {
        $dc = \App\Support\Delivery::cfg();
        if (!$dc['on']) $o[] = 'DELIVERY CHARGES: no delivery charge is added at the moment (delivery charge is OFF in the system). Final charge is always calculated by the system at order time.';
        else $o[] = 'DELIVERY CHARGES: '.$dc['l_in'].' = '.number_format($dc['inside']).' TK; '.$dc['l_out'].' = '.number_format($dc['outside']).' TK'.($dc['express'] > 0 ? '; '.$dc['l_ex'].' = '.number_format($dc['express']).' TK' : '').($dc['free_on'] && $dc['free_min'] > 0 ? '; FREE delivery on orders of '.number_format($dc['free_min']).' TK or more' : '').'. Final charge (zones, free-delivery rules) is calculated by the system at order time.';
      } catch (\Throwable $e) {}
      $pay = [];
      if (($s['co_cod'] ?? '1') === '1') $pay[] = 'Cash on delivery';
      try { foreach (DB::table('payment_methods')->where('active', true)->orderBy('position')->get() as $m) $pay[] = $m->title.' ('.$m->kind.')'.($m->account_no ? ' account '.$m->account_no.($m->account_name ? ' / '.$m->account_name : '') : '').($m->instruction ? ' - '.mb_substr(strip_tags($m->instruction), 0, 160) : ''); } catch (\Throwable $e) {}
      if ($pay) $o[] = 'PAYMENT OPTIONS: '.implode('; ', $pay).'.';
      try { foreach (DB::table('discount_rules')->where('enabled', true)->get(['title', 'help', 'config']) as $r) $o[] = 'OFFER: '.$r->title.' - '.mb_substr(strip_tags((string)$r->help), 0, 160).' '.mb_substr((string)$r->config, 0, 200); } catch (\Throwable $e) {}
      try { foreach (DB::table('pages')->where('active', true)->limit(14)->get(['title', 'body']) as $pg) { $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$pg->body))); if ($t !== '') $o[] = 'PAGE "'.$pg->title.'": '.mb_substr($t, 0, 700); } } catch (\Throwable $e) {}
      try {
        $cm = []; $pre = \App\Support\PrepaidOnly::catSet();
        foreach (DB::table('categories')->orderBy('position')->limit(40)->get(['id', 'name']) as $c) { $subs = DB::table('products')->where('published', true)->where('category_id', $c->id)->whereNotNull('sub')->where('sub', '<>', '')->select('sub', DB::raw('count(*) n'))->groupBy('sub')->orderByDesc('n')->limit(10)->pluck('sub')->implode(', '); $cm[] = $c->name.($subs !== '' ? ' ('.$subs.')' : '').(isset($pre[$c->id]) ? ' [PREPAID ONLY]' : ''); }
        if ($cm) $o[] = 'CATEGORY MAP (main category with its sub categories; all of these are sold on the website): '.implode('; ', $cm).'.';
        $pn = DB::table('categories')->where('prepaid_only', true)->pluck('name')->implode(', ');
        if ($pn !== '') $o[] = 'PREPAID-ONLY CATEGORIES ('.$pn.'): products here are imported on request, so they are sold with FULL ONLINE PAYMENT ONLY (bKash, Nagad, card or bank transfer), NO cash on delivery and no discount. They are available to order on the website. NEVER say they are out of stock or unavailable just because the stock number is zero: when the CATALOGUE or a tool lists one, it is available. You cannot take these orders in chat: send the product link and ask the customer to order and pay on the website.';
        $o[] = 'Skin Care and Hair Care are now ONE category: Skin & Hair Care (link /skin-care/). Old hair-care links redirect to it.';
      } catch (\Throwable $e) {}
      try { $o[] = 'CATEGORIES: '.DB::table('categories')->orderBy('name')->limit(60)->pluck('name')->implode(', ').'.'; $o[] = 'BRANDS: '.DB::table('brands')->where('active', true)->orderBy('name')->limit(80)->pluck('name')->implode(', ').'.'; } catch (\Throwable $e) {}
      $kb = self::kb(); if ($kb !== '') $o[] = "OWNER KNOWLEDGE BASE (written by the owner; live price, stock, offers, delivery charge and order data from the tools always win over it):\n".$kb;
      return implode("\n", $o);
    });
  }
  /** Owner-editable knowledge base (Admin > AI Chat); the built-in default is used until the owner saves their own. */
  public static function kb(): string {
    $base = ''; $v = Sms::secret('ai_kb');
    if ($v !== null && trim($v) !== '') $base = trim($v);
    else { $f = resource_path('ai/kb_default.txt'); $base = is_file($f) ? trim((string)file_get_contents($f)) : ''; }
    // frequent-question answers (from chat analysis); added once, also to an owner-edited KB, unless the owner already has that section
    foreach (['kb_faq.txt' => 'কাস্টমারের বারবার করা প্রশ্ন', 'kb_lang.txt' => 'কাস্টমারের ভাষা ও ভুল বানান'] as $fn => $mark) {
      $q = resource_path('ai/'.$fn);
      if ($base !== '' && is_file($q) && mb_strpos($base, $mark) === false) $base .= "\n\n".trim((string)file_get_contents($q));
    }
    return $base;
  }
  /** Whole published catalogue in compact form (cached 5 min). */
  private static function catalog(): array {
    return Cache::remember('ai.catalog2', 300, function () {
      $cat = DB::table('categories')->pluck('name', 'id');
      // balanced: up to 60 products from EVERY category (in-stock first) so new categories (Watch, Sunglass, Gadget, Lighter, Skin & Hair Care ...) are never crowded out by the big ones
      $rows = collect(); $cols = ['id', 'ext_id', 'name', 'price', 'offer_price', 'compare_price', 'stock', 'sub', 'sub_sub', 'category_id', 'path_slug', 'size'];
      foreach ($cat as $cid => $cn) { $rows = $rows->concat(StockGuard::listable(DB::table('products')->where('published', true))->where('category_id', $cid)->orderByDesc('stock')->orderByDesc('id')->limit(60)->get($cols)); }
      return $rows->map(function ($p) use ($cat) {
        $now = (float)($p->offer_price ?: $p->price); $was = (float)$p->compare_price > $now ? ' (was '.number_format($p->compare_price).')' : '';
        return ['id'=>$p->id, 'line'=>$p->name.' | '.($cat[$p->category_id] ?? '').($p->sub ? '/'.$p->sub : '').($p->size ? ' | '.$p->size : '').' | '.number_format($now).' TK'.$was.' | '.(\App\Support\StockGuard::sellable($p) ? 'in stock' : 'OUT OF STOCK').' | '.parse_url(\App\Http\Controllers\StoreController::url($p), PHP_URL_PATH)];
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
    if (preg_match('/(?<!\d)(?:INV-?\s?)?(\d{6,8})(?!\d)/i', $all, $m) && preg_match('/(?<!\d)(?:\+?88)?(01[3-9]\d{8})(?!\d)/', $all, $ph)) {
      $o = DB::table('orders')->whereIn('invoice', \App\Support\Inv::candidates($m[1]))->where('phone', $ph[1])->first(['invoice', 'status', 'total', 'courier_status', 'tracking_code', 'created_at']);
      if ($o) $out .= "VERIFIED ORDER (the customer proved ownership with the phone number; you may share this): ".\App\Support\Inv::show($o->invoice).' status '.$o->status.', total '.number_format($o->total).' TK'.($o->courier_status ? ', courier status '.$o->courier_status : '').($o->tracking_code ? ', tracking code '.$o->tracking_code : '').', placed '.\Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y').".\n";
      else $out .= "No order matches that invoice and phone number together.\n";
    }
    return $out;
  }
  /** Reply tone chosen by the owner in Chat Settings (applies to every AI reply). */
  public static function toneRule(): string {
    try {
      $k = (string)Sms::secret('ai_tone'); $raw = (string)Sms::secret('ai_tone_custom'); $c = $raw === '' ? 'প্রতিটি উত্তরের একদম শেষে ধন্যবাদ লিখবে।' : ($raw === '-' ? '' : trim($raw));
      $p = ['friendly'=>'Friendly and warm, like a helpful shop assistant; simple everyday words, a light emoji only when natural.',
        'respectful'=>'Very polite and respectful: always address the customer as "আপনি" (never "তুমি"), use "স্যার/ম্যাডাম" only when the gender is clear, formal but kind Bangla.',
        'professional'=>'Professional and businesslike, calm and precise, no emoji, no slang.',
        'short'=>'Very short and direct: one to three short lines, answer first, no filler words, no emoji.'];
      $r = $p[$k] ?? ''; if ($c !== '') $r .= ($r ? ' ' : '').mb_substr($c, 0, 400);
      $cl = Sms::secret('ai_closing') === '0' ? '' : "CLOSING (owner rule): once you have fully answered what the customer asked and nothing is pending, end that reply with ONE short closing question in the customer's own language and style, e.g. Bangla: \"স্যার, আপনার আর কোনো প্রশ্ন আছে? না থাকলে আমি এখন বিদায় নিচ্ছি।\" / English: \"Sir, do you have any other question? If not, I'll take your leave now.\". Use it only when the topic is finished: never while you are still collecting order details, never while a complaint, refund, return, payment check or hand-off is open, and not after every single message.\n";
      return ($r === '' ? '' : "TONE (set by the owner, follow it in every reply): ".$r."\n").$cl;
    } catch (\Throwable $e) { return ''; }
  }
  public static function reply(array $history, string $extra = '', ?string $lang = null): array {
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
    $sys = "IMPORTANT: You are the customer support AI for DutyFreeBD. Reply in the customer's own language and style (Bangla, English or Banglish); Bangla is the default.\n"
      ."You are the customer assistant of DutyFreeBD (dutyfreebd.com), a Bangladeshi online store for imported and duty-free perfume, skin care, hair care, lifestyle items and cigarettes (cigarettes are sold only by the carton of 200 sticks, never single packs).\n"
      .self::langRule(['lang_override'=>(($lang ?? '') !== '' ? $lang : (preg_match('/[\x{0980}-\x{09FF}]/u', (string)end($msgs)['content']) ? 'bangla' : null))])
      ."STYLE: SIMPLE and VERY SHORT. Use easy everyday words. A normal reply is ONE or TWO short sentences (under 25 words, about 110 Bengali characters); only a price/stock list or an order step may be longer, never more than 4 lines. Say each thing once: never repeat what the customer wrote, never repeat the same offer twice (e.g. say 'নাম বা ছবি পাঠান' once), no 'আমি এখনই দেখে জানাচ্ছি' style filler. Greet with salam ONLY in the first reply of a chat, never again. No emoji. If the question is unclear, ask ONE short question, e.g. 'কোন পণ্যের কথা বলছেন? নাম বা ছবি পাঠান।' Answer exactly what was asked first, then stop: no long intro, no repeating the question, no lists of extra tips, no extra offers. Give only the facts the customer needs. Use plain text only (no markdown, no asterisks). HUMAN VOICE: write exactly like a friendly real shop staff member chatting on WhatsApp: natural spoken Bangla, contractions of speech, no robotic or corporate phrases (never 'আমি একটি এআই', 'আপনার অনুরোধ প্রক্রিয়াধীন', 'সহায়তা প্রদান'), no self-introduction and no mention of being an AI or assistant unless the customer sincerely asks - if asked directly, answer honestly and briefly.\n"
      ."TRUTH: answer ONLY from the STORE FACTS, CATALOGUE, PRODUCT DETAILS and VERIFIED ORDER blocks below. Quote prices, stock, delivery charges, payment options and links exactly as written. Never invent a product, price, discount, delivery time, policy or stock. If the data does not contain the answer, say you are not sure and give the phone/WhatsApp number $call.\n"
      ."PRODUCTS: when the customer asks about a product or category, search the whole CATALOGUE (match Bengali/Banglish names to the English product names, e.g. 'পারফিউম'/'perfume', 'সিগারেট'/'cigarette'). Recommend the best 1 or 2 matching items (3 only if the customer asks for options), each on ONE short line with name, price, stock status and its link path exactly as given (e.g. /p/123-name/). To order: open the product and press ORDER NOW (cash on delivery is available where listed). If an item is OUT OF STOCK say so and suggest in-stock alternatives.\n"
      ."ORDERS: you can only show an order's status if a VERIFIED ORDER block is present. Otherwise ask the customer for the invoice number (INV-xxxxxx) and the phone number used for the order, or send them to /track. Never share any customer's personal data. Never ask for passwords, OTP or card numbers.\n"
      .self::toneRule().$extra."\nSTORE FACTS:\n".self::facts()."\n\nCATALOGUE (name | category | price | stock | link):\n".implode("\n", array_column(self::catalog(), 'line'));
    $detail = self::detail(end($msgs)['content'], $all);
    $sys2 = $detail !== '' ? $detail : '';
    try {
      $res = Http::withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])->timeout(40)->post('https://api.anthropic.com/v1/messages', ['model'=>self::model(), 'max_tokens'=>400, 'system'=>array_values(array_filter([['type'=>'text', 'text'=>$sys, 'cache_control'=>['type'=>'ephemeral']], $sys2 !== '' ? ['type'=>'text', 'text'=>$sys2] : null])), 'messages'=>$msgs]);
    } catch (\Throwable $e) { return [false, 'network']; }
    if (!$res->ok()) return [false, 'HTTP '.$res->status().' '.mb_substr((string)($res->json('error.message') ?? ''), 0, 160)];
    $t = trim(collect($res->json('content') ?? [])->where('type','text')->pluck('text')->implode("\n"));
    return $t === '' ? [false, 'empty reply'] : [true, $t];
  }
  /** Staff drafting aid: fixes spelling, grammar, punctuation and wording of a (voice-typed) reply while keeping the meaning, language and every number / price / link exactly. */
  public static function polish(string $text): array {
    $key = Sms::secret('ai_key'); if (!$key) return [false, 'AI key is not set'];
    $n = 'ai.polish.'.now()->format('Ymd'); if ((int)Cache::get($n, 0) >= 600) return [false, 'daily limit reached']; Cache::put($n, (int)Cache::get($n, 0) + 1, 90000);
    $sys = "You polish a customer-service reply typed by a DutyFreeBD staff member, often by voice dictation, so it may contain wrong or missing words, mis-heard words, no punctuation or broken sentences. Rewrite it as a clear, polite, natural message a friendly shopkeeper would send. Rules: keep the same language and script as the draft (Bengali stays Bengali, English stays English, Banglish stays Banglish; a Bengali draft with English business words may keep them); keep the exact meaning and DO NOT add any new fact, promise, price, discount, date or link; keep every number, price, phone number, order number and link exactly as written; keep it short; never use the words ভাইয়া, ভাই, আপু, বোন, bhai, bon, apu; no markdown. Output ONLY the corrected message, nothing else.";
    try {
      $res = Http::withHeaders(['x-api-key'=>$key, 'anthropic-version'=>'2023-06-01'])->timeout(25)->post('https://api.anthropic.com/v1/messages', ['model'=>self::model(), 'max_tokens'=>900, 'system'=>$sys, 'messages'=>[['role'=>'user', 'content'=>mb_substr($text, 0, 3000)]]]);
    } catch (\Throwable $e) { return [false, 'network']; }
    if (!$res->ok()) return [false, 'HTTP '.$res->status()];
    $t = trim(collect($res->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n"));
    return $t === '' ? [false, 'empty reply'] : [true, $t];
  }
}
class StoreFallback { public static function phone(): string { return Biz::hotline(); } }
