<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
/** Invoice and parcel-label printing, single or bulk. One template each; every selected order is its own sheet, printed one after another. */
class PrintController extends Controller {
  private static function words(int $n): string {
    if ($n === 0) return 'Zero';
    $a = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $b = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = fn($x) => $x < 20 ? $a[$x] : $b[intdiv($x, 10)].($x % 10 ? '-'.$a[$x % 10] : '');
    $hund = fn($x) => ($x >= 100 ? $a[intdiv($x, 100)].' Hundred'.($x % 100 ? ' ' : '') : '').($x % 100 ? $two($x % 100) : '');
    $o = []; foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$d, $nm]) { if ($n >= $d) { $o[] = $hund(intdiv($n, $d)).' '.$nm; $n %= $d; } }
    if ($n > 0) $o[] = $hund($n);
    return implode(' ', $o);
  }
  public function show(Request $r) {
    $ids = $r->input('ids', $r->query('id')); if (is_string($ids)) $ids = explode(',', $ids);
    $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', (array)$ids)))), 0, 200); abort_unless($ids, 404);
    $type = $r->input('type') === 'label' ? 'label' : 'invoice';
    $orders = DB::table('orders')->whereIn('id', $ids)->get()->sortBy(fn($o) => array_search($o->id, $ids))->values();
    $items = DB::table('order_items as i')->leftJoin('products as p', 'p.id', '=', 'i.product_id')->whereIn('i.order_id', $ids)->orderBy('i.id')->get(['i.order_id', 'i.name', 'i.qty', 'i.price', 'p.sku'])->groupBy('order_id');
    $sheets = $orders->map(function ($o) use ($items) {
      $its = $items[$o->id] ?? collect(); $paid = ($o->pay_status ?? null) === 'Verified' ? (float)$o->total : 0.0; $disc = (float)($o->discount ?? 0);
      return ['o'=>$o, 'items'=>$its, 'qty'=>(int)$its->sum('qty'), 'sub'=>round((float)$o->total + $disc - (float)$o->shipping, 2), 'paid'=>$paid, 'due'=>max(0, round((float)$o->total - $paid, 2)),
        'words'=>'BDT '.self::words((int)round(max(0, (float)$o->total - $paid))).' Taka Only', 'pay'=>$o->payment_method ?: 'Cash on Delivery', 'track'=>$o->consignment_id ?: ($o->tracking_code ?? null),
        'url'=>url('/track').'?invoice='.rawurlencode(\App\Support\Inv::show($o->invoice)), 'bc'=>\App\Support\Code128::svg(\App\Support\Inv::show($o->invoice), 40)];
    });
    return view('print_docs', ['sheets'=>$sheets, 'type'=>$type]);
  }
}
