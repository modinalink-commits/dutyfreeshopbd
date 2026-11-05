<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * AI Brief Builder: staff describe an AI task in structured fields BEFORE it runs, so the AI never has to guess.
 * The brief is saved with versions; "Run AI" sends the whole brief plus a fresh snapshot of the attached order / customer / product facts to Claude.
 * Safe by design: this tool only DRAFTS or SUGGESTS. It never sends a message, changes an order, a payment or a price. Internal cost / profit / fraud data is never put into the AI context.
 */
class AiTaskBrief {
  const TYPES = ['Customer Care', 'Marketing', 'Product', 'Order', 'Inventory', 'Reports & Analytics', 'SEO', 'Content', 'Design / Creative', 'Development', 'Accounts', 'Courier', 'Internal Operations', 'Other'];
  const PRIORITY = ['Low', 'Normal', 'High', 'Urgent'];
  const MODES = ['Assist', 'Analyze', 'Create'];
  const AUDIENCE = ['New Customer', 'Existing Customer', 'VIP Customer', 'Inactive Customer', 'Staff', 'Supplier', 'Courier', 'Admin', 'Website Visitor'];
  const ATTACH = ['Customer' => 'customer', 'Order / Invoice' => 'order', 'Product' => 'product'];
  const SOURCES = ['Current Page', 'Customer Profile', 'Order History', 'Product Database', 'Inventory', 'Reports', 'Message History', 'Call History', 'Courier Data', 'Discount Engine', 'Manual Input', 'Selected Files'];
  const OUTPUTS = ['Short Answer', 'Detailed Analysis', 'Customer Message', 'SMS', 'WhatsApp Message', 'Email', 'Report', 'Development Prompt', 'Design Prompt', 'SEO Content', 'Product Description', 'Summary', 'Recommendation', 'Action Plan', 'JSON / Structured Data'];
  const LENGTHS = ['Very Short', 'Short', 'Standard', 'Detailed', 'Comprehensive'];
  const LANGS = ['Bangla', 'English', 'Bangla + English', 'Auto Detect'];
  const TONES = ['Professional', 'Friendly', 'Premium', 'Formal', 'Short & Direct', 'Customer Care', 'Sales', 'Technical', 'Strict', 'Neutral'];
  const PERMS = ['Read Only', 'Suggest Only', 'Draft Only', 'Action with Confirmation', 'Authorized Action'];
  const ACTIONS = ['Can Read Customer Data', 'Can Read Orders', 'Can Search Products', 'Can Create Draft Message', 'Can Suggest Discount', 'Can Update Cart', 'Can Create Order', 'Can Change Status', 'Can Send Message'];
  const CONFIRM = ['Customer Message Send', 'Order Creation', 'Order Cancellation', 'Payment Action', 'Discount Override', 'Refund', 'Status Change'];
  const CONF = ['Normal', 'High', 'Very High'];
  const ESC = ['Ask Staff', 'Send to Human Review', 'Create Draft Only', 'Stop Action'];
  const DAILY_RUNS = 40;

  /** Rules loaded automatically from the Brief Type (staff may edit them in the brief). */
  public static function rulesFor(string $type): string {
    $core = ['Never invent prices, stock, customer facts, payment status, courier status, discount eligibility or business policy: if it is not in the brief or the attached facts, say it is missing.', 'Never mention internal FIFO cost, profit, fraud score, staff notes or API information to a customer.'];
    $by = [
      'Order' => ['Order status rules: only the status shown in the attached order facts is true.', 'Payment rules: do not say a payment is received unless the order facts show it is verified.', 'Stock rules: do not promise an order ships without stock.', 'Courier rules: do not promise an exact delivery time.', 'Discount rules: bundle, membership and coupon discounts follow the Discount Engine; the minimum protected profit ' . ProfitFloor::pct() . '% is internal and never disclosed.'],
      'Customer Care' => ['Do not promise exact delivery time.', 'Do not disclose the internal fraud score or risk label to the customer.', 'Do not mark a payment as paid without a verified transaction.'],
      'Product' => ['Product category and stock mode decide what can be said about availability.', 'Pricing rules: never state a price that is not in the attached product facts; minimum protected profit ' . ProfitFloor::pct() . '% is internal.', 'SEO rules: natural Bangla/English, no keyword stuffing, no false claims about origin or authenticity.'],
      'Marketing' => ['Discount eligibility: only offers that exist in the Discount Engine may be promised.', 'Customer segmentation: respect the chosen target audience.', 'Campaign rules: one clear call to action; the winback / app-install rules set by the owner apply.', 'Communication restrictions: SMS in Bangla is 70 characters per part (67 when split); keep it short.'],
      'Inventory' => ['Stock numbers come only from the attached facts or the brief; never estimate stock.'],
      'Accounts' => ['Never state profit, cost or margin to anyone outside Admin.'],
      'Courier' => ['Courier status comes only from the attached order facts.'],
    ];
    return implode("\n", array_merge($core, $by[$type] ?? []));
  }

