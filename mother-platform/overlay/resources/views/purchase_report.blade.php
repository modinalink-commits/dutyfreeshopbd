@extends('layout')@section('title','Purchase Report')
@section('body')
<style>
.pl{width:100%;border-collapse:collapse;font-size:13px}.pl th,.pl td{border:1px solid #dfe3e8;padding:0 8px;height:34px}
.pl thead th{position:sticky;top:0;background:#E9EEF5;color:#1E2A3B;z-index:2;height:36px}.pl .n{text-align:right;font-variant-numeric:tabular-nums}
.pl .gh td{background:#EEF3F9;font-weight:600;cursor:pointer}.pl .st td{background:#F3F4F6;font-weight:700}.pl .gt td{background:#FFF1E8;font-weight:700;height:38px}
.pl .p{color:#15803D}.pl .l{color:#B91C1C}.pl .gh:hover td{background:#FFF1E8}.pl .nc{color:#B45309}
.hide{display:none}.bar{display:flex;flex-wrap:wrap;gap:8px;align-items:end;margin-bottom:12px}.bar label{font-size:12px;color:#6B7280;display:block}
@media print{.bar,.noprint,aside,nav,.sidebar,header{display:none!important}.pl thead th{position:static}body{background:#fff}.hide{display:table-row!important}}
</style>
<div class="noprint" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:8px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Purchase Report</h1><span style="font-size:13px;color:#6B7280">Supplier purchase invoices with item lines, invoice totals, paid and due.</span></div>
<a class="btn g" href="?from={{ $from }}&to={{ $to }}&supplier={{ $sup }}&q={{ urlencode($q) }}&export=csv">Export Excel (CSV)</a><button class="g" onclick="window.print()">Print / PDF</button></div>
<form class="bar" method="get"><div><label>From</label><input type="date" name="from" value="{{ $from }}"></div><div><label>To</label><input type="date" name="to" value="{{ $to }}"></div>
<div><label>Supplier</label><select name="supplier"><option value="0">All suppliers</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" {{ $sup==$s->id?'selected':'' }}>{{ $s->name }}</option>@endforeach</select></div>
<div><label>Search invoice, supplier, product</label><input name="q" value="{{ $q }}" placeholder="search" style="min-width:220px"></div>
<div><label>Invoices</label><select name="per">@foreach([50,100,200,500] as $n)<option {{ $per==$n?'selected':'' }}>{{ $n }}</option>@endforeach</select></div><div><button>Search</button> <a class="btn g" href="/admin/purchase-report">Reset</a></div></form>
<div class="noprint" style="font-size:12px;color:#6B7280;margin-bottom:6px">Showing {{ count($groups) }} of {{ number_format($total) }} invoices &middot; {{ $from }} &rarr; {{ $to }} &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.add('hide'));return false">collapse all</a> &middot; <a href="#" onclick="document.querySelectorAll('.det').forEach(e=>e.classList.remove('hide'));return false">expand all</a></div>
<div style="overflow:auto;max-height:78vh"><table class="pl"><thead><tr><th>Product ID</th><th>Product</th><th class="n">Qty</th><th class="n">Purchase rate</th><th class="n">Line total</th></tr></thead><tbody>
@forelse($groups as $i => $gr)@php($p=$gr['p'])
<tr class="gh" onclick="document.querySelectorAll('.d{{ $i }}').forEach(e=>e.classList.toggle('hide'))"><td colspan="5">&#9660; Invoice: {{ $p->invoice }} &nbsp;|&nbsp; {{ $p->purchase_date }} &nbsp;|&nbsp; Supplier: {{ $p->supplier ?: '—' }} &nbsp;|&nbsp; Paid: {{ number_format($p->paid,2) }} &nbsp;|&nbsp; Due: <span class="{{ $gr['due']>0?'l':'p' }}">{{ number_format($gr['due'],2) }}</span></td></tr>
@foreach($gr['rows'] as $x)<tr class="det d{{ $i }}"><td>{{ $x['pid'] }}</td><td>{{ $x['name'] }}</td><td class="n">{{ $x['qty'] }}</td><td class="n">{{ number_format($x['cost'],2) }}</td><td class="n">{{ number_format($x['sum'],2) }}</td></tr>@endforeach
<tr class="st det d{{ $i }}"><td colspan="4" class="n">Invoice total @if(!count($gr['rows']))(no item lines recorded)@endif</td><td class="n">{{ number_format($p->total,2) }}</td></tr>
@empty<tr><td colspan="5" style="padding:14px">No purchase invoices for this filter.</td></tr>@endforelse
<tr class="gt"><td colspan="4">Grand Total ({{ number_format($total) }} invoices, all matching the filter)</td><td class="n">{{ number_format($g['total'],2) }}</td></tr>
<tr class="gt"><td colspan="5">Total purchase: {{ number_format($g['total'],2) }} &nbsp;|&nbsp; Total paid: <span class="p">{{ number_format($g['paid'],2) }}</span> &nbsp;|&nbsp; Total due: <span class="l">{{ number_format($g['due'],2) }}</span></td></tr>
</tbody></table></div>
@endsection
