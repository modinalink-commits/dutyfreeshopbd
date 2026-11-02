@extends('layout')@section('title','Income & Expense')
@section('body')
@include('report_grid_assets')
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Income &amp; Expense (Daily Cash)</h1><span style="font-size:13px;color:#6B7280">Every income and expense entry by account, with subtotals and a grand total.</span></div>
<a class="btn g" href="?from={{ $from }}&to={{ $to }}&account={{ $acc }}&dir={{ $dir }}&q={{ urlencode($q) }}&export=csv">Export Excel (CSV)</a><a class="btn g" href="?from={{ $from }}&to={{ $to }}&account={{ $acc }}&dir={{ $dir }}&q={{ urlencode($q) }}&export=doc">Export Word</a><button class="g" onclick="window.print()">Print / PDF</button></div>
<form class="bar" method="get"><div><label>From</label><input type="date" name="from" value="{{ $from }}"></div><div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
<div><label>Account</label><select name="account"><option value="0">All accounts</option>@foreach($accounts as $a)<option value="{{ $a->id }}" {{ $acc==$a->id?'selected':'' }}>{{ $a->name }}</option>@endforeach</select></div>
<div><label>Type</label><select name="dir"><option value="">Income and expense</option><option value="income" {{ $dir==='income'?'selected':'' }}>Income only</option><option value="expense" {{ $dir==='expense'?'selected':'' }}>Expense only</option></select></div>
<div><label>Search reference, note, account</label><input name="q" value="{{ $q }}" placeholder="search" style="min-width:200px"></div>
<div><label>Rows</label><select name="per">@foreach([200,500,1000,2000] as $n)<option {{ $per==$n?'selected':'' }}>{{ $n }}</option>@endforeach</select></div><div><button>Search</button> <a class="btn g" href="/admin/cashbook-report">Reset</a></div></form>
<div class="rp-body"><div class="noprint" style="font-size:12px;color:#6B7280;margin-bottom:6px">Showing {{ number_format($g['n']) }} of {{ number_format($total) }} entries &middot; {{ $from }} &rarr; {{ $to }} &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.add('hide'));return false">collapse all</a> &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.remove('hide'));return false">expand all</a></div>
<div style="overflow:auto;max-height:78vh"><table class="pl"><thead><tr><th>Date</th><th>Reference</th><th>Note</th><th class="n">Income</th><th class="n">Expense</th></tr></thead><tbody>
@forelse($groups as $i => $gr)
<tr class="gh" onclick="document.querySelectorAll('.d{{ $i }}').forEach(e=>e.classList.toggle('hide'))"><td colspan="5">&#9660; Account: {{ $gr['name'] }} &nbsp;|&nbsp; Income: <span class="p">{{ number_format($gr['in'],2) }}</span> &nbsp;|&nbsp; Expense: <span class="l">{{ number_format($gr['out'],2) }}</span> &nbsp;|&nbsp; Net: {{ number_format($gr['in']-$gr['out'],2) }}</td></tr>
@foreach($gr['rows'] as $x)<tr class="det d{{ $i }}"><td>{{ $x['d'] }}</td><td>{{ $x['ref'] }}</td><td>{{ $x['c'] }}</td><td class="n p">{{ $x['in'] ? number_format($x['in'],2) : '' }}</td><td class="n l">{{ $x['out'] ? number_format($x['out'],2) : '' }}</td></tr>@endforeach
<tr class="st det d{{ $i }}"><td colspan="3" class="n">Total ({{ $gr['name'] }})</td><td class="n">{{ number_format($gr['in'],2) }}</td><td class="n">{{ number_format($gr['out'],2) }}</td></tr>
@empty<tr><td colspan="5" style="padding:14px">No entries for this filter.</td></tr>@endforelse
<tr class="gt"><td colspan="3">Grand Total ({{ number_format($g['n']) }} entries)</td><td class="n">{{ number_format($g['in'],2) }}</td><td class="n">{{ number_format($g['out'],2) }}</td></tr>
<tr class="gt"><td colspan="5">Total income: <span class="p">{{ number_format($g['in'],2) }}</span> &nbsp;|&nbsp; Total expense: <span class="l">{{ number_format($g['out'],2) }}</span> &nbsp;|&nbsp; Net: <span class="{{ $g['in']-$g['out']>=0?'p':'l' }}">{{ number_format($g['in']-$g['out'],2) }}</span></td></tr>
</tbody></table></div></div>
@include('report_grid_js')
@endsection
