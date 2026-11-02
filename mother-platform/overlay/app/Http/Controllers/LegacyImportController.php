<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Temporary token-protected intake for the old DutyFreeBD customer data (staging table only; nothing touches live customers). */
class LegacyImportController extends Controller
{
  private function ok(Request $r): bool { $t = (string)DB::table('settings')->where('key', 'legacy_import_token')->value('value'); return $t !== '' && hash_equals($t, (string)$r->query('k')); }
  public static function phone(?string $p): ?string { $s = preg_replace('/\D/', '', (string)$p); if (str_starts_with($s, '880')) $s = substr($s, 2); return preg_match('/^01[3-9]\d{8}$/', $s) ? $s : null; }
  public function ingest(Request $r) {
    if (!$this->ok($r)) abort(404);
    $rows = json_decode((string)$r->input('rows'), true); if (!is_array($rows)) return response()->json(['ok' => false]);
    $n = 0; $bad = 0;
    foreach ($rows as $x) {
      $p = self::phone($x['phone'] ?? ''); if (!$p) { $bad++; continue; }
      $amt = (float)preg_replace('/[^\d.]/', '', (string)($x['amount'] ?? '0'));
      DB::table('legacy_customers')->updateOrInsert(['phone' => $p], [
        'name' => mb_substr(trim((string)($x['name'] ?? '')), 0, 160) ?: null, 'email' => mb_substr(trim((string)($x['email'] ?? '')), 0, 160) ?: null,
        'address' => trim((string)($x['address'] ?? '')) ?: null, 'tier' => mb_substr(trim((string)($x['tier'] ?? '')), 0, 40) ?: null, 'ip_status' => mb_substr(trim((string)($x['ip'] ?? '')), 0, 40) ?: null,
        'orders_count' => (int)preg_replace('/\D/', '', (string)($x['orders'] ?? '0')), 'orders_amount' => $amt, 'last_order' => mb_substr(trim((string)($x['last'] ?? '')), 0, 40) ?: null,
        'comment' => trim((string)($x['comment'] ?? '')) ?: null, 'old_id' => (int)($x['id'] ?? 0) ?: null, 'old_status' => isset($x['status']) ? (int)$x['status'] : null, 'customer_type' => isset($x['ctype']) ? (int)$x['ctype'] : null, 'is_blocked' => isset($x['blocked']) ? (int)$x['blocked'] : null, 'city_id' => (int)($x['city'] ?? 0) ?: null, 'sub_city_id' => (int)($x['sub'] ?? 0) ?: null, 'old_created_at' => mb_substr((string)($x['cat'] ?? ''), 0, 30) ?: null, 'old_updated_at' => mb_substr((string)($x['uat'] ?? ''), 0, 30) ?: null, 'last_order_date' => mb_substr((string)($x['lod'] ?? ''), 0, 30) ?: null, 'coupon_income' => (float)($x['coupon'] ?? 0), 'username' => mb_substr((string)($x['user'] ?? ''), 0, 120) ?: null, 'src_page' => (int)($x['page'] ?? 0), 'updated_at' => now(), 'created_at' => now()]);
      $n++;
    }
    return response()->json(['ok' => true, 'saved' => $n, 'bad' => $bad]);
  }

