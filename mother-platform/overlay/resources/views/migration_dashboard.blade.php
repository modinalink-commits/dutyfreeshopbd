@extends('layout')@section('title','Migration check')
@section('body')
<div style="margin-bottom:14px"><h1 style="margin:0">Migration check</h1><span style="font-size:13px;color:#6B7280">Old site (staged copy) against the live tables. "OK" means the numbers match; a difference is listed so it can be explained before go-live. Where the old site has no staged copy (suppliers, purchases, accounts, reviews) the old column shows a dash; compare those with the old admin totals by hand.</span></div>
@php $g = collect($rows)->groupBy('g'); @endphp
@foreach($g as $name => $list)
<div class="card" style="overflow-x:auto"><b>{{ $name }}</b><table><thead><tr><th>Check</th><th style="text-align:right">Old site</th><th style="text-align:right">New system</th><th style="text-align:right">Difference</th></tr></thead><tbody>
@foreach($list as $r)<tr><td>{{ $r['l'] }}</td><td style="text-align:right">{{ $r['old'] }}</td><td style="text-align:right">{{ $r['new'] }}</td><td style="text-align:right;font-weight:600;color:{{ $r['diff']==='—' ? '#6B7280' : ($r['ok'] ? '#166534' : '#B91C1C') }}">{{ $r['diff'] }}</td></tr>@endforeach
</tbody></table></div>
@endforeach
@if(!empty($sup))<div class="card" style="overflow-x:auto"><b>Supplier due, supplier by supplier (new system)</b><table><thead><tr><th>Supplier</th><th style="text-align:right">Purchases</th><th style="text-align:right">Paid</th><th style="text-align:right">Due</th></tr></thead><tbody>@foreach($sup as $r)<tr><td>{{ $r->name }}</td><td style="text-align:right">৳{{ number_format($r->bought) }}</td><td style="text-align:right">৳{{ number_format($r->paid) }}</td><td style="text-align:right;font-weight:600">৳{{ number_format($r->due) }}</td></tr>@endforeach</tbody></table></div>@endif
<div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr))">
<div class="card" style="overflow-x:auto"><b>Old orders by status (staged)</b><table><thead><tr><th>Status</th><th style="text-align:right">Orders</th><th style="text-align:right">Value</th></tr></thead><tbody>@foreach($stat as $s)<tr><td>{{ $s->s }}</td><td style="text-align:right">{{ number_format($s->n) }}</td><td style="text-align:right">৳{{ number_format($s->t) }}</td></tr>@endforeach</tbody></table></div>
<div class="card" style="overflow-x:auto"><b>Old orders by status (live)</b><table><thead><tr><th>Status</th><th style="text-align:right">Orders</th><th style="text-align:right">Value</th></tr></thead><tbody>@foreach($live as $s)<tr><td>{{ $s->s }}</td><td style="text-align:right">{{ number_format($s->n) }}</td><td style="text-align:right">৳{{ number_format($s->t) }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
