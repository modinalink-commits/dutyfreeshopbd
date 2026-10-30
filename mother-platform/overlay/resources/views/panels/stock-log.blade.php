@php $open = ['New','Incomplete','Pre Order','Assign','Pending','AI Order','Approved','Scheduled','WFP','Packaging'];
$rs = DB::table('order_items as i')->join('orders as o','o.id','=','i.order_id')->whereIn('o.status',$open)->whereNotNull('i.product_id')->select('i.product_id', DB::raw('sum(i.qty) q'))->groupBy('i.product_id')->pluck('q','product_id');
$dmg = DB::table('damaged_products')->select('product_id', DB::raw('sum(qty) q'))->groupBy('product_id')->pluck('q','product_id');
$ps = DB::table('products')->orderBy('stock')->limit(30)->get(); @endphp
<div class="card"><div style="display:flex;align-items:center"><b style="flex:1">Stock by product (lowest first)</b><a href="/admin/m/r-products/export/xls" style="font-size:13px;color:#FF5A00;font-weight:600">Download all (Excel)</a></div>
<div style="overflow-x:auto"><table><thead><tr><th>Product</th><th>On hand</th><th>Reserved</th><th>Available</th><th>Damaged</th><th>Level</th></tr></thead><tbody>
@forelse($ps as $p)@php $r = (int)($rs[$p->id] ?? 0); $av = max(0, $p->stock - $r); $lv = $av <= 0 ? ['Out of stock','red'] : ($av <= 5 ? ['Low',''] : ['Healthy','ok']); @endphp<tr><td>{{ $p->name }}<br><small style="color:#6B7280">{{ $p->sku }}</small></td><td>{{ $p->stock }}</td><td>{{ $r }}</td><td><b>{{ $av }}</b></td><td>{{ $dmg[$p->id] ?? 0 }}</td><td><span class="pill {{ $lv[1] }}">{{ $lv[0] }}</span></td></tr>
@empty<tr><td colspan="6">No products yet.</td></tr>@endforelse</tbody></table></div>
<p style="font-size:12px;color:#6B7280">Reserved = items in open orders (New to Packaging). Per-store quotas and transfers between warehouses come with multi-store setup.</p></div>
