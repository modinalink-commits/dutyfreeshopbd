@extends('layout')@section('title','Supplier Ledger')
@section('body')
@include('report_grid_assets')
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Supplier Ledger</h1><span style="font-size:13px;color:#6B7280">Purchases and payments per supplier with a running balance (positive = we owe the supplier).</span></div>
<a class="btn g" href="?from={{ $from }}&to={{ $to }}&supplier={{ $sup }}&q={{ urlencode($q) }}&export=csv">Export Excel (CSV)</a><a class="btn g" href="?from={{ $from }}&to={{ $to }}&supplier={{ $sup }}&q={{ urlencode($q) }}&export=doc">Export Word</a><button class="g" onclick="window.print()">Print / PDF</button></div>
<form class="bar" method="get"><div><label>From</label><input type="date" name="from" value="{{ $from }}"></div><div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
<div><label>Supplier</label><select name="supplier"><option value="0">All suppliers</option>@foreach($all as $s)<option value="{{ $s->id }}" {{ $sup==$s->id?'selected':'' }}>{{ $s->name }}</option>@endforeach</select></div>
<div><label>Search supplier</label><input name="q" value="{{ $q }}" placeholder="search" style="min-width:200px"></div><div><button>Search</button> <a class="btn g" href="/admin/supplier-ledger-report">Reset</a></div></form>
<div class="rp-body"><div class="noprint" style="font-size:12px;color:#6B7280;margin-bottom:6px">{{ count($groups) }} suppliers with activity &middot; {{ $from }} &rarr; {{ $to }} &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.add('hide'));return false">collapse all</a> &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.remove('hide'));return false">expand all</a></div>
<div style="overflow:auto;max-height:78vh"><table class="pl"><thead><tr><th>Date</th><th>Type</th><th>Reference</th><th class="n">Purchase (owed)</th><th class="n">Payment (paid)</th><th class="n">Balance</th></tr></thead><tbody>
@forelse($groups as $i => $gr)
<tr class="gh" onclick="document.querySelectorAll('.d{{ $i }}').forEach(e=>e.classList.toggle('hide'))"><td colspan="6">&#9660; Supplier: {{ $gr['s']->name }} {{ $gr['s']->phone ? '| '.$gr['s']->phone : '' }} &nbsp;|&nbsp; Opening: {{ number_format($gr['open'],2) }} &nbsp;|&nbsp; Closing: <span class="{{ $gr['close']>0?'l':'p' }}">{{ number_format($gr['close'],2) }}</span></td></tr>
<tr class="det d{{ $i }}"><td>{{ $from }}</td><td>Opening balance</td><td></td><td></td><td></td><td class="n">{{ number_format($gr['open'],2) }}</td></tr>
@foreach($gr['rows'] as $x)<tr class="det d{{ $i }}"><td>{{ $x['d'] }}</td><td>{{ $x['t'] }}</td><td>{{ $x['ref'] }}</td><td class="n">{{ $x['dr'] ? number_format($x['dr'],2) : '' }}</td><td class="n">{{ $x['cr'] ? number_format($x['cr'],2) : '' }}</td><td class="n">{{ number_format($x['bal'],2) }}</td></tr>@endforeach
<tr class="st det d{{ $i }}"><td colspan="3" class="n">Total</td><td class="n">{{ number_format($gr['dr'],2) }}</td><td class="n">{{ number_format($gr['cr'],2) }}</td><td class="n {{ $gr['close']>0?'l':'p' }}">{{ number_format($gr['close'],2) }}</td></tr>
@empty<tr><td colspan="6" style="padding:14px">No activity for this filter.</td></tr>@endforelse
<tr class="gt"><td colspan="3">Grand Total ({{ count($groups) }} suppliers)</td><td class="n">{{ number_format($g['debit'],2) }}</td><td class="n">{{ number_format($g['credit'],2) }}</td><td class="n">{{ number_format($g['close'],2) }}</td></tr>
<tr class="gt"><td colspan="6">Opening balance: {{ number_format($g['open'],2) }} &nbsp;|&nbsp; Purchases: {{ number_format($g['debit'],2) }} &nbsp;|&nbsp; Payments: <span class="p">{{ number_format($g['credit'],2) }}</span> &nbsp;|&nbsp; Closing due: <span class="l">{{ number_format($g['close'],2) }}</span></td></tr>
</tbody></table></div></div>
@include('report_grid_js')
@endsection
