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
  <div style="display:inline-flex;background:#fff;border:1px solid #FDC2A5;border-radius:10px;padding:3px;gap:2px">@foreach(['today'=>'Today','7'=>'7 days','30'=>'30 days'] as $rk=>$rl)<a href="/admin?range={{ $rk }}" style="padding:7px 13px;border-radius:8px;font-size:13px;font-weight:600;{{ $range===(string)$rk ? 'background:#FF5A00;color:#fff' : 'color:#0B1220' }}">{{ $rl }}</a>@endforeach</div>
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

@php
$mx = max(1, collect($trend)->max('sales')); $mo = max(1, collect($trend)->max('orders')); $nT = count($trend); $W = 640; $H = 190; $L = 8; $B = 26; $T = 12; $cw = ($W - $L*2) / max(1,$nT); $bw = max(4, min(34, $cw*0.62));
$pts = []; foreach ($trend as $i => $t) { $pts[] = round($L + $cw*$i + $cw/2, 1).','.round($T + ($H-$B-$T) * (1 - $t['orders']/$mo), 1); }
@endphp
<section style="display:flex;flex-wrap:wrap;gap:16px;align-items:stretch">
  <div style="flex:2 1 520px;min-width:0;{{ $box }};gap:10px">
    <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap"><h2 style="margin:0;font-size:16px;font-weight:600;flex-grow:1">Sales &amp; orders, last {{ $nT }} days</h2>
      <span style="font-size:12px;color:#6B7280;display:flex;gap:12px"><span><i style="display:inline-block;width:10px;height:10px;background:#FF5A00;border-radius:2px;margin-right:5px"></i>Sales (৳)</span><span><i style="display:inline-block;width:14px;height:3px;background:#0B1220;border-radius:2px;margin-right:5px;vertical-align:middle"></i>Orders</span></span></div>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" style="display:block;max-height:240px" role="img" aria-label="Sales and orders per day">
      @foreach([0,.5,1] as $g)<line x1="{{ $L }}" x2="{{ $W-$L }}" y1="{{ round($T + ($H-$B-$T)*(1-$g),1) }}" y2="{{ round($T + ($H-$B-$T)*(1-$g),1) }}" stroke="#FFE7D6" stroke-width="1"/>@endforeach
      @foreach($trend as $i => $t)@php $bh = ($H-$B-$T) * $t['sales']/$mx; $x = $L + $cw*$i + ($cw-$bw)/2; @endphp
      <rect x="{{ round($x,1) }}" y="{{ round($H-$B-$bh,1) }}" width="{{ round($bw,1) }}" height="{{ round(max($bh, $t['sales']>0?2:0),1) }}" rx="3" fill="#FF5A00" opacity=".9"><title>{{ $t['d'] }}: ৳{{ number_format($t['sales']) }} · {{ $t['orders'] }} orders · {{ $t['delivered'] }} delivered</title></rect>
      @if($nT <= 10 || $i % ceil($nT/8) === 0 || $i === $nT-1)<text x="{{ round($L + $cw*$i + $cw/2,1) }}" y="{{ $H-8 }}" text-anchor="middle" font-size="10" fill="#6B7280">{{ $t['d'] }}</text>@endif
      @endforeach
      <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="#0B1220" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
      @foreach($trend as $i => $t)<circle cx="{{ round($L + $cw*$i + $cw/2,1) }}" cy="{{ round($T + ($H-$B-$T) * (1 - $t['orders']/$mo),1) }}" r="3" fill="#fff" stroke="#0B1220" stroke-width="1.6"><title>{{ $t['d'] }}: {{ $t['orders'] }} orders</title></circle>@endforeach
    </svg>
    <span style="font-size:12px;color:#6B7280">Peak day ৳{{ number_format($mx > 1 ? $mx : 0) }} · Same numbers as Reports &amp; Analytics. <a href="/admin/analytics" style="color:#FF5A00;font-weight:600">Open full analytics</a></span>
  </div>
  <div style="flex:1 1 280px;min-width:0;display:flex;flex-direction:column;gap:16px">
    <div style="{{ $box }};gap:10px"><h2 style="margin:0;font-size:16px;font-weight:600">Quick actions</h2>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <a href="/admin/orders/new" style="background:#FF5A00;color:#fff;border-radius:10px;padding:11px;text-align:center;font-weight:600;font-size:13px">＋ Create order</a>
        <a href="/admin/products/new" style="border:1px solid #FDC2A5;border-radius:10px;padding:11px;text-align:center;font-weight:600;font-size:13px">＋ Add product</a>
        <a href="/admin/chat" style="border:1px solid #FDC2A5;border-radius:10px;padding:11px;text-align:center;font-weight:600;font-size:13px;position:relative">💬 Messages@if($chatUnread)<em style="font-style:normal;background:#E11D48;color:#fff;border-radius:20px;font-size:11px;padding:0 7px;margin-left:5px">{{ $chatUnread }}</em>@endif</a>
        <a href="/admin/analytics" style="border:1px solid #FDC2A5;border-radius:10px;padding:11px;text-align:center;font-weight:600;font-size:13px">📊 Reports</a></div></div>
    <div style="{{ $box }};gap:10px;flex:1"><h2 style="margin:0;font-size:16px;font-weight:600">Courier performance</h2>
      @forelse($couriers as [$cn,$cp,$cs])<div style="display:grid;grid-template-columns:90px 1fr 56px;gap:8px;align-items:center;font-size:13px"><span style="font-weight:500">{{ $cn }}</span><div style="height:10px;background:#FFE7D6;border-radius:5px;overflow:hidden"><div style="height:10px;width:{{ min(100,$cs) }}%;background:{{ $cs>=80 ? '#16A34A' : ($cs>=60 ? '#F59E0B' : '#DC2626') }};border-radius:5px"></div></div><span style="text-align:right;font-weight:600">{{ $cs }}%</span></div><span style="font-size:11px;color:#6B7280;margin-top:-6px">{{ $cp }} parcels · delivery success</span>
      @empty<span style="font-size:13px;color:#6B7280">No courier data for this period.</span>@endforelse</div>
  </div>
