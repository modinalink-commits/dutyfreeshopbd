<?php
namespace App\Http\Controllers;

use App\Support\Costing;
use App\Support\ProfitFloor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only business snapshots for the n8n automation server (Telegram reports). Bearer token only (hash below); no personal data, no writes. */
class N8nController extends Controller
{
  /** sha256 of the token that was given to the owner for the n8n credential. */
  private const TOKEN_SHA = '57a6717d9221e3a96c9d2a13cb45310b1cab684a08ff78e98f55a5931e765f60';

  public function snapshot(Request $r) {
    $t = (string)$r->bearerToken(); abort_unless($t !== '' && hash_equals(self::TOKEN_SHA, hash('sha256', $t)), 401);
    $k = (string)$r->query('kind');
    $d = match ($k) { 'summary' => $this->summary(), 'stock' => $this->stock(), 'stuck' => $this->stuck(), default => abort(404) };
    return response()->json(['kind' => $k, 'at' => now('Asia/Dhaka')->format('d M Y H:i')] + $d)->header('Cache-Control', 'no-store');
  }

  /** Today (Dhaka) order summary. */
  private function summary(): array {
    $from = now('Asia/Dhaka')->startOfDay()->utc(); $o = DB::table('orders')->where('created_at', '>=', $from);
    $by = (clone $o)->selectRaw('status, count(*) as n, coalesce(sum(total),0) as amt')->groupBy('status')->get();
    $all = (int)$by->sum('n'); $bad = $by->filter(fn($x) => in_array($x->status, ['Cancelled', 'Canceled', 'Return', 'Returned'], true));
    $good = $by->reject(fn($x) => in_array($x->status, ['Cancelled', 'Canceled', 'Return', 'Returned'], true));
    $cust = Schema::hasTable('customers') ? DB::table('customers')->where('created_at', '>=', $from)->count() : null;
    $ship = DB::table('orders')->where('status', 'Shipment')->count(); $pend = DB::table('orders')->whereIn('status', ['New', 'Pending'])->count();
    return ['orders_today' => $all, 'revenue_today' => round((float)$good->sum('amt')), 'cancelled_or_returned_today' => (int)$bad->sum('n'), 'by_status_today' => $by->pluck('n', 'status'), 'new_customers_today' => $cust, 'open_pending_now' => $pend, 'in_shipment_now' => $ship];
  }

  /** Published products low or out of stock, and products priced below the protected margin. */
  private function stock(): array {
    $ctl = DB::table('categories')->where('inventory_mode', 'STOCK_CONTROLLED')->pluck('id')->all(); $ctl = array_values(array_unique(array_merge($ctl, DB::table('categories')->whereIn('parent_id', $ctl ?: [0])->pluck('id')->all()))); if (!$ctl) $ctl = [0];
    $low = DB::table('products')->where('published', true)->whereIn('category_id', $ctl)->where('stock', '<=', 3)->orderBy('stock')->limit(15)->get(['name', 'stock'])->map(fn($p) => ['name' => $p->name, 'stock' => (int)$p->stock]);
    $cnt = DB::table('products')->where('published', true)->whereIn('category_id', $ctl)->where('stock', '<=', 0)->count(); $lowc = DB::table('products')->where('published', true)->whereIn('category_id', $ctl)->where('stock', '>', 0)->where('stock', '<=', 3)->count();
    $c = Costing::fifoSql('p'); $below = DB::table('products as p')->where('p.published', true)->where('p.price', '>', 0)->whereRaw("$c > 0")->whereRaw("p.price + 0.004 < $c * ?", [ProfitFloor::rate()])->count();
    $need = DB::table('orders as o')->whereIn('o.status', ['Approved', 'Packaging'])->whereExists(fn($q) => $q->select(DB::raw(1))->from('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')->whereColumn('i.order_id', 'o.id')->where('p.stock', '<=', 0))->count();
    return ['out_of_stock_published' => $cnt, 'low_stock_published' => $lowc, 'lowest_stock' => $low, 'below_margin_products' => $below, 'open_orders_waiting_for_stock' => $need];
  }

  /** Orders that have been sitting in one status for too long. */
  private function stuck(): array {
    $rule = [['New', 6, 'Pending/New for more than 6 hours'], ['Pending', 6, 'Pending/New for more than 6 hours'], ['Approved', 24, 'Approved for more than 24 hours'], ['Packaging', 24, 'Packaging for more than 24 hours'], ['Shipment', 24 * 5, 'In Shipment for more than 5 days']];
    $out = []; $seen = [];
    foreach ($rule as [$st, $h, $label]) {
      $rows = DB::table('orders')->where('status', $st)->where('updated_at', '<', now()->subHours($h))->orderBy('updated_at')->limit(10)->get(['invoice', 'status', 'updated_at', 'total']);
      $n = DB::table('orders')->where('status', $st)->where('updated_at', '<', now()->subHours($h))->count();
      if ($n) $out[] = ['rule' => $label, 'status' => $st, 'count' => $n, 'oldest' => $rows->map(fn($x) => ['invoice' => $x->invoice, 'hours' => (int)abs(now()->diffInHours($x->updated_at)), 'total' => round((float)$x->total)])];
    }
    return ['stuck_groups' => $out, 'total_stuck' => array_sum(array_column($out, 'count'))];
  }
}