  public static function ready(): bool { try { return Schema::hasTable('ai_task_briefs') && Schema::hasTable('ai_task_brief_versions') && Schema::hasTable('ai_task_templates'); } catch (\Throwable $e) { return false; } }

  /** Empty brief with sensible defaults (Draft Only, Bangla, Send to Human Review, human confirmation on every sensitive action). */
  public static function blank(): array {
    return ['title' => '', 'type' => '', 'priority' => 'Normal', 'ai_mode' => 'Assist', 'objective' => '', 'context' => '', 'audience' => [], 'attach' => [], 'sources' => [], 'source_priority' => '', 'must_know' => '', 'must_include' => [], 'must_not' => [], 'rules' => '',
      'output' => '', 'length' => 'Standard', 'language' => 'Bangla', 'tone' => 'Professional', 'reference' => '', 'files' => [], 'permission' => 'Draft Only', 'actions' => [], 'confirm' => self::CONFIRM, 'accept' => '', 'confidence' => 'Normal', 'escalation' => 'Send to Human Review'];
  }

  private static function one($v, array $allowed, string $def): string { $v = is_string($v) ? trim($v) : ''; return in_array($v, $allowed, true) ? $v : $def; }
  private static function many($v, ?array $allowed, int $max = 30, int $len = 80): array {
    if (!is_array($v)) return []; $o = [];
    foreach ($v as $x) { if (!is_string($x)) continue; $x = trim(mb_substr($x, 0, $len)); if ($x === '' || ($allowed !== null && !in_array($x, $allowed, true))) continue; if (!in_array($x, $o, true)) $o[] = $x; if (count($o) >= $max) break; }
    return $o;
  }
  private static function txt($v, int $max): string { return is_string($v) ? trim(mb_substr(str_replace("\r", '', $v), 0, $max)) : ''; }

  /** Clean untrusted form data into the brief shape. */
  public static function clean($in): array {
    $in = is_array($in) ? $in : []; $b = self::blank();
    $b['title'] = self::txt($in['title'] ?? '', 200); $b['type'] = self::one($in['type'] ?? '', self::TYPES, ''); $b['priority'] = self::one($in['priority'] ?? '', self::PRIORITY, 'Normal'); $b['ai_mode'] = self::one($in['ai_mode'] ?? '', self::MODES, 'Assist');
    $b['objective'] = self::txt($in['objective'] ?? '', 4000); $b['context'] = self::txt($in['context'] ?? '', 2000);
    $b['audience'] = self::many($in['audience'] ?? [], self::AUDIENCE); $b['sources'] = self::many($in['sources'] ?? [], self::SOURCES); $b['source_priority'] = self::txt($in['source_priority'] ?? '', 1000);
    $att = []; foreach ((array)($in['attach'] ?? []) as $a) { if (!is_array($a)) continue; $k = (string)($a['kind'] ?? ''); $q = self::txt($a['q'] ?? '', 80); if (in_array($k, self::ATTACH, true) && $q !== '' && count($att) < 6) $att[] = ['kind' => $k, 'q' => $q]; } $b['attach'] = $att;
    $b['must_know'] = self::txt($in['must_know'] ?? '', 4000); $b['must_include'] = self::many($in['must_include'] ?? [], null, 30, 80); $b['must_not'] = self::many($in['must_not'] ?? [], null, 30, 80); $b['rules'] = self::txt($in['rules'] ?? '', 4000);
    $b['output'] = self::one($in['output'] ?? '', self::OUTPUTS, ''); $b['length'] = self::one($in['length'] ?? '', self::LENGTHS, 'Standard'); $b['language'] = self::one($in['language'] ?? '', self::LANGS, 'Bangla'); $b['tone'] = self::one($in['tone'] ?? '', self::TONES, 'Professional');
    $b['reference'] = self::txt($in['reference'] ?? '', 6000);
    $files = []; foreach ((array)($in['files'] ?? []) as $f) { if (!is_array($f) || count($files) >= 4) continue; $n = self::txt($f['name'] ?? '', 120); $t = self::txt($f['text'] ?? '', 12000); if ($n !== '') $files[] = ['name' => $n, 'text' => $t]; } $b['files'] = $files;
    $b['permission'] = self::one($in['permission'] ?? '', self::PERMS, 'Draft Only'); $b['actions'] = self::many($in['actions'] ?? [], self::ACTIONS); $b['confirm'] = array_key_exists('confirm', $in) ? self::many($in['confirm'] ?? [], self::CONFIRM) : self::CONFIRM;
    $b['accept'] = self::txt($in['accept'] ?? '', 3000); $b['confidence'] = self::one($in['confidence'] ?? '', self::CONF, 'Normal'); $b['escalation'] = self::one($in['escalation'] ?? '', self::ESC, 'Send to Human Review');
    return $b;
  }

