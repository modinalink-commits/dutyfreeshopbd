<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
/** Server-side tools for the Commerce Agent. The AI only talks and asks; every rule (price, stock, confirmation, permissions) is enforced here, never by the prompt. */
class AgentTools {
  /** Anthropic tool schemas. Nothing here can refund, verify a payment, cancel, change a price or discount, or delete anything. */
  public static function schemas(bool $orders): array {
    $s = fn($name, $desc, $props = [], $req = []) => ['name'=>$name, 'description'=>$desc, 'input_schema'=>['type'=>'object', 'properties'=>(object)$props, 'required'=>$req]];
    $str = ['type'=>'string']; $int = ['type'=>'integer'];
    $t = [
      $s('search_products', 'Search the live catalogue by name, brand or category (Bengali, English or Banglish). Returns price, stock status and link from the database.', ['query'=>$str, 'limit'=>$int], ['query']),
      $s('product_details', 'Description and live price/stock of one product by id.', ['product_id'=>$int], ['product_id']),
      $s('delivery_info', 'Delivery charge options (inside / outside Chattogram city) and the live payment methods.'),
      $s('current_offers', 'LIVE offers right now: delivery charge rule (free or not), today\'s deals with prices, and the automatic discount rules. Call this for any question about offers, discounts, deals, free delivery or delivery charge.'),
      $s('order_status', 'Status, total, courier status and tracking code of an order. Requires BOTH the invoice number and the phone number used for that order.', ['invoice'=>$str, 'phone'=>$str], ['invoice', 'phone']),
      $s('cart_add', 'Put a product in this conversation\'s cart with EXACTLY this quantity (it SETS the quantity, it does not add to what is already there; live price and stock are checked). Check cart_show first if unsure.', ['product_id'=>$int, 'qty'=>$int], ['product_id']),
      $s('cart_remove', 'Remove a product from the cart.', ['product_id'=>$int], ['product_id']),
      $s('cart_show', 'Show the cart with live prices and the order total (no delivery).'),
      $s('prepare_order', 'Validate the customer details and build the order summary for the customer to confirm. Nothing is placed yet.', ['name'=>$str, 'phone'=>$str, 'address'=>$str, 'area'=>['type'=>'integer', 'description'=>'1 = inside Chattogram city, 2 = outside'], 'note'=>$str], ['name', 'phone', 'address', 'area']),
      $s('report_conversation', 'Call once per customer message: classify the conversation. intent: greeting|product_question|price|availability|order_status|order_request|delivery|payment|complaint|refund|cancel|change_order|human_request|other. mood: calm|happy|confused|impatient|angry|upset. confidence 0-100 = how sure you are your answer is correct and complete. language = the style of the customer\'s latest message: bangla (Bengali script) | english | banglish (Bengali in English letters) | mixed.', ['intent'=>$str, 'mood'=>$str, 'confidence'=>$int, 'language'=>$str], ['intent', 'mood', 'confidence']),
      $s('handoff_to_human', 'Pass the conversation to a human agent. Use for: refund, payment verification, cancel or change of an order, complaint, angry customer, discount demands, anything you are not sure about, or when the customer asks for a person. Give a short Bengali summary for the agent.', ['reason'=>$str, 'summary'=>$str, 'priority'=>['type'=>'string', 'enum'=>['normal', 'high', 'urgent']]], ['reason', 'summary']),
    ];
    if ($orders) $t[] = $s('place_order', 'Place the prepared order. Works ONLY after prepare_order AND after the customer\'s latest message clearly confirms it (e.g. "হ্যাঁ", "confirm"). The server re-checks this.');
    return $t;
  }
  private static function money($v): string { return number_format((float)$v).' TK'; }
  private static function norm(string $p): string { $d = preg_replace('/\D/', '', $p); return strlen($d) >= 11 ? substr($d, -11) : $d; }
  private static function validPhone(string $p): bool { return (bool)preg_match('/^01[3-9]\d{8}$/', $p); }
  private static function stockTxt($p): string { return StockGuard::sellable($p) ? 'in stock' : 'OUT OF STOCK'; }
  private static function row($p): array { $now = (float)$p->price; return ['id'=>$p->id, 'name'=>$p->name, 'price'=>self::money($now), 'was'=>(float)$p->compare_price > $now ? self::money($p->compare_price) : null, 'stock'=>self::stockTxt($p), 'link'=>url(\App\Http\Controllers\StoreController::url($p))]; }

