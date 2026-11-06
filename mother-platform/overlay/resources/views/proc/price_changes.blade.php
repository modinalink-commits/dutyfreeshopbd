@extends('layout')@section('title','Price change report')
@section('body')
<h1 style="margin:0 0 10px">Product Price Changes (from Purchase)</h1>@include('proc.nav')
<form class="card" method="get"><div class="fg"><div><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div><div><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div><div><label>Product / Purchase</label><input name="q" value="{{ request('q') }}"></div></div><div style="margin-top:8px"><button>Apply</button></div></form>
<div class="card tw"><table><thead><tr><th>Date</th><th>Purchase</th><th>Product</th><th class="n">Purchase Rate</th><th class="n">Old Rate</th><th class="n">New Rate</th><th class="n">Difference</th><th class="n">Margin Before</th><th class="n">Margin After</th><th>Changed By</th><th>Note</th></tr></thead><tbody>
@forelse($rows as $r)@php $c=(float)$r->purchase_rate; $d=(float)$r->new_rate-(float)$r->old_rate; $t=\Carbon\Carbon::parse($r->created_at)->timezone('Asia/Dhaka'); @endphp
<tr><td>{{ $t->format('d M Y H:i') }}</td><td>{{ $r->purchase_invoice }}</td><td>{{ $r->pname }}</td><td class="n">{{ number_format($c,2) }}</td><td class="n">{{ number_format((float)$r->old_rate,2) }}</td><td class="n">{{ number_format((float)$r->new_rate,2) }}</td><td class="n" style="color:{{ $d>=0 ? '#15803D' : '#B45309' }}">{{ $d>=0 ? '↑' : '↓' }} {{ number_format(abs($d),2) }}</td><td class="n">{{ $c>0 ? number_format(((float)$r->old_rate-$c)/$c*100,2).'%' : '—' }}</td><td class="n">{{ $c>0 ? number_format(((float)$r->new_rate-$c)/$c*100,2).'%' : '—' }}</td><td>{{ $r->changed_by }}</td><td>{{ $r->note }}</td></tr>
@empty<tr><td colspan="11" class="mut">No price changes yet.</td></tr>@endforelse</tbody></table></div>
{{ $rows->links() }}
@endsection
