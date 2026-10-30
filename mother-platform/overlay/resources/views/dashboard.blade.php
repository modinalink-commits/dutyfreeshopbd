@extends('layout')@section('title','Overview')
@section('body')
@php
$ST = ['New'=>['#E6EFF9','#1D5FA8'],'Incomplete'=>['#F3F4F6','#4B5563'],'Pre Order'=>['#ECE8F8','#4B33A0'],'Assign'=>['#E3F1F8','#0F5C80'],'Pending'=>['#FFF1E8','#C2410C'],'AI Order'=>['#F1EAF8','#5B3FA6'],'Approved'=>['#DDF1EE','#0E5F58'],'Scheduled'=>['#E9EEF8','#2A4C8C'],'WFP'=>['#FFF1E8','#9A3412'],'Packaging'=>['#F1EAF8','#5B3FA6'],'Shipment'=>['#E3EEF9','#1D4F94'],'Partial Delivered'=>['#E8F5E9','#2E7D32'],'Delivered'=>['#DCFCE7','#166534'],'Late Delivery'=>['#F8E4E1','#A3281E'],'Return Pending'=>['#F8E4E1','#A3281E'],'Returned'=>['#F3F4F6','#374151'],'Exchange Received'=>['#E6EFF9','#1D5FA8'],'Cancel'=>['#F3F4F6','#6B7280']];
$LV = ['CRITICAL'=>['#F8E4E1','#A3281E'],'WARNING'=>['#FFF1E8','#C2410C'],'INFO'=>['#E6EFF9','#1D5FA8']];
$box = 'background:#FFFFFF;border:1px solid #FDC2A5;border-radius:14px;padding:18px 20px;display:flex;flex-direction:column';
@endphp
<div style="display:flex;flex-direction:column;gap:20px">
<header style="display:flex;flex-wrap:wrap;align-items:center;gap:12px">
  <div style="display:flex;flex-direction:column;gap:2px;flex-grow:1"><h1 style="margin:0;font-size:24px;font-weight:700">Overview</h1>
  <span style="font-size:13px;color:#6B7280">{{ $now->format('l, j F Y') }} · All stores and channels · Asia/Dhaka</span></div>
  <span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#FF5A00;background:#FFF1E8;border-radius:8px;padding:6px 10px">⬇ Download: <a target="_blank" href="/admin/m/r-orders/export/pdf">PDF</a> · <a href="/admin/m/r-orders/export/xls">Excel</a> · <a href="/admin/m/r-orders/export/doc">Word</a> · <a href="/admin/m/r-orders/export/csv">CSV</a></span>
  <form action="/admin/orders" style="display:flex;align-items:center;gap:8px;background:#FFFFFF;border:1px solid #FDC2A5;border-radius:10px;padding:0 12px;height:40px;min-width:260px;margin:0">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    <input name="q" aria-label="Search orders" placeholder="Search order, phone, SKU" style="border:0;outline:0;font:inherit;font-size:14px;flex-grow:1;background:transparent;min-width:0;padding:0;width:auto"></form>
  <a href="/admin/orders?status=New" aria-label="Notifications" style="width:40px;height:40px;border-radius:10px;border:1px solid #FDC2A5;background:#FFFFFF;display:flex;align-items:center;justify-content:center;position:relative">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0B1220" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/></svg>@if(count($alerts))<span style="position:absolute;top:6px;right:7px;width:8px;height:8px;border-radius:50%;background:#FF5A00"></span>@endif</a>
</header>

<section style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px">
@foreach($kpis as [$l,$v,$n,$tone])<div style="background:#FFFFFF;border:1px solid #FDC2A5;border-radius:14px;padding:16px 18px;display:flex;flex-direction:column;gap:6px">
  <span style="font-size:13px;color:#6B7280;font-weight:500">{{ $l }}</span><span style="font-size:28px;font-weight:700;letter-spacing:-0.01em">{{ $v }}</span><span style="font-size:12px;font-weight:600;color:{{ $tone }}">{{ $n }}</span></div>@endforeach
</section>

<section style="display:flex;flex-wrap:wrap;gap:16px">
  <div style="flex:1.3 1 460px;min-width:0;{{ $box }};gap:14px">
    <div style="display:flex;align-items:baseline;gap:8px"><h2 style="margin:0;font-size:16px;font-weight:600;flex-grow:1">Sales today by store</h2><span style="font-size:12px;color:#6B7280">৳ thousand</span></div>
    @forelse($stores as [$name,$val])<div style="display:grid;grid-template-columns:150px 1fr 70px;align-items:center;gap:12px">
      <span style="font-size:13px;font-weight:500">{{ $name }}</span>
      <div style="height:12px;background:#FFE7D6;border-radius:6px;overflow:hidden"><div style="height:12px;border-radius:6px;width:{{ round($val/$max*100) }}%;background:#FF5A00"></div></div>
      <span style="font-size:13px;font-weight:600;text-align:right">{{ number_format($val/1000, 1) }}</span></div>
    @empty<span style="font-size:13px;color:#6B7280">No stores yet.</span>@endforelse
  </div>
  <div style="flex:1 1 380px;min-width:0;{{ $box }};gap:14px">
    <h2 style="margin:0;font-size:16px;font-weight:600">Order pipeline</h2>
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px">
    @foreach(\App\Models\Mother::STAGES as $sg)<a href="/admin/orders?status={{ urlencode($sg) }}" style="border:1px solid #FDC2A5;border-radius:10px;padding:10px 12px;display:flex;flex-direction:column;gap:6px">
      <span style="align-self:flex-start;font-size:11px;font-weight:600;border-radius:5px;padding:2px 7px;background:{{ $ST[$sg][0] }};color:{{ $ST[$sg][1] }}">{{ $sg }}</span><span style="font-size:22px;font-weight:700">{{ $counts[$sg] ?? 0 }}</span></a>@endforeach
    </div>
  </div>
