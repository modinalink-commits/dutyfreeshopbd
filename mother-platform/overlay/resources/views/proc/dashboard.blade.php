@extends('layout')@section('title','Purchase Dashboard')
@section('body')
<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:12px"><div style="flex:1;min-width:240px"><h1 style="margin:0">Purchase, Suppliers &amp; Damage</h1><span style="font-size:13px;color:#6B7280">Supplier → Purchase → Invoice → Stock → Payment → Due → SMS → Damage / Return → Ledger</span></div><a class="btn" href="/admin/purchase/new">+ New Purchase</a> <a class="btn g" href="/admin/purchase/payments/new">Make Payment</a></div>
@include('proc.nav')
@php $t = fn($v) => '৳'.number_format($v); @endphp
<div class="sk">
<div class="card"><span>Total purchase</span><b>{{ $t($k['total']) }}</b></div><div class="card"><span>Today's purchase</span><b>{{ $t($k['today']) }}</b></div><div class="card"><span>This month</span><b>{{ $t($k['month']) }}</b></div>
<div class="card"><span>Total supplier due</span><b style="color:#C2410C">{{ $t($k['due']) }}</b></div><div class="card"><span>Total paid</span><b>{{ $t($k['paid']) }}</b></div><div class="card"><span>Overdue amount</span><b style="color:#991B1B">{{ $t($k['over']) }}</b></div>
<div class="card"><span>Active suppliers</span><b>{{ $k['active'] }}</b></div><div class="card"><span>Damage value</span><b>{{ $t($k['damage']) }}</b></div><div class="card"><span>Purchase return value</span><b>{{ $t($k['returns']) }}</b></div>
<div class="card"><span>Pending payment</span><b>{{ $t($k['pendpay']) }}</b></div><div class="card"><span>Pending approval</span><b>{{ $k['pendappr'] }}</b></div></div>
@php $bars = function($rows,$col='var(--o)'){ $m = max(1, max(array_column($rows,1) ?: [1])); foreach($rows as [$l,$v]) echo '<div class="bar"><span title="'.e($l).'">'.e($l).'</span><i style="width:'.max(1,round($v/$m*100)).'%;max-width:calc(100% - 150px);background:'.$col.'"></i><b>'.number_format($v).'</b></div>'; }; @endphp
<div class="cols"><div class="card"><b>Monthly purchase</b><div style="margin-top:8px">{!! $bars($ch['monthly']) !!}</div></div>
<div class="card"><b>Supplier-wise purchase</b><div style="margin-top:8px">{!! count($ch['supplier']) ? $bars($ch['supplier'],'#4F46E5') : '<span class="mut">No purchases yet.</span>' !!}</div></div>
<div class="card"><b>Paid vs Due</b><div style="margin-top:8px">{!! $bars($ch['pd'],'#16A34A') !!}</div></div>
<div class="card"><b>Damage trend</b><div style="margin-top:8px">{!! $bars($ch['damage'],'#DC2626') !!}</div></div></div>
<div class="card tw" style="margin-top:14px"><b>Recent purchases</b> <a href="/admin/purchase/invoices" style="font-size:12px">all</a><table><thead><tr><th>Invoice</th><th>Supplier</th><th>Date</th><th>Status</th><th class="n">Total</th></tr></thead><tbody>
@forelse($recent as $r)<tr><td><a href="/admin/purchase/invoices/{{ $r->id }}">{{ $r->invoice }}</a></td><td>{{ $r->sname }}</td><td>{{ $r->purchase_date }}</td><td><span class="pb pb-{{ $r->status }}">{{ $r->status }}</span></td><td class="n">{{ number_format($r->total,2) }}</td></tr>@empty<tr><td colspan="5" class="mut">No purchases yet — start with “New Purchase”.</td></tr>@endforelse</tbody></table></div>
@endsection
