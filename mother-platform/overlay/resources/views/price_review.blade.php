@extends('layout')@section('title','Price Review')
@section('body')
@include('report_grid_assets')
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Price Review Required</h1><span style="font-size:13px;color:#6B7280">Products priced below the protected minimum price. Fix the selling price to clear a product from this list.</span></div>
<a class="btn" href="/admin/inventory-pricing">Inventory Pricing (FIFO &amp; Average Cost)</a><a class="btn g" href="?export=xlsx">Download ALL products Excel (price, cost, profit, ROI, stock)</a><a class="btn g" href="?export=raised">Prices raised (old vs new)</a><a class="btn g" href="?export=csv">Below-minimum list (CSV)</a></div>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:10px 14px;margin-bottom:10px;font-size:14px"><b>{{ number_format($total) }}</b> products below the minimum price ({{ number_format($pubN) }} published, {{ number_format($total - $pubN) }} unpublished)@if($total > count($rows)) · showing the {{ count($rows) }} with the biggest shortfall, download the CSV for the full list @endif</div>
<div class="rp-body"><div style="overflow:auto;max-height:78vh"><table class="pl"><thead><tr><th>Product</th><th>SKU</th><th>Category</th><th class="n">FIFO batch cost</th><th class="n">Selling</th><th class="n">Minimum</th><th class="n">Shortfall</th><th class="n">Margin on cost</th><th class="n">Stock</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ $x['name'] }}</td><td>{{ $x['sku'] }}</td><td>{{ $x['cat'] }}</td><td class="n">{{ number_format($x['cost'],2) }}</td><td class="n">{{ number_format($x['price'],2) }}</td><td class="n">{{ number_format($x['min'],2) }}</td><td class="n" style="color:#B91C1C;font-weight:700">{{ number_format($x['short'],2) }}</td><td class="n">{{ $x['margin'] }}%</td><td class="n">{{ $x['stock'] }}</td><td>{{ $x['pub'] ? 'Published' : 'Unpublished' }}</td><td><a href="/admin/products/{{ $x['id'] }}/edit">Fix price</a></td></tr>
@empty<tr><td colspan="11" style="padding:14px">All products are at or above the protected minimum.</td></tr>@endforelse
</tbody></table></div></div>
@include('report_grid_js')
@endsection
