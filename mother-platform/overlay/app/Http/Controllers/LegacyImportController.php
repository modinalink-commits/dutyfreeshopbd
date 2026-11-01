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
  public function review() {
    $m = "exists (select 1 from customers c where right(c.phone,11) = l.phone)";
    $q = fn($w) => (int)DB::selectOne("select count(*) n from legacy_customers l where $w")->n;
    $d = ['staged' => $q('true'), 'real' => $q(self::REAL), 'test' => $q('not ('.self::REAL.')'), 'matched' => $q(self::REAL." and $m"), 'new' => $q(self::REAL." and not $m"), 'with_orders' => $q('l.orders_count > 0'),
      'sum_orders' => (int)DB::selectOne('select coalesce(sum(orders_count),0) n from legacy_customers')->n, 'sum_amount' => (float)DB::selectOne('select coalesce(sum(orders_amount),0) n from legacy_customers')->n,
      'email' => $q("coalesce(l.email,'') <> ''"), 'done' => $q("l.action is not null"), 'now' => (int)DB::table('customers')->count()];
    $d['tiers'] = DB::select("select coalesce(".self::TIER.", 'Regular') t, count(*) n from legacy_customers l where ".self::REAL." group by 1 order by 2 desc");
    return view('legacy_import', ['d' => $d]);
  }
  public function apply(Request $r) {
    abort_unless($r->input('confirm') === 'IMPORT', 422);
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
}
