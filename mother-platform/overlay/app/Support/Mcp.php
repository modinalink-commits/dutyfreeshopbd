<?php
namespace App\Support;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Mother Commerce MCP server, phase 1: READ tools only. No raw SQL, no writes. Every call is authenticated (rotatable hashed token), rate limited, logged and can be switched off (setting mcp_enabled). */
class Mcp {
  const PROTOCOL = '2025-03-26';
  const LIMIT = 60;   // read calls per token per minute
  public static function ready(): bool { try { return Schema::hasTable('mcp_tokens') && Schema::hasTable('mcp_audit'); } catch (\Throwable $e) { return false; } }
  public static function enabled(): bool { return Procure::set('mcp_enabled', '0') === '1'; }
  public static function newToken(string $name, ?string $expires, ?int $by): string {
    $raw = 'mcp_'.bin2hex(random_bytes(24));
    DB::table('mcp_tokens')->insert(['name' => mb_substr($name, 0, 120), 'token_hash' => hash('sha256', $raw), 'token_hint' => substr($raw, 0, 6).'…'.substr($raw, -4), 'scope' => 'read', 'expires_at' => $expires ?: null, 'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);
    return $raw;
  }
  private static function money($v) { return round((float)$v, 2); }
  private static function lim($a, int $d = 20, int $max = 50): int { return max(1, min($max, (int)($a['limit'] ?? $d))); }
  private static function like(string $q): string { return '%'.str_replace(['%', '_'], ['\%', '\_'], trim($q)).'%'; }

  /** name => [description, inputSchema properties, handler] — all READ. */
  public static function tools(): array {
    $s = fn(array $p, array $req = []) => ['type' => 'object', 'properties' => (object)$p, 'required' => $req];
    $str = fn($d) => ['type' => 'string', 'description' => $d]; $int = fn($d) => ['type' => 'integer', 'description' => $d];
    return [
      'search_orders' => ['Search orders by invoice, phone, customer name, status, courier, approval source (AI/MANUAL/SYSTEM/API) and date range. Newest first.',
        $s(['q' => $str('invoice, phone or customer name'), 'status' => $str('exact status, e.g. New, Approved, Delivered'), 'courier' => $str('Pathao, Steadfast, RedX…'), 'approval_source' => $str('AI, MANUAL, SYSTEM or API'), 'date_from' => $str('YYYY-MM-DD'), 'date_to' => $str('YYYY-MM-DD'), 'limit' => $int('max 50')]),
        function ($a) {
          $q = DB::table('orders');
          if (!empty($a['q'])) { $l = self::like((string)$a['q']); $q->where(fn($x) => $x->where('invoice', 'ilike', $l)->orWhere('phone', 'like', $l)->orWhere('customer_name', 'ilike', $l)); }
          foreach (['status', 'courier', 'approval_source'] as $f) if (!empty($a[$f])) $q->where($f, (string)$a[$f]);
          if (!empty($a['date_from'])) $q->whereRaw("(created_at at time zone 'Asia/Dhaka')::date >= ?", [$a['date_from']]); if (!empty($a['date_to'])) $q->whereRaw("(created_at at time zone 'Asia/Dhaka')::date <= ?", [$a['date_to']]);
          $rows = $q->orderByDesc('id')->limit(self::lim($a))->get(['id', 'invoice', 'customer_name', 'phone', 'district', 'status', 'total', 'payment_status', 'courier', 'consignment_id', 'courier_stage', 'approval_source', 'created_at']);
          return ['count' => $rows->count(), 'orders' => $rows];
        }],
      'get_order' => ['Full order by invoice number: customer, address, items with price/cost, totals, payment, courier, approval source and status timeline.',
        $s(['invoice' => $str('invoice number, e.g. 102619 or INV-102619')], ['invoice']),
        function ($a) {
          $o = DB::table('orders')->whereIn('invoice', Inv::candidates((string)($a['invoice'] ?? '')))->first(); if (!$o) throw new \RuntimeException('ORDER_NOT_FOUND');
          $items = DB::table('order_items')->where('order_id', $o->id)->get(['name', 'qty', 'price', 'cogs']);
          $tl = DB::table('order_status_logs')->where('order_id', $o->id)->orderBy('id')->get(['created_at', 'user_name', 'from_status', 'to_status', 'note']);
          $keep = ['id', 'invoice', 'customer_name', 'phone', 'address', 'district', 'source', 'courier', 'consignment_id', 'courier_stage', 'shipping', 'total', 'status', 'payment_status', 'paid_total', 'approval_source', 'approved_at', 'approval_reference', 'note', 'created_at'];
          $row = []; foreach ($keep as $k) if (property_exists($o, $k)) $row[$k] = $o->$k;
          $cogs = $items->sum(fn($i) => (float)$i->cogs); return ['order' => $row, 'items' => $items, 'items_cost_recorded' => self::money($cogs), 'timeline' => $tl];
        }],
      'search_products' => ['Search products by name or SKU; filter by published and by stock.',
        $s(['q' => $str('name or SKU'), 'published' => ['type' => 'boolean'], 'max_stock' => $int('only products with stock <= this'), 'limit' => $int('max 50')]),
        function ($a) {
          $q = DB::table('products'); if (!empty($a['q'])) { $l = self::like((string)$a['q']); $q->where(fn($x) => $x->where('name', 'ilike', $l)->orWhere('sku', 'ilike', $l)); }
          if (isset($a['published'])) $q->where('published', (bool)$a['published']); if (isset($a['max_stock'])) $q->where('stock', '<=', (int)$a['max_stock']);
          return ['products' => $q->orderBy('name')->limit(self::lim($a))->get(['id', 'name', 'sku', 'price', 'cost', 'stock', 'published'])];
        }],
      'get_product' => ['One product by id or SKU with price, cost, margin on cost, minimum safe price (cost + protected profit %), stock and publish status.',
        $s(['id' => $int('product id'), 'sku' => $str('SKU')]),
        function ($a) {
          $p = !empty($a['id']) ? DB::table('products')->find((int)$a['id']) : (!empty($a['sku']) ? DB::table('products')->where('sku', (string)$a['sku'])->first() : null); if (!$p) throw new \RuntimeException('PRODUCT_NOT_FOUND');
          $pct = (float)Procure::set('minimum_protected_profit_percent', '10'); $cost = (float)$p->cost;
          return ['product' => $p, 'minimum_protected_profit_percent' => $pct, 'minimum_safe_price' => $cost > 0 ? self::money($cost * (1 + $pct / 100)) : null, 'margin_percent_on_cost' => $cost > 0 ? self::money(((float)$p->price - $cost) / $cost * 100) : null, 'note' => 'Cost shown is the product cost field; FIFO batch cost is used for real COGS.'];
        }],
      'get_inventory' => ['Products whose stock is below a threshold (default 100, the supplier reorder rule), published or not.',
        $s(['below' => $int('stock threshold, default 100'), 'limit' => $int('max 50')]),
        function ($a) { $b = (int)($a['below'] ?? 100); $q = DB::table('products')->where('stock', '<', $b); return ['threshold' => $b, 'total_matching' => (clone $q)->count(), 'products' => $q->orderBy('stock')->limit(self::lim($a, 30))->get(['id', 'name', 'sku', 'stock', 'published', 'price', 'cost'])]; }],
      'search_customers' => ['Search customers by name or phone.', $s(['q' => $str('name or phone'), 'limit' => $int('max 50')], ['q']),
        function ($a) { $l = self::like((string)($a['q'] ?? '')); return ['customers' => DB::table('customers')->where(fn($x) => $x->where('name', 'ilike', $l)->orWhere('phone', 'like', $l))->orderByDesc('id')->limit(self::lim($a))->get(['id', 'name', 'phone', 'membership', 'blocked'])]; }],
      'get_customer' => ['Customer by phone: order counts per status and the received (Delivered + Partial Delivered) count and amount — the basis for benefits.', $s(['phone' => $str('phone number')], ['phone']),
        function ($a) {
          $ph = (string)($a['phone'] ?? ''); $c = DB::table('customers')->where('phone', $ph)->first(); $by = DB::table('orders')->where('phone', $ph)->selectRaw('status, count(*) n, round(sum(total)) amt')->groupBy('status')->get();
          if (!$c && $by->isEmpty()) throw new \RuntimeException('CUSTOMER_NOT_FOUND');
          $rec = $by->whereIn('status', ['Delivered', 'Partial Delivered']); $all = (int)$by->sum('n');
          return ['customer' => $c, 'orders_by_status' => $by, 'total_orders' => $all, 'received_orders' => (int)$rec->sum('n'), 'received_amount' => self::money($rec->sum('amt')), 'success_rate_percent' => $all ? self::money($rec->sum('n') / $all * 100) : 0];
        }],
      'get_expenses' => ['Ledger money-out entries (Income & Expenses) in a date range, with purpose and account.', $s(['date_from' => $str('YYYY-MM-DD'), 'date_to' => $str('YYYY-MM-DD'), 'limit' => $int('max 50')]),
        function ($a) {
          $q = DB::table('account_entries as e')->leftJoin('expense_purposes as p', 'p.id', '=', 'e.purpose_id')->leftJoin('money_accounts as m', 'm.id', '=', 'e.account_id')->where('e.direction', 'expense');
          if (!empty($a['date_from'])) $q->where('e.entry_date', '>=', $a['date_from']); if (!empty($a['date_to'])) $q->where('e.entry_date', '<=', $a['date_to']);
          return ['total' => self::money((clone $q)->sum('e.amount')), 'entries' => $q->orderByDesc('e.entry_date')->orderByDesc('e.id')->limit(self::lim($a))->get(['e.reference', 'e.entry_date', 'e.amount', 'p.name as purpose', 'm.name as account', 'e.comment'])];
        }],
      'get_recurring_expenses' => ['Recurring fixed expense masters and the instances due or unpaid.', $s([]),
        function ($a) {
          if (!RecurringExpense::ready()) return ['masters' => [], 'instances' => []];
          return ['masters' => DB::table('recurring_expenses')->orderBy('name')->get(['id', 'name', 'category', 'expense_type', 'amount', 'frequency', 'pay_day', 'start_date', 'end_date', 'status', 'auto_post', 'auto_deduct']),
            'unpaid_or_due' => DB::table('recurring_expense_instances as i')->join('recurring_expenses as m', 'm.id', '=', 'i.recurring_id')->where('i.payment_status', '!=', 'paid')->whereNotIn('i.status', ['skipped', 'cancelled'])->orderBy('i.due_date')->limit(50)->get(['m.name', 'i.due_date', 'i.amount', 'i.status', 'i.payment_status', 'i.note'])];
        }],
      'get_account_balance' => ['Balance of every money account (opening + income − expense).', $s([]),
        function ($a) { return ['accounts' => DB::table('money_accounts')->where('active', true)->orderBy('name')->get()->map(fn($x) => ['name' => $x->name, 'type' => $x->type, 'balance' => self::money(RecurringExpense::balance((int)$x->id))])]; }],
      'get_finance_summary' => ['Ledger income and money-out for a date range (default: current month, Dhaka). Money-out includes stock purchases and transfers, so it is not an operating-expense or net-profit figure.', $s(['date_from' => $str('YYYY-MM-DD'), 'date_to' => $str('YYYY-MM-DD')]),
        function ($a) {
          $f = $a['date_from'] ?? now('Asia/Dhaka')->startOfMonth()->toDateString(); $t = $a['date_to'] ?? now('Asia/Dhaka')->toDateString();
          $r = DB::table('account_entries')->whereBetween('entry_date', [$f, $t])->selectRaw("coalesce(sum(amount) filter (where direction='income'),0) i, coalesce(sum(amount) filter (where direction='expense'),0) e")->first();
          return ['from' => $f, 'to' => $t, 'ledger_income' => self::money($r->i), 'ledger_money_out' => self::money($r->e), 'net_cash_movement' => self::money($r->i - $r->e), 'caveat' => 'Money-out includes purchases/transfers; do not read it as operating expense.'];
        }],
      'get_courier_status' => ['Courier, consignment and delivery stage of an order by invoice.', $s(['invoice' => $str('invoice number')], ['invoice']),
        function ($a) { $o = DB::table('orders')->whereIn('invoice', Inv::candidates((string)($a['invoice'] ?? '')))->first(['invoice', 'status', 'courier', 'consignment_id', 'courier_stage', 'courier_cod', 'collected_amount', 'courier_delivered_at', 'courier_location']); if (!$o) throw new \RuntimeException('ORDER_NOT_FOUND'); return ['courier' => $o]; }],
      'get_dashboard_summary' => ['Today (Dhaka): orders placed by status and total ordered value; plus unpaid recurring expenses count.', $s([]),
        function ($a) {
          $d = now('Asia/Dhaka')->toDateString(); $by = DB::table('orders')->whereRaw("(created_at at time zone 'Asia/Dhaka')::date = ?", [$d])->selectRaw('status, count(*) n, round(sum(total)) amt')->groupBy('status')->get();
          return ['date' => $d, 'orders_today' => (int)$by->sum('n'), 'ordered_value_today' => self::money($by->sum('amt')), 'by_status' => $by];
        }],
    ];
  }

  private static function out(Request $r, $id, array $res) { return response()->json(['jsonrpc' => '2.0', 'id' => $id] + $res); }
  private static function err($id, int $code, string $msg, int $http = 200) { return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $msg]], $http); }
  private static function audit(string $rid, $tok, ?string $tool, $in, string $status, ?string $err, string $ip, int $ms): void {
    try { DB::table('mcp_audit')->insert(['request_id' => $rid, 'token_id' => $tok->id ?? null, 'client' => $tok->name ?? null, 'tool' => $tool, 'input' => $in !== null ? mb_substr(json_encode($in, JSON_UNESCAPED_UNICODE), 0, 600) : null, 'status' => $status, 'error' => $err ? mb_substr($err, 0, 240) : null, 'ip' => $ip, 'ms' => $ms]); } catch (\Throwable $e) {}
  }
  public static function health() {
    $db = true; try { DB::select('select 1'); } catch (\Throwable $e) { $db = false; }
    return response()->json(['mcp' => self::enabled() ? 'enabled' : 'disabled', 'laravel' => 'ok', 'database' => $db ? 'ok' : 'down', 'queue' => Cache::has('worker.beat') ? 'ok' : 'unknown', 'time' => now('Asia/Dhaka')->toIso8601String()], $db ? 200 : 503);
  }
  public static function handle(Request $r) {
    $t0 = microtime(true); $rid = 'mcp-'.bin2hex(random_bytes(6)); $ip = (string)$r->ip();
    if (!self::ready() || !self::enabled()) return self::err(null, -32000, 'MCP is disabled', 503);
    $raw = (string)preg_replace('/^Bearer\s+/i', '', (string)$r->header('Authorization')); $tok = $raw !== '' ? DB::table('mcp_tokens')->where('token_hash', hash('sha256', $raw))->first() : null;
    if (!$tok || $tok->revoked_at || ($tok->expires_at && now()->gt($tok->expires_at))) { self::audit($rid, null, null, null, 'auth_failed', 'bad or expired token', $ip, 0); return self::err(null, -32001, 'Unauthorized', 401); }
    $k = 'mcp.rl.'.$tok->id.'.'.now()->format('YmdHi'); Cache::add($k, 0, 90); if (Cache::increment($k) > self::LIMIT) { self::audit($rid, $tok, null, null, 'rate_limited', null, $ip, 0); return self::err(null, -32002, 'RATE_LIMITED', 429); }
    DB::table('mcp_tokens')->where('id', $tok->id)->update(['last_used_at' => now()]);
    $b = json_decode((string)$r->getContent(), true); if (!is_array($b)) return self::err(null, -32700, 'Parse error');
    $id = $b['id'] ?? null; $m = (string)($b['method'] ?? ''); $p = is_array($b['params'] ?? null) ? $b['params'] : [];
    if (str_starts_with($m, 'notifications/')) return response('', 202);
    if ($m === 'initialize') return self::out($r, $id, ['result' => ['protocolVersion' => self::PROTOCOL, 'capabilities' => ['tools' => (object)['listChanged' => false]], 'serverInfo' => ['name' => 'Mother Commerce', 'version' => '1.0-read']]]);
    if ($m === 'ping') return self::out($r, $id, ['result' => (object)[]]);
    if ($m === 'tools/list') { $o = []; foreach (self::tools() as $n => [$d, $sch]) $o[] = ['name' => $n, 'description' => '[READ] '.$d, 'inputSchema' => $sch]; return self::out($r, $id, ['result' => ['tools' => $o]]); }
    if ($m === 'tools/call') {
      $n = (string)($p['name'] ?? ''); $a = is_array($p['arguments'] ?? null) ? $p['arguments'] : []; $T = self::tools();
      if (!isset($T[$n])) { self::audit($rid, $tok, $n, $a, 'unknown_tool', null, $ip, 0); return self::err($id, -32602, 'Unknown tool: '.$n); }
      try {
        $data = $T[$n][2]($a); $env = ['success' => true, 'message' => 'ok', 'data' => $data, 'warnings' => [], 'requires_confirmation' => false, 'audit_reference' => $rid];
        self::audit($rid, $tok, $n, $a, 'ok', null, $ip, (int)((microtime(true) - $t0) * 1000));
        return self::out($r, $id, ['result' => ['content' => [['type' => 'text', 'text' => json_encode($env, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)]], 'isError' => false]]);
      } catch (\Throwable $e) {
        $code = $e instanceof \RuntimeException && preg_match('/^[A-Z_]+$/', $e->getMessage()) ? $e->getMessage() : 'SERVER_ERROR'; if ($code === 'SERVER_ERROR') report($e);
        self::audit($rid, $tok, $n, $a, 'error', $code, $ip, (int)((microtime(true) - $t0) * 1000));
        return self::out($r, $id, ['result' => ['content' => [['type' => 'text', 'text' => json_encode(['success' => false, 'error' => ['code' => $code, 'message' => $code, 'details' => null, 'retryable' => $code === 'SERVER_ERROR'], 'audit_reference' => $rid])]], 'isError' => true]]);
      }
    }
    return self::err($id, -32601, 'Method not found: '.$m);
  }
}
