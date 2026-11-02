@extends('layout')@section('title', $mode==='damage' ? 'Damage Report' : 'Stock Movement')
@section('body')
@include('report_grid_assets')
@php $dm = $mode==='damage'; $cols = $dm ? 8 : 7; @endphp
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">{{ $dm ? 'Damage Report' : 'Stock Movement' }}</h1><span style="font-size:13px;color:#6B7280">{{ $dm ? 'Damaged products by product, with quantity and value.' : 'Every stock change by product: before, change, after.' }}</span></div>
<a class="btn g" href="?mode={{ $mode }}&from={{ $from }}&to={{ $to }}&q={{ urlencode($q) }}&export=csv">Export Excel (CSV)</a><a class="btn g" href="?mode={{ $mode }}&from={{ $from }}&to={{ $to }}&q={{ urlencode($q) }}&export=doc">Export Word</a><button class="g" onclick="window.print()">Print / PDF</button></div>
<form class="bar" method="get"><input type="hidden" name="mode" value="{{ $mode }}"><div><label>From</label><input type="date" name="from" value="{{ $from }}"></div><div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
<div><label>Search product, ID, reference</label><input name="q" value="{{ $q }}" placeholder="search" style="min-width:220px"></div>
<div><label>Rows</label><select name="per">@foreach([500,1000,2000,3000] as $n)<option {{ $per==$n?'selected':'' }}>{{ $n }}</option>@endforeach</select></div><div><button>Search</button> <a class="btn g" href="/admin/stock-movement-report?mode={{ $mode }}">Reset</a> <a class="btn g" href="/admin/stock-movement-report?mode={{ $dm?'movement':'damage' }}">{{ $dm ? 'Stock movement' : 'Damage report' }}</a></div></form>
<div class="rp-body"><div class="noprint" style="font-size:12px;color:#6B7280;margin-bottom:6px">Showing {{ number_format($g['n']) }} of {{ number_format($total) }} entries &middot; {{ $from }} &rarr; {{ $to }} &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.add('hide'));return false">collapse all</a> &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.remove('hide'));return false">expand all</a></div>
<div style="overflow:auto;max-height:78vh"><table class="pl"><thead><tr>@if($dm)<th>Date</th><th>Type</th><th>Code</th><th class="n">Qty</th><th class="n">Unit cost</th><th class="n">Value</th><th>Responsibility</th><th>Reason</th>@else<th>Date</th><th>Source</th><th>Reference</th><th class="n">Before</th><th class="n">Change</th><th class="n">After</th><th>User</th>@endif</tr></thead><tbody>
@forelse($groups as $i => $gr)
<tr class="gh" onclick="document.querySelectorAll('.d{{ $i }}').forEach(e=>e.classList.toggle('hide'))"><td colspan="{{ $cols }}">&#9660; {{ $gr['sku'] }} {{ $gr['name'] }} &nbsp;|&nbsp; Entries: {{ count($gr['rows']) }} &nbsp;|&nbsp; Qty: {{ number_format($gr['qty']) }}@if($dm) &nbsp;|&nbsp; Value: {{ number_format($gr['val'],2) }}@endif</td></tr>
@foreach($gr['rows'] as $x)
@if($dm)<tr class="det d{{ $i }}"><td>{{ substr((string)$x->d,0,10) }}</td><td>{{ $x->kind }}</td><td>{{ $x->ref }}</td><td class="n">{{ number_format((int)$x->cq) }}</td><td class="n">{{ number_format((float)$x->uc,2) }}</td><td class="n l">{{ number_format((float)$x->val,2) }}</td><td>{{ $x->who }}</td><td>{{ $x->reason }}</td></tr>
@else<tr class="det d{{ $i }}"><td>{{ substr((string)$x->d,0,16) }}</td><td>{{ $x->kind }}</td><td>{{ $x->ref }}</td><td class="n">{{ number_format((int)$x->bq) }}</td><td class="n {{ $x->cq>=0?'p':'l' }}">{{ $x->cq>0?'+':'' }}{{ number_format((int)$x->cq) }}</td><td class="n">{{ number_format((int)$x->aq) }}</td><td>{{ $x->who }}</td></tr>@endif
@endforeach
<tr class="st det d{{ $i }}"><td colspan="{{ $dm ? 3 : 4 }}" class="n">Total ({{ $gr['name'] }})</td><td class="n">{{ number_format($gr['qty']) }}</td>@if($dm)<td></td><td class="n">{{ number_format($gr['val'],2) }}</td><td colspan="2"></td>@else<td colspan="2"></td>@endif</tr>
@empty<tr><td colspan="{{ $cols }}" style="padding:14px">No entries for this filter.</td></tr>@endforelse
<tr class="gt"><td colspan="{{ $dm ? 3 : 4 }}">Grand Total ({{ number_format($g['n']) }} entries)</td><td class="n">{{ number_format($g['qty']) }}</td>@if($dm)<td></td><td class="n">{{ number_format($g['val'],2) }}</td><td colspan="2"></td>@else<td colspan="2"></td>@endif</tr>
</tbody></table></div></div>
@include('report_grid_js')
@endsection