  /** Completeness 0-100, state, missing required, quality-check list. 8 required fields = 80 points, 4 strongly recommended = 20 points. */
  public static function check(array $b): array {
    $req = ['Brief Title' => $b['title'] !== '', 'Brief Type' => $b['type'] !== '', 'Main Objective' => $b['objective'] !== '', 'Business / Module Context' => $b['context'] !== '',
      'Data Source' => (bool)($b['sources'] || $b['attach'] || $b['files'] || $b['reference'] !== ''), 'Desired Output' => $b['output'] !== '', 'Language' => $b['language'] !== '', 'Permission Level' => $b['permission'] !== ''];
    $rec = ['Must Know' => $b['must_know'] !== '', 'Must Include' => (bool)$b['must_include'], 'Must NOT Include' => (bool)$b['must_not'], 'Acceptance Criteria' => $b['accept'] !== ''];
    $pts = 0; foreach ($req as $ok) if ($ok) $pts += 10; foreach ($rec as $ok) if ($ok) $pts += 5;
    $missing = array_keys(array_filter($req, fn($x) => !$x)); $weak = array_keys(array_filter($rec, fn($x) => !$x));
    $state = $missing ? 'red' : ($pts >= 85 ? 'green' : 'yellow');
    $q = [['Objective present?', $b['objective'] !== ''], ['Context present?', $b['context'] !== ''], ['Output type selected?', $b['output'] !== ''], ['Important facts present?', $b['must_know'] !== ''],
      ['Restrictions defined?', (bool)($b['must_not'] || $b['rules'] !== '')], ['Data source available?', $req['Data Source']], ['Permission level selected?', $b['permission'] !== '']];
    return ['pct' => $pts, 'state' => $state, 'missing' => $missing, 'weak' => $weak, 'quality' => $q];
  }

  /** Short list of the field names that differ between two briefs. */
  public static function diff(array $old, array $new): array { $c = []; foreach ($new as $k => $v) if (($old[$k] ?? null) != $v) $c[] = $k; return $c; }

  private static function mask(?string $p): string { $d = preg_replace('/\D/', '', (string)$p); return strlen($d) >= 3 ? '***' . substr($d, -3) : '***'; }

