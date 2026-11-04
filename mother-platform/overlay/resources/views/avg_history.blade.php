@extends('layout')@section('title','Average price history')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px"><div style="flex:1"><h1 style="margin:0">Average Purchase Price history</h1><span style="font-size:13px;color:#6B7280">{{ $p->name }} · SKU {{ $p->sku ?: $p->id }} · average now ৳{{ number_format(\App\Support\ProfitFloor::avg($p), 2) }}</span></div><a class="btn g" href="/admin/inventory-pricing">Back</a></div>
<div class="card"><div style="overflow:auto"><table><thead><tr><th>Date</th><th>Source</th><th>Reference</th><th class="n">Qty</th><th class="n">Unit cost</th><th class="n">Old average</th><th class="n">New average</th><th>By</th></tr></thead><tbody>
@forelse($rows as $x)<tr><td>{{ \Carbon\Carbon::parse($x->created_at)->format('d M Y, h:i A') }}</td><td>{{ $x->source }}</td><td>{{ $x->reference }}</td><td class="n">{{ $x->qty }}</td><td class="n">{{ number_format($x->unit_cost,2) }}</td><td class="n">{{ number_format($x->old_avg,2) }}</td><td class="n"><b>{{ number_format($x->new_avg,2) }}</b></td><td>{{ $x->user_name }}</td></tr>
@empty<tr><td colspan="8" style="padding:14px">No stock-in since average tracking started. The current average came from the last purchase price / current stock lots.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
