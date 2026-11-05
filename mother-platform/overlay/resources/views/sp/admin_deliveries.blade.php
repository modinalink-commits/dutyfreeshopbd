@extends('layout')@section('title','Supplier deliveries')
@section('body')@include('sp.admin_nav')
<h1>Deliveries &amp; Goods Receiving</h1><div class="sub">Supplier-reported deliveries. Open one to record what actually arrived.</div>
<div class="card tw"><table><thead><tr><th>PO</th><th>Supplier</th><th>Status</th><th>Dispatch</th><th>Vehicle</th><th>Challan</th><th>Expected</th></tr></thead><tbody>
@forelse($rows as $d)<tr><td><a href="/admin/purchase/portal/deliveries/{{ $d->id }}"><b>{{ $d->po_no }}</b></a></td><td>{{ $d->sname }}</td><td><span class="pb pb-{{ $d->status==='delivered' ? 'received' : ($d->status==='dispatched' ? 'pending' : 'draft') }}">{{ $d->status }}</span></td><td>{{ $d->dispatch_date ?: '—' }}</td><td>{{ $d->vehicle ?: '—' }}</td><td>{{ $d->challan_no ?: '—' }}</td><td>{{ $d->expected_arrival ?: '—' }}</td></tr>@empty<tr><td colspan="7" class="mut">No deliveries yet</td></tr>@endforelse</tbody></table></div>@endsection
