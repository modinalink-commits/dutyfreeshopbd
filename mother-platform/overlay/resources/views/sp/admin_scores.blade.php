@extends('layout')@section('title','Supplier scores')
@section('body')@include('sp.admin_nav')
<div class="hd"><div><h1>Supplier Score &amp; Performance</h1><div class="sub" style="margin-bottom:0">Internal score from price, delivery, quality, returns, damage, lead time, fulfilment and response speed. Metrics with no data yet are left out of the score.</div></div>
<form method="post" action="/admin/purchase/portal/scores/visible">@csrf<input type="hidden" name="on" value="{{ $show ? 0 : 1 }}"><button class="g">{{ $show ? 'Hide' : 'Show' }} "My Performance" to suppliers</button></form></div>
<div class="card tw"><table><thead><tr><th>Supplier</th><th class="n">Score</th>@foreach(\App\Support\SupplierInsight::LABEL as $l)<th class="n">{{ $l }}</th>@endforeach<th class="n">Total purchase</th></tr></thead><tbody>
@foreach($rows as $x)<tr><td><a href="/admin/purchase/portal/suppliers/{{ $x['s']->id }}"><b>{{ $x['s']->name }}</b></a></td><td class="n">@if($x['score'] !== null)<span class="pb pb-{{ \App\Support\SupplierInsight::band($x['score']) }}">{{ $x['score'] }}/100</span>@else<span class="mut">no data</span>@endif</td>
@foreach(array_keys(\App\Support\SupplierInsight::LABEL) as $k)<td class="n">{{ $x['m'][$k] === null ? '—' : round($x['m'][$k]*100).'%' }}</td>@endforeach<td class="n">{{ number_format($x['purchase']) }}</td></tr>@endforeach</tbody></table></div>
<div class="mut">Weights: price 20%, delivery 20%, quality 15%, returns 10%, lead time 10%, fulfilment 10%, response 10%, damage 5%. Suppliers never see rankings.</div>@endsection
