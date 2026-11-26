@extends('layout')@section('title','Marine Requisitions & Orders')
@section('body')
@php $bt = fn($t) => $t ? \Carbon\Carbon::parse($t)->timezone('Asia/Dhaka')->format('d M, H:i') : '-'; $col = ['requested' => '#B45309', 'quoted' => '#1D4ED8', 'ordered' => '#C2410C', 'preparing' => '#C2410C', 'delivered' => '#166534', 'declined' => '#6B7280', 'cancelled' => '#6B7280']; @endphp
<div style="margin-bottom:10px"><h1 style="margin:0">Requisitions &amp; Orders</h1><div style="font-size:12px;color:#6B7280">Requests from verified marine accounts. The desk prices each one as a USD quotation; an accepted quotation is the purchase order. Times are Bangladesh time.</div></div>
@if(session('ok'))<div class="card" style="background:#DCFCE7;border-color:#A7F3D0;color:#166534">{{ session('ok') }}</div>@endif
@if(!$ready)<div class="card" style="background:#FEF3C7;border-color:#FDE68A;color:#713F12">The order tables are not ready yet. Open this page again when the deploy has finished.</div>
@else
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">@foreach($status as $k => $l)<a href="/admin/marine/orders?status={{ $k }}{{ $kind ? '&kind=' . $kind : '' }}" style="font-size:12px;padding:5px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $st === $k ? '#0C161F' : '#D1D5DB' }};background:{{ $st === $k ? '#0C161F' : '#FFFFFF' }};color:{{ $st === $k ? '#FFFFFF' : '#0C161F' }}">{{ $l }} ({{ $n[$k] ?? 0 }})</a>@endforeach
<a href="/admin/marine/orders?status=all{{ $kind ? '&kind=' . $kind : '' }}" style="font-size:12px;padding:5px 12px;border-radius:999px;text-decoration:none;border:1px solid {{ $st === 'all' ? '#0C161F' : '#D1D5DB' }};background:{{ $st === 'all' ? '#0C161F' : '#FFFFFF' }};color:{{ $st === 'all' ? '#FFFFFF' : '#0C161F' }}">All</a>
<span style="margin-left:auto;display:flex;gap:6px">@foreach(['' => 'Vessel and personal', 'vessel' => 'Vessel supply', 'personal' => 'Crew personal'] as $k => $l)<a class="btn {{ $kind === $k ? '' : 'g' }}" style="padding:4px 10px;font-size:12px" href="/admin/marine/orders?status={{ $st }}{{ $k ? '&kind=' . $k : '' }}">{{ $l }}</a>@endforeach</span></div>
<div class="card" style="padding:0;overflow:auto"><table style="width:100%;min-width:900px;font-size:13px;border-collapse:collapse">
<thead><tr style="text-align:left;color:#6B7280;font-size:11px;text-transform:uppercase"><th style="padding:8px 10px">Number</th><th style="padding:8px 10px">Account</th><th style="padding:8px 10px">Vessel</th><th style="padding:8px 10px">Port / ETA</th><th style="padding:8px 10px">Received</th><th style="padding:8px 10px;text-align:right">Total USD</th><th style="padding:8px 10px">Status</th></tr></thead><tbody>
@forelse($rows as $o)<tr style="border-top:1px solid #E5E7EB"><td style="padding:8px 10px"><a href="/admin/marine/orders/{{ $o->id }}"><b>{{ $o->number }}</b></a><div style="font-size:11.5px;color:#6B7280">{{ $o->kind === 'personal' ? 'Crew personal' : 'Vessel supply' }}</div></td>
<td style="padding:8px 10px">{{ $o->company ?: $o->person }}<div style="font-size:11.5px;color:#6B7280">{{ $o->username }} · {{ rtrim($types[$o->atype] ?? '', 's') }}</div></td>
<td style="padding:8px 10px">{{ $o->vessel ?: '-' }}<div style="font-size:11.5px;color:#6B7280">{{ $o->imo ? 'IMO ' . $o->imo : '' }}</div></td>
<td style="padding:8px 10px">{{ $ports[$o->port] ?? $o->port }}<div style="font-size:11.5px;color:#6B7280">{{ $o->eta ? 'ETA ' . $bt($o->eta) : '' }}</div></td>
<td style="padding:8px 10px;white-space:nowrap">{{ $bt($o->created_at) }}</td><td style="padding:8px 10px;text-align:right;font-variant-numeric:tabular-nums">{{ $o->total !== null ? number_format((float)$o->total, 2) : '-' }}</td>
<td style="padding:8px 10px"><b style="color:{{ $col[$o->status] ?? '#0C161F' }}">{{ $status[$o->status] ?? $o->status }}</b>@if($o->po_ref)<div style="font-size:11.5px;color:#6B7280">PO {{ $o->po_ref }}</div>@endif</td></tr>
@empty<tr><td colspan="7" style="padding:16px 10px;color:#6B7280">{{ $st === 'requested' ? 'No request is waiting for a quotation.' : 'None.' }}</td></tr>@endforelse</tbody></table></div>
<div style="margin-top:10px">{{ $rows->links('pagination.global') }}</div>
<div style="font-size:12px;color:#6B7280;margin-top:8px">No payment, stock movement or accounting entry is made from this page yet.</div>
@endif
@endsection