  /** Facts for ONE attached item. Never includes cost, profit, fraud, staff notes, addresses or full phone numbers. */
  public static function lookup(string $kind, string $q): array {
    $q = trim($q); if ($q === '') return ['error' => 'empty'];
    try {
      if ($kind === 'order') {
        $o = DB::table('orders')->whereIn('invoice', Inv::candidates($q) ?: ['-'])->first(); if (!$o) return ['error' => 'No order with invoice ' . $q];
        $it = DB::table('order_items')->where('order_id', $o->id)->limit(12)->get(['name', 'qty', 'price'])->map(fn($x) => ['name' => $x->name, 'qty' => (int)$x->qty, 'price' => (float)$x->price])->all();
        return ['type' => 'order', 'invoice' => Inv::show($o->invoice), 'customer' => $o->customer_name ?? $o->name ?? null, 'phone' => self::mask($o->phone ?? ''), 'status' => $o->status, 'placed' => (string)$o->created_at, 'district' => $o->district ?? null,
          'courier' => $o->courier ?? null, 'courier_status' => $o->courier_status ?? null, 'tracking' => $o->tracking_code ?? $o->consignment_id ?? null, 'payment_method' => $o->payment_method ?? null, 'payment_status' => $o->payment_status ?? null,
          'paid' => (float)($o->paid_total ?? 0), 'total' => (float)$o->total, 'discount' => (float)($o->discount ?? 0), 'delivery_charge' => (float)($o->shipping ?? 0), 'items' => $it];
      }
      if ($kind === 'customer') {
        $d = preg_replace('/\D/', '', $q); $ph = strlen($d) >= 11 ? substr($d, -11) : $d;
        $rows = DB::table('orders')->where(fn($w) => $w->where('phone', $ph)->orWhere('phone', 'like', '%' . $ph))->orderByDesc('id')->limit(200)->get(['invoice', 'status', 'total', 'created_at', 'customer_name', 'name']);
        if (!$rows->count()) return ['error' => 'No customer orders for ' . $q];
        $by = $rows->groupBy('status')->map->count()->all(); $last = $rows->first();
        return ['type' => 'customer', 'name' => $last->customer_name ?? $last->name ?? null, 'phone' => self::mask($ph), 'orders_total' => $rows->count(), 'orders_by_status' => $by, 'lifetime_value_valid' => (float)$rows->whereNotIn('status', ['Cancel', 'Returned', 'Return Pending'])->sum('total'), 'last_order' => Inv::show($last->invoice) . ' · ' . $last->status . ' · ' . $last->created_at];
      }
      if ($kind === 'product') {
        $p = DB::table('products')->where(fn($w) => $w->where('sku', $q)->orWhere('id', ctype_digit($q) ? (int)$q : 0)->orWhere('name', 'ilike', '%' . $q . '%'))->first(); if (!$p) return ['error' => 'No product ' . $q];
        return ['type' => 'product', 'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku ?? null, 'price' => (float)$p->price, 'compare_price' => (float)($p->compare_price ?? 0), 'stock' => (int)$p->stock, 'sellable' => StockGuard::sellable($p), 'published' => (bool)($p->published ?? true)];
      }
    } catch (\Throwable $e) { return ['error' => 'lookup failed: ' . mb_substr($e->getMessage(), 0, 120)]; }
    return ['error' => 'unknown kind'];
  }

  /** Fresh snapshot of every attached item. */
  public static function snapshot(array $b): array { $o = []; foreach ($b['attach'] as $a) $o[] = ['ask' => $a, 'facts' => self::lookup($a['kind'], $a['q'])]; return $o; }

  /** The final brief as plain text (the preview AND what the AI receives). */
  public static function render(array $b, array $snap = []): string {
    $L = fn($arr) => $arr ? implode(', ', $arr) : '—'; $T = fn($s) => $s !== '' ? $s : '—'; $o = [];
    $o[] = "TASK: " . $T($b['title']) . "  [type: " . $T($b['type']) . ", priority: " . $b['priority'] . ", mode: " . $b['ai_mode'] . "]";
    $o[] = "OBJECTIVE:\n" . $T($b['objective']); $o[] = "BUSINESS / MODULE CONTEXT:\n" . $T($b['context']); $o[] = "AUDIENCE: " . $L($b['audience']);
    $o[] = "DATA SOURCES (authoritative): " . $L($b['sources']) . ($b['source_priority'] !== '' ? "\nSOURCE PRIORITY (a higher source is never overridden by a lower one):\n" . $b['source_priority'] : '');
    if ($snap) $o[] = "ATTACHED FACTS (system snapshot, authoritative):\n" . json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $o[] = "MUST KNOW:\n" . $T($b['must_know']); $o[] = "MUST INCLUDE: " . $L($b['must_include']); $o[] = "MUST NOT INCLUDE: " . $L($b['must_not']); $o[] = "BUSINESS RULES:\n" . $T($b['rules']);
    $o[] = "OUTPUT: " . $T($b['output']) . " · length: " . $b['length'] . " · language: " . $b['language'] . " · tone: " . $b['tone'];
    if ($b['reference'] !== '' || $b['files']) { $r = $b['reference']; foreach ($b['files'] as $f) $r .= "\n[file: " . $f['name'] . "]\n" . $f['text']; $o[] = "REFERENCE / EXAMPLE (inspiration only, do not copy blindly):\n" . trim($r); }
    $o[] = "PERMISSION: " . $b['permission'] . " · allowed actions: " . $L($b['actions']) . " · needs human confirmation: " . $L($b['confirm']);
    $o[] = "ACCEPTANCE CRITERIA:\n" . $T($b['accept']); $o[] = "MINIMUM CONFIDENCE: " . $b['confidence'] . " · IF NOT CONFIDENT: " . $b['escalation'];
    return implode("\n\n", $o);
  }

  private static function key(): ?string { $k = Sms::secret('ai_key'); return $k ?: null; }
  private static function model(): string { return Sms::secret('ai_brief_model') ?: 'claude-sonnet-5-5'; }

  /** One Claude call. Returns [text|null, tokensIn, tokensOut, error|null, model]. */
  private static function ask(string $system, string $user, int $max = 3500): array {
    $key = self::key(); if (!$key) return [null, 0, 0, 'Claude API key is not set (Site settings > AI Chat)', self::model()];
    @set_time_limit(150);
    try {
      $res = Http::withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])->timeout(110)->post('https://api.anthropic.com/v1/messages', ['model' => self::model(), 'max_tokens' => $max, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $user]]]);
      if (!$res->ok()) return [null, 0, 0, 'HTTP ' . $res->status() . ' ' . mb_substr((string)($res->json('error.message') ?? ''), 0, 160), self::model()];
      $t = trim(collect($res->json('content') ?? [])->where('type', 'text')->pluck('text')->implode("\n"));
      return [$t, (int)$res->json('usage.input_tokens'), (int)$res->json('usage.output_tokens'), $t === '' ? 'empty reply' : null, self::model()];
    } catch (\Throwable $e) { return [null, 0, 0, 'network: ' . mb_substr($e->getMessage(), 0, 140), self::model()]; }
  }
  private static function json(?string $t): ?array {
    if (!$t) return null; $t = trim(preg_replace('/^```(?:json)?|```$/m', '', $t)); $a = json_decode($t, true); if (is_array($a)) return $a;
    $i = strpos($t, '{'); $j = strrpos($t, '}'); if ($i !== false && $j > $i) { $a = json_decode(substr($t, $i, $j - $i + 1), true); if (is_array($a)) return $a; } return null;
  }

  const SYS_RUN = "You are the AI task assistant inside the DutyFreeBD admin panel (a Bangladeshi e-commerce business). You receive a structured BRIEF written by staff.\n"
    . "Rules you always follow:\n- The brief and the ATTACHED FACTS are the only source of business facts. Never invent or guess prices, stock, customer facts, payment status, courier status, discount eligibility or policy. If a needed fact is missing, list it under \"missing\" and set status to needs_review.\n"
    . "- Obey MUST INCLUDE, MUST NOT INCLUDE, BUSINESS RULES, language, tone, length and the ACCEPTANCE CRITERIA exactly.\n- You can only DRAFT or SUGGEST. You cannot send a message, change an order, a payment, a price or any data; if the permission asks for an action, write the exact proposed steps for a human to confirm, and say nothing was executed.\n"
    . "- Never reveal internal cost, profit, fraud score, staff notes or API information in anything meant for a customer.\n- The REFERENCE is inspiration, not text to copy.\n- If you are less confident than the MINIMUM CONFIDENCE, set status to needs_review and explain what is uncertain instead of guessing.\n"
    . "Reply with ONLY one JSON object: {\"status\":\"ok\" or \"needs_review\",\"confidence\":\"normal\"|\"high\"|\"very_high\",\"missing\":[strings],\"checks\":[{\"criterion\":string,\"met\":true|false}],\"output\":string}. \"output\" is the final deliverable in the requested language, ready to use. No text outside the JSON.";

  /** Run the brief. Returns the stored result array. */
  public static function run(array $b): array {
    $snap = self::snapshot($b); $err = null;
    foreach ($snap as $s) if (isset($s['facts']['error'])) $err = ($err ? $err . '; ' : '') . $s['facts']['error'];
    [$t, $in, $out, $e, $model] = self::ask(self::SYS_RUN, "BRIEF:\n" . self::render($b, $snap), 3500);
    if ($t === null) return ['ok' => false, 'error' => $e, 'snapshot' => $snap, 'in' => 0, 'out' => 0, 'model' => $model];
    $j = self::json($t);
    if (!$j || !isset($j['output'])) return ['ok' => true, 'status' => 'needs_review', 'confidence' => 'normal', 'missing' => ['The AI reply was not in the expected format; showing it as written'], 'checks' => [], 'output' => $t, 'snapshot' => $snap, 'in' => $in, 'out' => $out, 'model' => $model, 'lookup_errors' => $err];
    $miss = array_values(array_filter(array_map('strval', (array)($j['missing'] ?? [])))); if ($err) $miss[] = $err;
    $st = ($j['status'] ?? 'ok') === 'ok' && !$miss ? 'ok' : 'needs_review';
    return ['ok' => true, 'status' => $st, 'confidence' => (string)($j['confidence'] ?? 'normal'), 'missing' => $miss, 'checks' => array_values(array_filter((array)($j['checks'] ?? []), 'is_array')), 'output' => (string)$j['output'], 'snapshot' => $snap, 'in' => $in, 'out' => $out, 'model' => $model];
  }

  const IMPROVABLE = ['title', 'objective', 'context', 'must_know', 'must_include', 'must_not', 'accept'];
  const SYS_IMPROVE = "You tidy a staff member's rough AI-task brief for a Bangladeshi e-commerce admin panel. Reorganize and clarify the text into the fields below. STRICT: do NOT change any fact, number, name, date, price, policy or promise, and do NOT add facts that are not there. Keep the staff's language (Bangla stays Bangla). If a field is already clear, leave it out.\nFields: title (string), objective (string), context (string), must_know (string, one fact per line), must_include (array of short strings), must_not (array of short strings), accept (string, one criterion per line).\nReply with ONLY JSON: {\"changes\":{field:newValue,...},\"note\":\"one short sentence\"}.";

  /** Suggested rewrites (nothing is applied until staff accept). */
  public static function improve(array $b): array {
    $send = array_intersect_key($b, array_flip(array_merge(self::IMPROVABLE, ['type', 'output', 'language'])));
    [$t, $in, $out, $e] = self::ask(self::SYS_IMPROVE, json_encode($send, JSON_UNESCAPED_UNICODE), 1800);
    if ($t === null) return ['ok' => false, 'error' => $e];
    $j = self::json($t); if (!$j || !isset($j['changes']) || !is_array($j['changes'])) return ['ok' => false, 'error' => 'The AI reply could not be read'];
    $c = []; foreach ($j['changes'] as $k => $v) { if (!in_array($k, self::IMPROVABLE, true)) continue; $new = self::clean([$k => $v] + $b)[$k]; if ($new != $b[$k] && $new !== '' && $new !== []) $c[$k] = ['old' => $b[$k], 'new' => $new]; }
    return ['ok' => true, 'changes' => $c, 'note' => (string)($j['note'] ?? ''), 'in' => $in, 'out' => $out];
  }

  public static function runsToday(int $uid): int { try { return (int)\Illuminate\Support\Facades\Cache::get('aitb.runs.' . $uid . '.' . now('Asia/Dhaka')->format('Ymd'), 0); } catch (\Throwable $e) { return 0; } }
  public static function countRun(int $uid): void { try { $k = 'aitb.runs.' . $uid . '.' . now('Asia/Dhaka')->format('Ymd'); \Illuminate\Support\Facades\Cache::add($k, 0, 90000); \Illuminate\Support\Facades\Cache::increment($k); } catch (\Throwable $e) {} }
}
