@extends('sp.layout')@section('title','My performance')
@section('body')<h1>My Performance</h1><p class="sub">Your own delivery and fulfilment record with DutyFreeBD.</p>
<div class="kpis">@foreach([['On-Time Delivery',$o['on_time'] === null ? '—' : $o['on_time'].'%'],['Order Fulfillment',$o['fulfil'] === null ? '—' : $o['fulfil'].'%'],['Return',$o['return_pct'] === null ? '—' : $o['return_pct'].'%'],['Avg Response Time',$o['resp_hours'] === null ? '—' : $o['resp_hours'].' h']] as [$l,$v])<div class="kpi"><span>{{ $l }}</span><b>{{ $v }}</b></div>@endforeach</div>@endsection
