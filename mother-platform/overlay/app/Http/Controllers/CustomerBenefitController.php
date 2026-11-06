<?php
namespace App\Http\Controllers;
use App\Support\CustomerBenefit;
use App\Support\ProfitFloor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Customer Benefit Eligibility: admin switches, the eligibility report (+ CSV) and the authorised Suspended / Review flag with reason and audit. */
class CustomerBenefitController extends Controller {
  const SWITCHES = ['membership'=>'Membership based on successful receiving', 'discount'=>'Discount based on successful receiving', 'delivery'=>'Free delivery based on successful receiving', 'reward'=>'Loyalty reward based on successful receiving', 'coupon'=>'Coupon eligibility check', 'abuse'=>'Fraud / abuse restriction'];
  private function rows(Request $r, int $limit = 200): array {
    $days = max(1, min(730, (int)($r->query('days') ?: 90))); $q = trim((string)$r->query('q', ''));
    $sql = "select right(regexp_replace(o.phone,'\\D','','g'),11) p, max(o.customer_name) name, count(*) total,
      count(*) filter (where o.status = 'Delivered' or (o.status = 'Partial Delivered' and coalesce(o.collected_amount,0) > 0)) rc,
      coalesce(sum(case when o.status = 'Delivered' then o.total when o.status = 'Partial Delivered' then coalesce(o.collected_amount,0) else 0 end),0) amt,
      count(*) filter (where o.status = 'Cancel') cn, count(*) filter (where o.status = 'Returned') rt
      from orders o where o.created_at >= now() - (? || ' days')::interval".($q !== '' ? " and (o.phone like ? or o.customer_name ilike ?)" : "")."
      group by 1 order by total desc limit ".(int)$limit;
    $b = [(string)$days]; if ($q !== '') { $b[] = '%'.$q.'%'; $b[] = '%'.$q.'%'; }
    $list = DB::select($sql, $b); $phones = array_values(array_filter(array_map(fn($x) => $x->p, $list)));
    $cust = []; if ($phones) { $in = implode(',', array_fill(0, count($phones), '?'));
      foreach (DB::select("select * from customers where right(regexp_replace(phone,'\\D','','g'),11) in ($in)", $phones) as $c) $cust[substr(preg_replace('/\D/', '', (string)$c->phone), -11)] ??= $c; }
    $out = [];
    foreach ($list as $x) { $c = $cust[$x->p] ?? null; $rc = (int)$x->rc + (int)($c->legacy_orders ?? 0); $amt = (float)$x->amt + (float)($c->legacy_spent ?? 0);
      [$st, $rs] = CustomerBenefit::classify($rc, (int)$x->rc, (int)$x->cn, (int)$x->rt, $c); $fin = (int)$x->rc + (int)$x->cn + (int)$x->rt;
      $out[] = ['phone'=>$x->p, 'name'=>$x->name, 'total'=>(int)$x->total, 'received'=>$rc, 'amount'=>$amt, 'cancelled'=>(int)$x->cn, 'returned'=>(int)$x->rt, 'rate'=>$fin > 0 ? round((int)$x->rc / $fin * 100, 1) : null, 'status'=>$st, 'reason'=>implode(', ', $rs), 'note'=>$c->benefit_note ?? null, 'flag'=>$c->benefit_flag ?? null]; }
    return ['rows'=>$out, 'days'=>$days, 'q'=>$q];
  }
  public function index(Request $r) {
    $d = $this->rows($r); $sum = ['Eligible'=>0, 'Limited'=>0, 'Review Required'=>0, 'Suspended'=>0]; foreach ($d['rows'] as $x) $sum[$x['status']]++;
    $sw = []; foreach (self::SWITCHES as $k => $l) $sw[$k] = ['label'=>$l, 'on'=>CustomerBenefit::on($k)];
    return view('customer_benefit', $d + ['sum'=>$sum, 'sw'=>$sw, 'admin'=>ProfitFloor::admin()]);
  }
  public function csv(Request $r) {
    $d = $this->rows($r, 5000); $o = fopen('php://temp', 'w+'); fputcsv($o, ['Customer', 'Mobile', 'Total Orders', 'Received Orders', 'Received Amount', 'Cancelled', 'Returned', 'Success Rate %', 'Benefit Status', 'Reason']);
    foreach ($d['rows'] as $x) fputcsv($o, [$x['name'], $x['phone'], $x['total'], $x['received'], $x['amount'], $x['cancelled'], $x['returned'], $x['rate'], $x['status'], $x['reason']]);
    rewind($o); return response("\xEF\xBB\xBF".stream_get_contents($o), 200, ['Content-Type'=>'text/csv; charset=UTF-8', 'Content-Disposition'=>'attachment; filename="customer-benefit-eligibility.csv"']);
  }
  public function saveSwitches(Request $r) {
    abort_unless(ProfitFloor::admin(), 403, 'Benefit settings are for the Admin only.');
    foreach (self::SWITCHES as $k => $l) DB::table('settings')->updateOrInsert(['key'=>'benefit_'.$k], ['value'=>$r->boolean('sw_'.$k) ? '1' : '0', 'updated_at'=>now(), 'created_at'=>now()]);
    $this->audit('Customer benefit settings changed', json_encode(array_map(fn($k) => $k.'='.($r->boolean('sw_'.$k) ? 'on' : 'off'), array_keys(self::SWITCHES))));
    return back()->with('ok', 'Benefit settings saved.');
  }
  /** Authorised override: Suspended / Review Required / clear, always with a reason and an audit row (no silent change). */
  public function flag(Request $r) {
    abort_unless(ProfitFloor::admin(), 403, 'Only an authorised person can change a customer benefit status.');
    $d = $r->validate(['phone'=>'required|string|max:20', 'flag'=>'required|in:suspended,review,clear', 'reason'=>'required|string|min:5|max:250']);
    $p = substr(preg_replace('/\D/', '', $d['phone']), -11);
    $c = DB::table('customers')->whereRaw("right(regexp_replace(phone,'\\D','','g'),11) = ?", [$p])->orderBy('id')->first();
    if (!$c) return back()->with('err', 'No customer record for '.$p.'. Nothing was changed.');
    $old = $c->benefit_flag ?: 'none'; $new = $d['flag'] === 'clear' ? null : $d['flag'];
    DB::table('customers')->where('id', $c->id)->update(['benefit_flag'=>$new, 'benefit_note'=>mb_substr($d['reason'], 0, 250), 'updated_at'=>now()]);
    $this->audit('Customer benefit status override', $p.': '.$old.' -> '.($new ?: 'none').' · reason: '.mb_substr($d['reason'], 0, 200));
    return back()->with('ok', 'Benefit status for '.$p.' set to '.($new ?: 'normal (cleared)').'.');
  }
  private function audit(string $a, string $s): void { try { DB::table('audit_logs')->insert(['user_id'=>auth()->id(), 'user_name'=>auth()->user()->name ?? null, 'action'=>$a, 'subject'=>mb_substr($s, 0, 250), 'meta'=>json_encode(['ip'=>request()->ip()]), 'created_at'=>now(), 'updated_at'=>now()]); } catch (\Throwable $e) { report($e); } }
}
