@extends('layout')@section('title','Dashboard')
@section('body')<h1>Dashboard</h1>
<div class="grid"><div class="card k"><b>{{ $today }}</b><span>Orders today</span></div><div class="card k"><b>৳{{ number_format($sales) }}</b><span>Sales today (not cancelled)</span></div><div class="card k"><b>{{ $counts->sum() }}</b><span>All orders</span></div></div>
<div class="card"><h3 style="margin-top:0">Orders by stage</h3><div class="chips">
@foreach(\App\Models\Mother::STAGES as $s)<a class="chip {{ in_array($s,\App\Models\Mother::RED)?'red':'' }}" href="/admin/orders?status={{ urlencode($s) }}">{{ $s }} · {{ $counts[$s] ?? 0 }}</a>@endforeach</div></div>
<a class="btn" href="/admin/orders/new">Create order</a>@endsection
