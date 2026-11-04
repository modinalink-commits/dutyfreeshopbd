@extends('layout')@section('title','Stock History')
@section('body')
@include('report_grid_assets')
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Stock Tracking History</h1><span style="font-size:13px;color:#6B7280">Every stock movement of this product, newest first.</span></div>
<a class="btn g" href="?export=csv">Export Excel (CSV)</a><a class="btn g" href="/admin/stock-report">Stock Report</a><a class="btn g" href="/admin/products/{{ $p->id }}/edit">Edit product</a></div>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:12px 14px;margin-bottom:10px;display:flex;flex-wrap:wrap;gap:18px;font-size:13.5px">
<div><span style="color:#6B7280">Product</span><br><b>{{ $p->name }}</b></div><div><span style="color:#6B7280">SKU</span><br><b>{{ $p->sku ?: $p->id }}</b></div><div><span style="color:#6B7280">Category</span><br><b>{{ $p->cat ?: '-' }}</b></div>
<div><span style="color:#6B7280">Current stock</span><br><b style="font-size:17px;{{ (int)$p->stock <= 0 ? 'color:#B91C1C' : '' }}">{{ (int)$p->stock }}</b></div>
<div><span style="color:#6B7280">Inventory mode</span><br><b>{{ $mode }}</b></div><div><span style="color:#6B7280">Storefront</span><br><b>{{ $p->published ? 'Published' : 'Unpublished' }}</b></div></div>
@if($mode === 'STOCK_CONTROLLED')<div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:10px;padding:8px 12px;margin-bottom:10px;font-size:12.5px;color:#9A3412">Stock-controlled: zero stock unpublishes the product, stock-in publishes it, and it cannot be shipped without stock.</div>@endif
<div class="rp-body"><div style="overflow:auto;max-height:78vh"><table class="pl"><thead><tr><th>Date &amp; Time</th><th>Movement</th><th class="n">Previous</th><th class="n">Change</th><th class="n">New stock</th><th>Invoice</th><th>Purchase ref</th><th>Courier status</th><th>Staff / System</th><th>Note</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ $x['at'] }}</td><td><b>{{ $x['type'] }}</b></td><td class="n">{{ $x['before'] }}</td><td class="n" style="font-weight:700;color:{{ $x['chg'] < 0 ? '#B91C1C' : '#15803D' }}">{{ $x['chg'] > 0 ? '+' : '' }}{{ $x['chg'] }}</td><td class="n">{{ $x['after'] }}</td><td>{{ $x['inv'] }}</td><td>{{ $x['pur'] }}</td><td>{{ $x['courier'] }}</td><td>{{ $x['who'] }}</td><td>{{ $x['note'] }}</td></tr>
@empty<tr><td colspan="10" style="padding:14px">No stock movement recorded for this product yet.</td></tr>@endforelse
</tbody></table></div></div>
@include('report_grid_js')
@endsection