  /** Runs one tool. $st is the conversation state (cart, pending order), changed by reference. ctx: thread_id, sim, orders_on, last_msg_id, last_msg_text. Returns an array the model sees. */
  public static function run(string $name, array $in, array &$st, array $ctx): array {
    try { $r = self::exec($name, $in, $st, $ctx); } catch (\Throwable $e) { report($e); $r = ['error'=>'tool failed, hand over to a human agent']; }
    self::log($ctx, $name, $in, $r);
    return $r;
  }
  private static function log(array $ctx, string $tool, array $in, array $r): void {
    try {
      $safe = $in; foreach (['phone'] as $k) if (isset($safe[$k])) $safe[$k] = substr((string)$safe[$k], 0, 3).'***'.substr((string)$safe[$k], -2);
      DB::table('ai_activity_logs')->insert(['thread_id'=>$ctx['thread_id'] ?? null, 'customer_id'=>$ctx['customer_id'] ?? null, 'tool'=>$tool, 'input'=>mb_substr(json_encode($safe, JSON_UNESCAPED_UNICODE), 0, 1500),
        'result'=>mb_substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 1500), 'ok'=>!isset($r['error']), 'sim'=>!empty($ctx['sim']), 'created_at'=>now()]);
    } catch (\Throwable $e) {}
  }
  private static function exec(string $name, array $in, array &$st, array $ctx): array {
    switch ($name) {
      case 'search_products': {
        $q = trim(mb_substr((string)($in['query'] ?? ''), 0, 80)); if ($q === '') return ['error'=>'empty query'];
        $words = array_slice(array_values(array_filter(preg_split('/[\s,.?!]+/u', mb_strtolower($q)), fn($w) => mb_strlen($w) >= 2)), 0, 5);
        $rows = StockGuard::listable(DB::table('products')->where('published', true))->where(function ($w) use ($words) { foreach ($words as $t) { $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $t).'%'; $w->orWhere('name', 'ilike', $like)->orWhere('sub', 'ilike', $like)->orWhere('size', 'ilike', $like); } })
          ->limit(80)->get(['id', 'name', 'price', 'compare_price', 'stock', 'sub', 'category_id', 'path_slug', 'sub_sub', 'size']);
        // best match first: the product whose name holds the most of the customer's words (e.g. 'dunhill switch blue' finds Dunhill Switch 8 Mg (Blue) before any other Dunhill)
        $rows = $rows->map(function ($p) use ($words) { $l = mb_strtolower($p->name.' '.($p->size ?? '')); $sc = 0; foreach ($words as $w) if (str_contains($l, $w)) $sc++; return [$sc, $p]; })->sort(fn($a, $b) => [$b[0], ($b[1]->stock > 0 ? 1 : 0), $b[1]->id] <=> [$a[0], ($a[1]->stock > 0 ? 1 : 0), $a[1]->id])->take(max(1, min(6, (int)($in['limit'] ?? 4))))->map(fn($x) => $x[1])->values();
        return ['count'=>$rows->count(), 'products'=>$rows->map(fn($p) => self::row($p))->all(), 'note'=>$rows->count() ? 'only in-stock published products are listed' : 'no available product matches: say it is currently not available, never mention or link anything else out of stock']; 
      }
      case 'product_details': {
        $p = DB::table('products')->where('published', true)->find((int)($in['product_id'] ?? 0)); if (!$p) return ['error'=>'product not found'];
        return self::row($p) + ['description'=>mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags((string)$p->description))), 0, 600)];
      }
      case 'delivery_info': {
        $o = \App\Support\Delivery::options(); $pay = [];
        try { foreach (DB::table('payment_methods')->where('active', true)->orderBy('position')->get() as $m) $pay[] = $m->title.' ('.$m->kind.')'; } catch (\Throwable $e) {}
        return ['areas'=>collect($o)->map(fn($a, $k) => ['area'=>$k, 'name'=>$a['n'], 'charge'=>self::money($a['c'])])->values()->all(), 'payment_methods'=>$pay, 'note'=>'Final delivery charge and any discount are calculated by the system at order time.'];
      }
      case 'current_offers': {
        $c = \App\Support\Delivery::cfg(); $out = [];
        if (!$c['on']) $out['delivery'] = 'FREE: no delivery charge is added to any order at the moment (delivery charge is switched off in the system).';
        else {
          $out['delivery'] = ['inside_chattogram'=>self::money($c['inside']), 'outside_chattogram'=>self::money($c['outside']), 'free_over'=>($c['free_on'] && $c['free_min'] > 0) ? self::money($c['free_min']) : null, 'note'=>'Zone or product rules may change the final charge; it is calculated when the order is prepared.']; }
        try { $deals = \App\Http\Controllers\StoreController::dealList(\App\Http\Controllers\StoreController::set())->take(8);
          $out['todays_deals'] = $deals->map(fn($p) => ['id'=>$p->id, 'name'=>$p->name, 'price'=>self::money($p->price), 'was'=>self::money($p->compare_price), 'off_percent'=>(int)round(100 * ((float)$p->compare_price - (float)$p->price) / max(1, (float)$p->compare_price)), 'stock'=>self::stockTxt($p), 'link'=>url(\App\Http\Controllers\StoreController::url($p))])->values()->all(); } catch (\Throwable $e) { $out['todays_deals'] = []; }
        try { $out['automatic_discounts'] = DB::table('discount_rules')->where('enabled', true)->get(['title', 'help', 'config'])->map(fn($r) => ['title'=>$r->title, 'details'=>mb_substr(strip_tags((string)$r->help), 0, 200), 'config'=>mb_substr((string)$r->config, 0, 200)])->all(); } catch (\Throwable $e) { $out['automatic_discounts'] = []; }
        $out['note'] = 'Coupon codes are personal and applied at checkout; never invent or reveal a code. Only quote what is listed here.';
        return $out;
      }
      case 'order_status': {
        $inv = strtoupper(trim((string)($in['invoice'] ?? ''))); $inv = str_replace(' ', '', $inv);
        $ph = self::norm((string)($in['phone'] ?? '')); if (!self::validPhone($ph)) return ['error'=>'a valid 11-digit phone number is needed'];
        $o = DB::table('orders')->whereIn('invoice', \App\Support\Inv::candidates($inv) ?: ['-'])->where('phone', $ph)->first(['invoice', 'status', 'total', 'courier_status', 'tracking_code', 'created_at']);
        if (!$o) return ['found'=>false, 'message'=>'No order matches that invoice and phone together.'];
        return ['found'=>true, 'invoice'=>$o->invoice, 'status'=>$o->status, 'total'=>self::money($o->total), 'courier_status'=>$o->courier_status, 'tracking_code'=>$o->tracking_code, 'placed'=>\Carbon\Carbon::parse($o->created_at)->timezone('Asia/Dhaka')->format('d M Y')];
      }
      case 'cart_add': {
        $p = DB::table('products')->where('published', true)->find((int)($in['product_id'] ?? 0)); if (!$p) return ['error'=>'product not found'];
        if (!StockGuard::sellable($p)) return ['error'=>'this product is OUT OF STOCK'];
        if (\App\Support\PrepaidOnly::any([(int)$p->id])) return ['error'=>'This product is in a prepaid-only category (Watch, Sunglass, Gadget & Electronics, Lighter & Accessories ...): it is AVAILABLE, but it cannot be ordered through chat. Do not say it is out of stock. Send the product link and ask the customer to order on the website and pay fully online (bKash, Nagad, card or bank transfer); there is no cash on delivery.'];
        // age-restricted goods (cigarette / vape / tobacco): the owner's rule is that the assistant answers questions but never takes the order itself
        try { $cn = (string)DB::table('categories')->where('id', $p->category_id ?? 0)->value('name'); } catch (\Throwable $e) { $cn = ''; }
        // owner's rule: the assistant takes cigarette/vape orders like any other (summary + customer confirmation) but they are never created automatically: they land in Order Requests for staff to create
        $tob = (bool)preg_match('/cigarette|vape|tobacco|nicotine/i', $cn.' '.$p->name);
        $q = max(1, min(20, (int)($in['qty'] ?? 1))); if (StockGuard::mode($p->category_id ?? null) === 'STOCK_CONTROLLED' && $p->stock < $q) return ['error'=>'only '.(int)$p->stock.' in stock'];
        if (time() - (int)($st['cart_at'] ?? 0) > 21600) { $st['cart'] = []; unset($st['tobacco']); } $st['cart_at'] = time(); if ($tob) $st['tobacco'] = true;
        $st['cart'][$p->id] = min(20, $q); unset($st['pending']);
        return ['added'=>$p->name, 'qty_in_cart'=>$st['cart'][$p->id]];
      }
      case 'cart_remove': { unset($st['cart'][(int)($in['product_id'] ?? 0)], $st['pending']); return ['removed'=>true]; }
      case 'cart_show': return self::cart($st);
      case 'prepare_order': {
        $c = self::cart($st); if (empty($c['items'])) return ['error'=>'the cart is empty'];
        if (!empty($c['problems'])) return ['error'=>implode('; ', $c['problems'])];
        $name = trim(mb_substr((string)($in['name'] ?? ''), 0, 120)); $ph = self::norm((string)($in['phone'] ?? '')); $addr = trim(mb_substr((string)($in['address'] ?? ''), 0, 500)); $area = (int)($in['area'] ?? 0);
        if ($name === '' || mb_strlen($addr) < 8) return ['error'=>'full name and a complete address are needed'];
        if (!self::validPhone($ph)) return ['error'=>'a valid Bangladeshi mobile number (01XXXXXXXXX) is needed'];
        $opts = \App\Support\Delivery::options(); if (!isset($opts[$area])) return ['error'=>'area must be 1 (inside Chattogram city) or 2 (outside)'];
        $ship = (float)$opts[$area]['c'];
        $dvPaid = false; try { $dv = \App\Support\Delivery::compute((float)$c['subtotal_raw'], $area, $addr, '', array_keys($st['cart'] ?? [])); $ship = (float)$dv['charge']; $dvPaid = !empty($dv['paid']); } catch (\Throwable $e) { report($e); }
        $disc = 0.0; $dnotes = [];
        try {
          $eng = \App\Support\OrderDiscount::compute($ph, (float)$c['subtotal_raw'], null, ['web'=>true, 'verified'=>false, 'qty'=>array_sum($st['cart'] ?? []), 'items'=>array_map(fn($i) => ['product_id'=>$i['id'], 'price'=>(float)\Illuminate\Support\Facades\DB::table('products')->where('id', $i['id'])->value('price'), 'qty'=>$i['qty']], $c['items'])]);
          $disc = min((float)$eng['total'], (float)$c['subtotal_raw']); $dnotes = array_column(array_filter($eng['lines'], fn($l) => $l['applied']), 'label');
          if (!empty($eng['free_ship']) && $ship > 0 && !$dvPaid) $ship = 0.0;
        } catch (\Throwable $e) { report($e); }
        $total = $c['subtotal_raw'] + $ship - $disc;
        $pd = $st['pending'] ?? null;
        if (!empty($ctx['orders_on']) && $pd && $pd['name'] === $name && $pd['phone'] === $ph && $pd['address'] === $addr && (int)$pd['area'] === $area && $pd['cart'] == ($st['cart'] ?? []) && self::isConfirm((string)($ctx['last_msg_text'] ?? '')) && (int)($ctx['last_msg_id'] ?? 0) > (int)$pd['after_msg'])
          return ['error'=>'This exact summary was already shown and the customer has just confirmed it. Do NOT prepare again: call place_order now.'];
        // Returning WhatsApp customer (details come from the server, not the model): his own explicit order command is the confirmation, so the order is placed at once on the saved address.
        $sv = $st['saved'] ?? null; $direct = false;
        if (!empty($ctx['orders_on']) && $sv && $name === $sv['name'] && $ph === $sv['phone'] && $addr === $sv['address'] && self::isConfirm((string)($ctx['last_msg_text'] ?? '')) && (int)($ctx['last_msg_id'] ?? 0) > 0) $direct = true;
        $st['pending'] = ['name'=>$name, 'phone'=>$ph, 'address'=>$addr, 'area'=>$area, 'note'=>mb_substr((string)($in['note'] ?? ''), 0, 300), 'cart'=>$st['cart'], 'total'=>$total, 'after_msg'=>$direct ? (int)$ctx['last_msg_id'] - 1 : (int)($ctx['last_msg_id'] ?? 0), 'at'=>time()];
        if ($direct) return ['summary'=>['items'=>$c['items'], 'total_estimate'=>self::money($total), 'name'=>$name, 'phone'=>$ph, 'address'=>$addr], 'next'=>'The returning customer just asked to place the order, so that counts as his confirmation: call place_order NOW (same turn). Then tell him in your reply the invoice number, total and Cash on delivery.'];
        return ['summary'=>['items'=>$c['items'], 'subtotal'=>self::money($c['subtotal_raw']), 'delivery'=>self::money($ship), 'delivery_note'=>isset($dv) ? ($dv['reason'] ?: ($ship <= 0 ? 'No delivery charge' : $dv['zone'])) : null, 'automatic_discount'=>$disc > 0 ? self::money($disc).' ('.implode(', ', $dnotes).')' : 'none', 'total_estimate'=>self::money($total), 'name'=>$name, 'phone'=>$ph, 'address'=>$addr, 'payment'=>'Cash on delivery'],
          'next'=>'Show this summary (items, delivery, automatic_discount if any, and the exact total_estimate) and ask the customer to confirm. Do NOT place the order until they clearly say yes.'];
      }
      case 'place_order': return self::place($st, $ctx);
      case 'report_conversation': {
        $okI = ['greeting', 'product_question', 'price', 'availability', 'order_status', 'order_request', 'delivery', 'payment', 'complaint', 'refund', 'cancel', 'change_order', 'human_request', 'other'];
        $okM = ['calm', 'happy', 'confused', 'impatient', 'angry', 'upset'];
        $i = in_array($in['intent'] ?? '', $okI, true) ? $in['intent'] : 'other'; $m = in_array($in['mood'] ?? '', $okM, true) ? $in['mood'] : 'calm'; $conf = max(0, min(100, (int)($in['confidence'] ?? 50)));
        $lg = strtolower((string)($in['language'] ?? '')); $lg = in_array($lg, ['bangla', 'english', 'banglish', 'mixed'], true) ? $lg : null;
        $st['meta'] = ['intent'=>$i, 'mood'=>$m, 'confidence'=>$conf, 'lang'=>$lg];
        // server-side safety net: these always need a person, whatever the model says
        $st['force_handoff'] = in_array($i, ['refund', 'cancel', 'change_order', 'complaint', 'human_request'], true) || in_array($m, ['angry', 'upset'], true) || $conf < 40;
        return ['ok'=>true, 'handoff_required'=>$st['force_handoff']];
      }
      case 'handoff_to_human': return AgentHandoff::create($ctx, $st, mb_substr((string)($in['reason'] ?? ''), 0, 200), mb_substr((string)($in['summary'] ?? ''), 0, 1200), in_array($in['priority'] ?? '', ['high', 'urgent'], true) ? $in['priority'] : 'normal');
    }
    return ['error'=>'unknown tool'];
  }
  private static function cart(array $st): array {
    $want = array_filter((array)($st['cart'] ?? []), fn($q) => $q > 0); if (!$want) return ['items'=>[], 'subtotal'=>self::money(0), 'subtotal_raw'=>0];
    $rows = DB::table('products')->where('published', true)->whereIn('id', array_keys($want))->get()->keyBy('id'); $items = []; $sub = 0; $prob = [];
    foreach ($want as $id => $q) {
      $p = $rows[$id] ?? null; if (!$p) { $prob[] = 'product #'.$id.' is no longer available'; continue; }
      if (StockGuard::mode($p->category_id ?? null) === 'STOCK_CONTROLLED' && $p->stock < $q) $prob[] = $p->name.': only '.(int)$p->stock.' in stock';
      $line = (float)$p->price * $q; $sub += $line; $items[] = ['id'=>$p->id, 'name'=>$p->name, 'qty'=>$q, 'unit'=>self::money($p->price), 'line'=>self::money($line)];
    }
    return ['items'=>$items, 'subtotal'=>self::money($sub), 'subtotal_raw'=>$sub, 'problems'=>$prob];
  }
  /** The customer must have said a clear yes AFTER the summary was shown. Checked here, in code. */
  public static function isConfirm(string $t): bool {
    $t = mb_strtolower(trim($t));
    if ($t === '' || preg_match('/(না(?![\p{L}\p{M}])|করবেন না|বাতিল|cancel|(?<![\p{L}\p{M}])no(?![\p{L}\p{M}])|not now|change|পরিবর্তন|দাম কত|\?|কি(?![\p{L}\p{M}])|কত)/iu', $t)) return false;
    return (bool)preg_match('/^(হ্যাঁ|হ্যা|হা|ঠিক আছে|কনফার্ম|confirm|confirmed|yes|yep|ok|okay|ওকে|জি|জ্বি|করুন|অর্ডার করুন|place order|done)(?![\p{L}\p{M}])/iu', $t) || (bool)preg_match('/(কনফার্ম|confirm|অর্ডার.*(করুন|করে দিন)|হ্যাঁ)/iu', $t);
  }
  /** The customer-side rules of Auto Approve (AI Verify) decide whether the AI may place the order itself: blocked / suspended / review-required customers, and every new customer (no delivered order, any amount), become an Order Request for staff. All other checks (fraud, duplicate, stock, price...) still run on the created order exactly as for any order. */
  public static function aiEligible(string $phone, float $total = 0): array {
    try {
      $c = DB::table('customers')->where('phone', $phone)->first(); if ($c && !empty($c->blocked)) return [false, 'blocked customer'];
      $st = (string)(\App\Support\CustomerBenefit::check($phone)['status'] ?? '');
      if (in_array($st, ['Suspended', 'Review Required'], true)) return [false, 'customer status: '.$st];
      $d = (int)DB::table('orders')->where('phone', $phone)->where('status', 'Delivered')->count();
      if ($d === 0) return [false, 'new customer: manual verification required'];
      return [true, ''];
    } catch (\Throwable $e) { report($e); return [false, 'eligibility check failed']; }
  }
  /** Creates the real order through the storefront order service (single source of truth). $status: 'AI Order' for AI-created, null = normal New for staff-created. */
  public static function make(array $p, int $threadId, string $incKey, ?string $status, string $by, string $tag): array {
    $req = \Illuminate\Http\Request::create('/order', 'POST', ['items'=>$p['cart'], 'name'=>$p['name'], 'phone'=>$p['phone'], 'address'=>$p['address'], 'area'=>$p['area'], 'note'=>trim($tag.' '.($p['note'] ?? '')), 'pay'=>'cod', 'inc'=>$incKey]);
    $res = app(\App\Http\Controllers\StoreController::class)->order($req);
    $url = $res instanceof \Illuminate\Http\RedirectResponse ? $res->getTargetUrl() : '';
    if ($url !== '' && str_contains($url, '/checkout')) { parse_str((string)parse_url($url, PHP_URL_QUERY), $q); return ['ok'=>false, 'error'=>'the system rejected the order: '.mb_substr((string)($q['e'] ?? 'unknown'), 0, 160)]; }
    $inv = null; if (preg_match('#(INV-\d+)#', $url, $m)) $inv = $m[1];
    if (!$inv) $inv = DB::table('orders')->where('phone', $p['phone'])->where('created_at', '>', now()->subMinutes(3))->orderByDesc('id')->value('invoice');
    if (!$inv) return ['ok'=>false, 'error'=>'could not confirm the order was created'];
    $o = DB::table('orders')->where('invoice', $inv)->first(['id', 'total']);
    if ($o) {
      $upd = ['source'=>'AI Chat', 'updated_at'=>now()]; if ($status) $upd['status'] = $status;
      DB::table('orders')->where('id', $o->id)->update($upd);
      if ($status) DB::table('order_status_logs')->insert(['order_id'=>$o->id, 'user_id'=>null, 'user_name'=>$by, 'from_status'=>'New', 'to_status'=>$status, 'note'=>'Placed by '.$by.' after the customer confirmed in chat. Staff: please verify before processing.', 'created_at'=>now(), 'updated_at'=>now()]);
    }
    OrderIntent::close($threadId, $o->id ?? null, $inv);
    return ['ok'=>true, 'invoice'=>$inv, 'order_id'=>$o->id ?? null, 'total'=>(float)($o->total ?? $p['total'] ?? 0)];
  }
  private static function saveRequest(array $p, array $st, array $ctx, string $reason): int {
    $c = self::cart($st); $ch = null; try { $ch = DB::table('chat_threads')->where('id', (int)($ctx['thread_id'] ?? 0))->value('channel'); } catch (\Throwable $e) {}
    try { DB::table('chat_order_requests')->where('thread_id', (int)($ctx['thread_id'] ?? 0))->where('status', 'review')->where('reason', 'like', 'Wants to order (word:%')->update(['status'=>'dismissed', 'handled_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) {}
    return (int)DB::table('chat_order_requests')->insertGetId(['thread_id'=>(int)($ctx['thread_id'] ?? 0), 'channel'=>$ch, 'name'=>$p['name'], 'phone'=>$p['phone'], 'address'=>$p['address'], 'area'=>(int)$p['area'], 'note'=>$p['note'] ?? null, 'cart'=>json_encode($p['cart']), 'items'=>json_encode($c['items'] ?? []), 'total'=>(float)($p['total'] ?? 0), 'status'=>'review', 'reason'=>mb_substr($reason, 0, 200), 'created_at'=>now(), 'updated_at'=>now()]);
  }
  private static function place(array &$st, array $ctx): array {
    if (empty($ctx['orders_on'])) return ['error'=>'order placing by the assistant is switched off; hand over to a human agent', 'handoff_required'=>true];
    $p = $st['pending'] ?? null; if (!$p) return ['error'=>'call prepare_order first'];
    if (time() - (int)$p['at'] > 1800) { unset($st['pending']); return ['error'=>'the summary expired; prepare the order again'];}
    if ((int)($ctx['last_msg_id'] ?? 0) <= (int)$p['after_msg']) return ['error'=>'the customer has not confirmed yet; show the summary and wait for a clear yes'];
    if (!self::isConfirm((string)($ctx['last_msg_text'] ?? ''))) return ['error'=>'the customer\'s last message is not a clear confirmation; ask again'];
    if (($p['cart'] ?? []) != ($st['cart'] ?? [])) { unset($st['pending']); return ['error'=>'the cart changed after the summary; prepare the order again'];}
    if (\App\Support\PrepaidOnly::any(array_map('intval', array_keys((array)($p['cart'] ?? []))))) return ['error'=>'Prepaid-only products (Watch, Sunglass, Gadget, Lighter) cannot be ordered through chat: they need full online payment on the website. Do not place this order; send the customer the product link and ask them to order and pay online.', 'order_created'=>false];
    if (!empty($ctx['sim'])) return ['simulated'=>true, 'message'=>'Simulation: all checks passed, no real order was created.'];
    [$ok, $why] = self::aiEligible((string)$p['phone'], (float)($p['total'] ?? 0));
    if ($ok && !empty($st['tobacco'])) [$ok, $why] = [false, 'cigarette/vape order: staff confirms before creating'];
    if (!$ok) {
      $rid = self::saveRequest($p, $st, $ctx, $why); AiMemory::event((int)($ctx['thread_id'] ?? 0), null, 'ai', 'order_request', null, ['request'=>$rid, 'why'=>$why, 'total'=>$p['total'] ?? 0]);
      $h = AgentHandoff::create($ctx, $st, 'order_request', 'Order Request #'.$rid.' (not placed: '.$why.'): '.$p['name'].' '.$p['phone'].', total '.self::money($p['total'] ?? 0).'. Review it in Messages > Order Requests.', 'normal');
      $st['cart'] = []; unset($st['pending']);
      return ['request_saved'=>true, 'order_created'=>false, 'tell_customer'=>'আপনার অর্ডার রিকোয়েস্টটি পেয়েছি। আমাদের টিম যাচাই করে কনফার্ম করবে। (Say it is a request. Never say the order is placed or give an invoice number.)'] + $h;
    }
    $r = self::make($p, (int)($ctx['thread_id'] ?? 0), 'ai-'.($ctx['thread_id'] ?? 0).'-'.md5(json_encode($p['cart']).$p['phone'].(int)$p['after_msg']), 'AI Order', 'Claude AI', '[AI Chat]');
    if (empty($r['ok'])) return ['error'=>$r['error'].' - hand over to a human agent', 'handoff_required'=>true];
    $st['cart'] = []; unset($st['pending']); AiMemory::event((int)($ctx['thread_id'] ?? 0), null, 'ai', 'order_created', null, ['invoice'=>$r['invoice'], 'total'=>$r['total']]);
    \Illuminate\Support\Facades\Cache::put('ai.invoice.'.(int)($ctx['thread_id'] ?? 0), $r['invoice'], 600); // Chat::aiReply sends the secure invoice link right after the AI's message
    return ['placed'=>true, 'invoice'=>$r['invoice'], 'total'=>self::money($r['total']), 'payment'=>'Cash on delivery', 'note'=>'The system sends the invoice link by itself right after your message: do not paste any link. A team member will confirm shortly. Do not promise a delivery time.'];
  }
}