  private const TIER = "case when l.orders_amount >= 200000 then 'Diamond / V VIP' when l.orders_amount >= 150000 then 'Platinum / VIP' when l.orders_amount >= 50000 then 'Gold / Premium' when l.orders_amount >= 5000 then 'Silver / Standard' else null end";
  /** Real customers only: valid phone (guaranteed by staging) and not an obvious test entry with no orders. */
  private const REAL = "not (l.orders_count = 0 and (l.name ilike 'test%' or coalesce(l.name,'') = ''))";
  /** Bogus values in the old system: more than 1 lakh per order on average (fake/test orders). Zero the amount, keep the count. */
  private function clean(): void { DB::statement("update legacy_customers set orders_amount = 0 where orders_amount > 100000 * greatest(orders_count,1)"); }
  public function review() {
    $this->clean();
    $m = "exists (select 1 from customers c where right(c.phone,11) = l.phone)";
    $q = fn($w) => (int)DB::selectOne("select count(*) n from legacy_customers l where $w")->n;
    $d = ['staged' => $q('true'), 'real' => $q(self::REAL), 'test' => $q('not ('.self::REAL.')'), 'matched' => $q(self::REAL." and $m"), 'new' => $q(self::REAL." and not $m"), 'with_orders' => $q('l.orders_count > 0'),
      'sum_orders' => (int)DB::selectOne('select coalesce(sum(orders_count),0) n from legacy_customers')->n, 'sum_amount' => (float)DB::selectOne('select coalesce(sum(orders_amount),0) n from legacy_customers')->n,
      'email' => $q("coalesce(l.email,'') <> ''"), 'done' => $q("l.action is not null"), 'now' => (int)DB::table('customers')->count()];
    $d['top'] = DB::select("select phone, name, orders_count, orders_amount from legacy_customers order by orders_amount desc limit 12");
    $d['huge'] = $q('l.orders_amount > 5000000');
    $d['tiers'] = DB::select("select coalesce(".self::TIER.", 'Regular') t, count(*) n from legacy_customers l where ".self::REAL." group by 1 order by 2 desc");
    return view('legacy_import', ['d' => $d]);
  }
  public function apply(Request $r) {
    abort_unless($r->input('confirm') === 'IMPORT', 422);
    $this->clean();
    $m = "exists (select 1 from customers c where right(c.phone,11) = l.phone)";
    DB::transaction(function () use ($m) {
      DB::statement("update legacy_customers l set action = case when $m then 'update' else 'insert' end where action is null and ".self::REAL);
      DB::statement("update customers c set legacy_orders = case when coalesce(c.legacy_orders,0) = 0 then l.orders_count else c.legacy_orders end, legacy_spent = case when coalesce(c.legacy_spent,0) = 0 then l.orders_amount else c.legacy_spent end,
        email = coalesce(nullif(c.email,''), l.email), address = coalesce(nullif(c.address,''), l.address), note = coalesce(nullif(c.note,''), l.comment), membership = coalesce(nullif(c.membership,''), ".self::TIER."), updated_at = now()
        from legacy_customers l where l.action = 'update' and right(c.phone,11) = l.phone");
      DB::statement("insert into customers (name, phone, email, address, note, username, membership, legacy_orders, legacy_spent, created_at, updated_at)
        select coalesce(nullif(l.name,''), 'Customer ' || l.phone), l.phone, l.email, l.address, l.comment, l.username, ".self::TIER.", l.orders_count, l.orders_amount,
        coalesce(case when l.old_created_at ~ '^\\d{4}-\\d{2}-\\d{2}' then substr(l.old_created_at,1,19)::timestamp end, now()), now() from legacy_customers l where l.action = 'insert' and l.new_customer_id is null");
      DB::statement("update legacy_customers l set new_customer_id = c.id, merged = true from customers c where l.action = 'insert' and l.new_customer_id is null and right(c.phone,11) = l.phone");
      DB::statement("update legacy_customers set merged = true where action = 'update'");
    });
    return redirect('/admin/legacy-import')->with('ok', 'Import finished.');
  }
  public function stats(Request $r) {
    if (!$this->ok($r)) abort(404);
    return response()->json(['rows' => DB::table('legacy_customers')->count(), 'pages' => DB::table('legacy_customers')->distinct()->count('src_page'), 'maxpage' => DB::table('legacy_customers')->max('src_page')]);
  }
  /** Token webhook: set bcrypt hashes from the old site where the new account has no password yet. d = JSON [[phone,hash],...] */
  public function passwords(Request $r) {
    if (!$this->ok($r)) abort(404);
    $rows = json_decode((string)$r->input('d'), true);
    if (!is_array($rows)) return response()->json(['ok'=>false], 422);
    $set = 0; $seen = 0;
    foreach (array_chunk($rows, 400) as $ch) {
      $v = []; $b = [];
      foreach ($ch as $x) {
        $ph = self::phone($x[0] ?? ''); $h = (string)($x[1] ?? '');
        if (!$ph || !preg_match('/^\$2[aby]\$\d\d\$[.\/A-Za-z0-9]{53}$/', $h)) continue;
        $v[] = '(?,?)'; $b[] = $ph; $b[] = $h;
      }
      if (!$v) continue;
      $seen += count($v);
      $set += DB::update("update customers c set password = v.h, registered_at = coalesce(c.registered_at, now()), updated_at = now() from (values ".implode(',', $v).") as v(p,h) where right(c.phone,11) = v.p and c.password is null", $b);
    }
    return response()->json(['ok'=>true, 'seen'=>$seen, 'set'=>$set, 'with_pw'=>DB::table('customers')->whereNotNull('password')->count()]);
  }
  /** Token webhook: import old-site product reviews (idempotent). d = JSON [{pid,pcode,user,st,txt,stars,at},...] */
  public function reviews(Request $r) {
    if (!$this->ok($r)) abort(404);
    $rows = json_decode((string)$r->input('d'), true);
    if (!is_array($rows)) return response()->json(['ok'=>false], 422);
    $in = 0; $skip = 0; $nomatch = 0;
    foreach ($rows as $x) {
      $pid = DB::table('products')->where('ext_id', (int)($x['pid'] ?? 0) + 999)->value('id') ?: (($x['pcode'] ?? '') !== '' ? DB::table('products')->where('sku', (string)$x['pcode'])->value('id') : null);
      if (!$pid) { $nomatch++; continue; }
      $name = mb_substr(trim((string)($x['user'] ?? '')) ?: 'Customer', 0, 120); $body = trim((string)($x['txt'] ?? '')); $at = $x['at'] ?? now();
      if (DB::table('reviews')->where('product_id', $pid)->where('name', $name)->where('body', $body)->where('created_at', $at)->exists()) { $skip++; continue; }
      DB::table('reviews')->insert(['product_id'=>$pid, 'name'=>$name, 'rating'=>max(1, min(5, (int)($x['stars'] ?? 5))), 'body'=>$body, 'verified_buyer'=>false, 'status'=>((int)($x['st'] ?? 0)) === 1 ? 'approved' : 'hidden', 'created_at'=>$at, 'updated_at'=>$at]);
      $in++;
    }
    return response()->json(['ok'=>true, 'in'=>$in, 'skip'=>$skip, 'nomatch'=>$nomatch, 'total'=>DB::table('reviews')->count()]);
  }
  /** Token webhook: old-site suppliers and purchases (with items and the old paid amount as a payment). Idempotent by legacy ids. Stock is NOT changed. */
  public function procurement(Request $r) {
    if (!$this->ok($r)) abort(404);
    $d = json_decode((string)$r->input('d'), true);
    if (!is_array($d)) return response()->json(['ok'=>false], 422);
    $sup = 0; $pur = 0; $skip = 0; $items = 0; $nop = 0;
    foreach (($d['suppliers'] ?? []) as $x) {
      $lid = (int)($x['id'] ?? 0); if (!$lid) continue;
      $name = trim((string)($x['name'] ?? '')) ?: trim((string)($x['company'] ?? '')) ?: 'Supplier '.$lid;
      $row = ['name'=>mb_substr($name,0,190), 'business_name'=>mb_substr((string)($x['company'] ?? ''),0,200) ?: null, 'phone'=>mb_substr((string)($x['phone'] ?? ''),0,40) ?: null, 'address'=>($x['address'] ?? null) ?: null, 'active'=>(int)($x['st'] ?? 1) === 1, 'updated_at'=>now()];
      $ex = DB::table('suppliers')->where('legacy_supplier_id', $lid)->first();
      if ($ex) { DB::table('suppliers')->where('id', $ex->id)->update($row); continue; }
      $id = DB::table('suppliers')->insertGetId($row + ['legacy_supplier_id'=>$lid, 'created_at'=>($x['at'] ?? now())]);
      DB::table('suppliers')->where('id', $id)->update(['code'=>'SUP-'.str_pad($id,5,'0',STR_PAD_LEFT), 'token'=>bin2hex(random_bytes(10))]);
      $sup++;
    }
    foreach (($d['purchases'] ?? []) as $x) {
      $lid = (int)($x['id'] ?? 0); if (!$lid) continue;
      if (DB::table('purchases')->where('legacy_purchase_id', $lid)->exists()) { $skip++; continue; }
      $sid = DB::table('suppliers')->where('legacy_supplier_id', (int)($x['sid'] ?? 0))->value('id');
      if (!$sid) { $nop++; continue; }
      DB::transaction(function () use ($x, $lid, $sid, &$pur, &$items) {
        $total = (float)($x['total'] ?? 0); $paid = min((float)($x['paid'] ?? 0), $total > 0 ? $total : (float)($x['paid'] ?? 0)); $date = $x['date'] ?? now()->toDateString();
        $pid = DB::table('purchases')->insertGetId(['invoice'=>'OLD-'.$lid, 'supplier_id'=>$sid, 'total'=>$total, 'paid'=>$paid, 'purchase_date'=>$date, 'note'=>($x['note'] ?? null) ?: null, 'status'=>'received', 'stock_applied'=>true, 'subtotal'=>$total, 'ptype'=>'Local', 'pay_type'=>$paid >= $total && $total > 0 ? 'Cash' : 'Credit', 'supplier_invoice'=>isset($x['inv']) ? mb_substr((string)$x['inv'],0,80) : null, 'approved_by'=>($x['by'] ?? null) ?: null, 'token'=>bin2hex(random_bytes(10)), 'legacy_purchase_id'=>$lid, 'created_at'=>($x['at'] ?? $date), 'updated_at'=>now()]);
        foreach (($x['items'] ?? []) as $it) {
          $prod = DB::table('products')->where('ext_id', (int)($it[0] ?? 0) + 999)->first(['id','name','sku']);
          $qty = (int)($it[2] ?? 0); $cost = (float)($it[1] ?? 0);
          DB::table('purchase_items')->insert(['purchase_id'=>$pid, 'product_id'=>$prod->id ?? null, 'name'=>mb_substr((string)($prod->name ?? ($it[3] ?? 'Legacy product')),0,250), 'qty'=>$qty, 'cost'=>$cost, 'sku'=>$prod->sku ?? null, 'line_total'=>$qty * $cost, 'created_at'=>now(), 'updated_at'=>now()]);
          $items++;
        }
        if ($paid > 0) {
          $py = DB::table('supplier_payments')->insertGetId(['supplier_id'=>$sid, 'purchase_id'=>$pid, 'pay_date'=>$date, 'amount'=>$paid, 'method'=>'Other', 'note'=>'Imported from old site', 'status'=>'paid', 'created_at'=>now(), 'updated_at'=>now()]);
          DB::table('supplier_payments')->where('id', $py)->update(['code'=>'PAY-'.str_pad($py,6,'0',STR_PAD_LEFT)]);
          DB::table('payment_allocations')->insert(['payment_id'=>$py, 'purchase_id'=>$pid, 'amount'=>$paid, 'created_at'=>now(), 'updated_at'=>now()]);
        }
        $pur++;
      });
    }
    return response()->json(['ok'=>true, 'sup'=>$sup, 'pur'=>$pur, 'skip'=>$skip, 'items'=>$items, 'nosup'=>$nop, 'tot_sup'=>DB::table('suppliers')->count(), 'tot_pur'=>DB::table('purchases')->count()]);
  }
}