</section>

<section style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start">
  <div style="flex:2 1 640px;min-width:0;{{ $box }};gap:12px">
    <div style="display:flex;align-items:center;gap:10px"><h2 style="margin:0;font-size:16px;font-weight:600;flex-grow:1">Live orders</h2>
      <span style="display:flex;align-items:center;gap:6px;font-size:12px;color:#FF5A00;font-weight:600"><span style="width:8px;height:8px;border-radius:50%;background:#FF5A00"></span>Live</span>
      <a href="/admin/orders" style="font-size:13px;font-weight:600;color:#FF5A00">Open work queue</a></div>
    <div style="overflow-x:auto"><table style="width:100%;min-width:720px;border-collapse:collapse;font-size:13px">
      <thead><tr style="text-align:left;color:#6B7280;font-size:12px"><th style="padding:8px 8px 8px 0">Order</th><th style="padding:8px">Store</th><th style="padding:8px">Customer</th><th style="padding:8px;text-align:right">Total</th><th style="padding:8px">Payment</th><th style="padding:8px">Risk</th><th style="padding:8px">Status</th><th style="padding:8px 0 8px 8px;text-align:right">Age</th></tr></thead>
      <tbody>@forelse($orders as $o)@php $b = $bad[$o->phone] ?? 0; $risk = $b >= 2 ? 'High' : ($b == 1 ? 'Medium' : 'Low'); $rf = ['Low'=>'#FF5A00','Medium'=>'#C2410C','High'=>'#A3281E'][$risk]; $ph = strlen($o->phone) > 6 ? substr($o->phone,0,5).'-••••'.substr($o->phone,-2) : $o->phone; $c = $ST[$o->status] ?? ['#F3F4F6','#374151']; @endphp
      <tr style="border-top:1px solid #FFE7D6"><td style="padding:11px 8px 11px 0;font-weight:600"><a href="/admin/orders/{{ $o->id }}">{{ $o->invoice }}</a></td><td style="padding:11px 8px">{{ $o->store ?? '-' }}</td><td style="padding:11px 8px">{{ $ph }}</td><td style="padding:11px 8px;text-align:right;font-weight:600">৳{{ number_format($o->total) }}</td><td style="padding:11px 8px">{{ $o->payment_method ?: 'COD' }}</td><td style="padding:11px 8px;font-weight:600;color:{{ $rf }}">{{ $risk }}</td>
      <td style="padding:11px 8px"><span style="font-size:11px;font-weight:600;border-radius:5px;padding:2px 7px;background:{{ $c[0] }};color:{{ $c[1] }}">{{ $o->status }}</span></td><td style="padding:11px 0 11px 8px;text-align:right;color:#6B7280">{{ \Carbon\Carbon::parse($o->created_at)->diffForHumans(null, true, true) }}</td></tr>
      @empty<tr><td colspan="8" style="padding:14px 0;color:#6B7280">No orders yet. <a style="color:#FF5A00;font-weight:600" href="/admin/orders/new">Create order</a></td></tr>@endforelse</tbody></table></div>
  </div>
  <div style="flex:1 1 300px;min-width:0;display:flex;flex-direction:column;gap:16px">
    <div style="{{ $box }};gap:10px"><h2 style="margin:0;font-size:16px;font-weight:600">Needs attention</h2>
      @forelse($alerts as [$lvl,$tt,$sub])<div style="display:flex;gap:10px;align-items:flex-start;border-top:1px solid #FFE7D6;padding-top:10px"><span style="font-size:10px;font-weight:700;border-radius:5px;padding:2px 6px;background:{{ $LV[$lvl][0] }};color:{{ $LV[$lvl][1] }}">{{ $lvl }}</span><div style="display:flex;flex-direction:column;gap:2px"><span style="font-size:13px;font-weight:600">{{ $tt }}</span><span style="font-size:12px;color:#6B7280">{{ $sub }}</span></div></div>
      @empty<span style="font-size:13px;color:#166534">All clear right now.</span>@endforelse</div>
    <div style="{{ $box }};gap:10px"><h2 style="margin:0;font-size:16px;font-weight:600">System health</h2>
      @foreach($health as [$k,$v])<div style="display:flex;align-items:center;gap:8px;border-top:1px solid #FFE7D6;padding-top:9px;font-size:13px"><span style="flex-grow:1;color:#6B7280">{{ $k }}</span><span style="font-weight:600">{{ $v }}</span><span style="font-size:10px;font-weight:700;color:#166534;background:#DCFCE7;border-radius:5px;padding:2px 6px">OK</span></div>@endforeach</div>
  </div>
</section>
</div>
@endsection