</section>

<section style="display:flex;flex-wrap:wrap;gap:16px;align-items:stretch">
  <div style="flex:1 1 340px;min-width:0;{{ $box }};gap:12px"><div style="display:flex;align-items:baseline"><h2 style="margin:0;font-size:16px;font-weight:600;flex-grow:1">Top products</h2><a href="/admin/analytics/product-sales" style="font-size:12px;font-weight:600;color:#FF5A00">All</a></div>
    @php $tp = max(1, $topProducts->max(2) ?? 1); @endphp
    @forelse($topProducts as [$pn,$pq,$pg])<div style="display:flex;flex-direction:column;gap:4px"><div style="display:flex;gap:8px;font-size:13px"><span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $pn }}</span><b>৳{{ number_format($pg) }}</b></div><div style="height:8px;background:#FFE7D6;border-radius:4px"><div style="height:8px;border-radius:4px;background:#FF5A00;width:{{ round($pg/$tp*100) }}%"></div></div><span style="font-size:11px;color:#6B7280">{{ $pq }} sold</span></div>
    @empty<span style="font-size:13px;color:#6B7280">No sales in this period yet.</span>@endforelse</div>
  <div style="flex:1 1 340px;min-width:0;{{ $box }};gap:12px"><div style="display:flex;align-items:baseline"><h2 style="margin:0;font-size:16px;font-weight:600;flex-grow:1">Sales by category</h2><a href="/admin/analytics/category-sales" style="font-size:12px;font-weight:600;color:#FF5A00">All</a></div>
    @php $ct = max(1, $cats->sum(1)); @endphp
    @forelse($cats as $ci => [$cl,$cv])<div style="display:grid;grid-template-columns:110px 1fr 44px;gap:8px;align-items:center;font-size:13px"><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $cl }}</span><div style="height:10px;background:#FFE7D6;border-radius:5px"><div style="height:10px;border-radius:5px;width:{{ round($cv/$ct*100) }}%;background:{{ ['#FF5A00','#0B1220','#F59E0B','#16A34A','#6366F1','#9CA3AF'][$ci % 6] }}"></div></div><b style="text-align:right">{{ round($cv/$ct*100) }}%</b></div>
    @empty<span style="font-size:13px;color:#6B7280">No category sales in this period yet.</span>@endforelse</div>
</section>

<section style="display:flex;flex-wrap:wrap;gap:16px">
  <div style="flex:1.3 1 460px;min-width:0;{{ $box }};gap:14px">
    <div style="display:flex;align-items:baseline;gap:8px"><h2 style="margin:0;font-size:16px;font-weight:600;flex-grow:1">Sales {{ $label }} by store</h2><span style="font-size:12px;color:#6B7280">৳ thousand</span></div>
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
