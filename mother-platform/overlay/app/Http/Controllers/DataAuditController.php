<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

/** Read-only data health check (Admin only). Counts + a few sample rows per check; changes nothing. */
class DataAuditController extends Controller
{
  private function checks(): array {
    $P = 'products p';
    return [
      ['Products: total', "select count(*) n from products", null],
      ['Orders from 1 Nov: total', "select count(*) n from orders o where o.created_at >= '2026-10-31 18:00:00'", null],
      ['Published without image', "select count(*) n from products where published and coalesce(image,'')=''", "select id, name from products where published and coalesce(image,'')='' order by id limit 12"],
      ['Published with price 0 or less', "select count(*) n from products where published and price <= 0", "select id, name, price from products where published and price <= 0 order by id limit 12"],
      ['Published with no category', "select count(*) n from products where published and category_id is null", "select id, name from products where published and category_id is null order by id limit 12"],
      ['Negative stock', "select count(*) n from products where stock < 0", "select id, name, stock from products where stock < 0 order by stock limit 12"],
      ['Selling below cost (price < cost, cost > 0)', "select count(*) n from products where cost > 0 and price < cost", "select id, name, price, cost from products where cost > 0 and price < cost order by (cost - price) desc limit 12"],
      ['Duplicate SKU (same SKU on 2+ products)', "select count(*) n from (select sku from products where coalesce(sku,'')<>'' group by sku having count(*)>1) x", "select sku, count(*) c from products where coalesce(sku,'')<>'' group by sku having count(*)>1 order by c desc limit 12"],
      ['Duplicate product name (same name, 2+ products)', "select count(*) n from (select lower(trim(name)) k from products group by 1 having count(*)>1) x", "select min(name) name, count(*) c from products group by lower(trim(name)) having count(*)>1 order by c desc limit 12"],
      ['Name with leading/trailing/double spaces', "select count(*) n from products where name <> regexp_replace(trim(name), '\\s+', ' ', 'g')", "select id, name from products where name <> regexp_replace(trim(name), '\\s+', ' ', 'g') order by id limit 12"],
      ['Order items pointing to a missing product (from 1 Nov)', "select count(*) n from order_items i join orders o on o.id=i.order_id left join products p on p.id=i.product_id where i.product_id is not null and p.id is null and o.created_at >= '2026-10-31 18:00:00'", "select i.id, i.order_id, i.name from order_items i join orders o on o.id=i.order_id left join products p on p.id=i.product_id where i.product_id is not null and p.id is null and o.created_at >= '2026-10-31 18:00:00' order by i.id desc limit 12"],
      ['Orders with no items (from 1 Nov)', "select count(*) n from orders o where o.created_at >= '2026-10-31 18:00:00' and not exists (select 1 from order_items i where i.order_id=o.id)", "select o.status, count(*) c, min(o.invoice) first_invoice from orders o where o.created_at >= '2026-10-31 18:00:00' and not exists (select 1 from order_items i where i.order_id=o.id) group by o.status order by c desc limit 12"],
      ['Orders (from 1 Nov) where total differs from items - discount (> ৳5, incl. delivery charge)', "select count(*) n from orders o join (select order_id, sum(qty*price) s from order_items group by order_id) x on x.order_id=o.id where o.created_at >= '2026-10-31 18:00:00' and o.status not in ('Cancel') and abs(o.total - (x.s - coalesce(o.discount,0) + coalesce(o.shipping,0))) > 5", "select o.id, o.invoice, o.status, o.total, x.s items, o.discount, o.shipping, o.courier_fee from orders o join (select order_id, sum(qty*price) s from order_items group by order_id) x on x.order_id=o.id where o.created_at >= '2026-10-31 18:00:00' and o.status not in ('Cancel') and abs(o.total - (x.s - coalesce(o.discount,0) + coalesce(o.shipping,0))) > 5 order by o.id desc limit 12"],
      ['Orders with no phone (from 1 Nov)', "select count(*) n from orders o where o.created_at >= '2026-10-31 18:00:00' and coalesce(trim(o.phone),'')=''", "select o.id, o.invoice, o.status from orders o where o.created_at >= '2026-10-31 18:00:00' and coalesce(trim(o.phone),'')='' order by o.id desc limit 12"],
    ];
  }

  public function index() {
    abort_unless(\App\Support\ProfitFloor::admin(), 403);
    $out = []; $t0 = microtime(true);
    foreach ($this->checks() as [$title, $count, $sample]) {
      $row = ['t' => $title, 'n' => null, 's' => [], 'e' => null];
      try {
        $row['n'] = (int)(DB::selectOne($count)->n ?? 0);
        if ($sample && $row['n'] > 0) $row['s'] = array_map(fn($x) => (array)$x, DB::select($sample));
      } catch (\Throwable $e) { $row['e'] = substr($e->getMessage(), 0, 160); }
      $out[] = $row;
    }
    $h = '<!doctype html><meta charset="utf-8"><title>Data Audit</title><style>body{font:14px system-ui;margin:24px;color:#0B1220}h1{font-size:18px}table{border-collapse:collapse;margin:6px 0 16px}td,th{border:1px solid #E5E7EB;padding:3px 8px;font-size:12.5px;text-align:left}.ok{color:#166534}.bad{color:#B91C1C;font-weight:700}.e{color:#B45309}</style><h1>Data Audit (read-only)</h1>';
    foreach ($out as $r) {
      $cls = $r['e'] ? 'e' : ($r['n'] > 0 && !in_array($r['t'], ['Products: total','Orders from 1 Nov: total'], true) ? 'bad' : 'ok');
      $h .= '<div class="'.$cls.'">'.e($r['t']).': '.($r['e'] ? 'ERROR '.e($r['e']) : number_format((int)$r['n'])).'</div>';
      if ($r['s']) { $h .= '<table><tr>'.implode('', array_map(fn($k) => '<th>'.e($k).'</th>', array_keys($r['s'][0]))).'</tr>'; foreach ($r['s'] as $x) $h .= '<tr>'.implode('', array_map(fn($v) => '<td>'.e((string)$v).'</td>', $x)).'</tr>'; $h .= '</table>'; }
    }
    return response($h.'<p style="color:#6B7280">Took '.round((microtime(true) - $t0) * 1000).' ms</p>');
  }
}
