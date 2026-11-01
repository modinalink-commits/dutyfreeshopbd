@extends('layout')@section('title','Overview')
@section('body')
@php
$ST = ['New'=>['#E6EFF9','#1D5FA8'],'Incomplete'=>['#F3F4F6','#4B5563'],'Pre Order'=>['#ECE8F8','#4B33A0'],'Assign'=>['#E3F1F8','#0F5C80'],'Pending'=>['#FFF1E8','#C2410C'],'AI Order'=>['#F1EAF8','#5B3FA6'],'Approved'=>['#DDF1EE','#0E5F58'],'Scheduled'=>['#E9EEF8','#2A4C8C'],'WFP'=>['#FFF1E8','#9A3412'],'Packaging'=>['#F1EAF8','#5B3FA6'],'Shipment'=>['#E3EEF9','#1D4F94'],'Partial Delivered'=>['#E8F5E9','#2E7D32'],'Delivered'=>['#DCFCE7','#166534'],'Late Delivery'=>['#F8E4E1','#A3281E'],'Return Pending'=>['#F8E4E1','#A3281E'],'Returned'=>['#F3F4F6','#374151'],'Exchange Received'=>['#E6EFF9','#1D5FA8'],'Cancel'=>['#F3F4F6','#6B7280']];
$LV = ['CRITICAL'=>['#F8E4E1','#A3281E'],'WARNING'=>['#FFF1E8','#C2410C'],'INFO'=>['#E6EFF9','#1D5FA8']];
$IC = ['sales'=>'<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>','cart'=>'<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6"/>','tag'=>'<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/>','coin'=>'<circle cx="12" cy="12" r="9"/><path d="M14.8 9.2c-.6-.8-1.6-1.2-2.8-1.2-1.7 0-2.8.8-2.8 2s1 1.7 2.8 2 2.8.8 2.8 2-1.1 2-2.8 2c-1.2 0-2.3-.4-2.9-1.3M12 6v2m0 8v2"/>','truck'=>'<path d="M1 3h13v13H1zM14 8h4l4 4v4h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>','undo'=>'<path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-15-6.7L3 13"/>','clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>','box'=>'<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8"/>','plus'=>'<path d="M12 5v14M5 12h14"/>','list'=>'<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>','stock'=>'<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/>','chat'=>'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>','chart'=>'<path d="M4 20V10M10 20V4M16 20v-8M22 20H2"/>','users'=>'<circle cx="9" cy="8" r="4"/><path d="M1 21a8 8 0 0 1 16 0M17 4a4 4 0 0 1 0 8M23 21a8 8 0 0 0-5-7.4"/>','phone'=>'<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>','check'=>'<path d="M20 6 9 17l-5-5"/>','alert'=>'<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>','bell'=>'<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4"/>','dl'=>'<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>','refresh'=>'<path d="M21 12a9 9 0 0 1-15.5 6.2L3 16M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M3 21v-5h5"/>','search'=>'<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>','edit'=>'<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>','up'=>'<path d="m6 15 6-6 6 6"/>','down'=>'<path d="m6 9 6 6 6-6"/>','flat'=>'<path d="M5 12h14"/>','target'=>'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>','shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>','pulse'=>'<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>','mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'];
$ico = fn($n, $s = 18) => '<svg viewBox="0 0 24 24" width="'.$s.'" height="'.$s.'" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($IC[$n] ?? '').'</svg>';
$tone = ['orange'=>['#FFF1E8','#FF5A00'],'blue'=>['#E6EFF9','#1D5FA8'],'violet'=>['#F1EAF8','#5B3FA6'],'green'=>['#DCFCE7','#166534'],'teal'=>['#DDF1EE','#0E5F58'],'red'=>['#FEE2E2','#B91C1C'],'amber'=>['#FEF3C7','#B45309'],'slate'=>['#EEF2F6','#475569']];
$q = http_build_query(array_filter(['range'=>$range, 'store'=>$storeId ?: null, 'from'=>request('from'), 'to'=>request('to')]));
$nT = count($trend);
@endphp
<style>
.dx{display:flex;flex-direction:column;gap:18px}
.dx a{text-decoration:none;color:inherit}
.dx .card{background:#fff;border:1px solid #FDC2A5;border-radius:16px;padding:18px 20px;box-shadow:0 1px 2px rgba(11,18,32,.04),0 8px 24px -14px rgba(11,18,32,.12);min-width:0}
.dx h2{margin:0;font-size:15px;font-weight:700;letter-spacing:-.005em}
.dx .head{display:flex;align-items:center;gap:10px;margin-bottom:12px}.dx .head h2{flex:1}
.dx .more{font-size:12px;font-weight:700;color:#FF5A00}
.dx .muted{color:#6B7280;font-size:12px}
.dx-top{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.dx-top .ttl{flex:1 1 220px}.dx-top h1{margin:0;font-size:24px;font-weight:800;letter-spacing:-.02em}
.dx-pills{display:inline-flex;background:#fff;border:1px solid #FDC2A5;border-radius:12px;padding:3px;gap:2px}
.dx-pills a{padding:7px 12px;border-radius:9px;font-size:13px;font-weight:700;color:#0B1220}.dx-pills a.on{background:#FF5A00;color:#fff;box-shadow:0 4px 10px -4px rgba(255,90,0,.6)}
.dx select,.dx input[type=date]{width:auto;height:40px;border:1px solid #FDC2A5;border-radius:12px;background:#fff;padding:0 10px;font-size:13px;font-weight:600}
.dx-ibtn{width:40px;height:40px;border-radius:12px;border:1px solid #FDC2A5;background:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;position:relative;color:#0B1220;padding:0}
.dx-ibtn:hover{background:#FFF1E8}.dx-dot{position:absolute;top:7px;right:8px;width:9px;height:9px;border-radius:50%;background:#FF5A00;border:2px solid #fff}
.dx-search{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #FDC2A5;border-radius:12px;padding:0 12px;height:40px;min-width:220px;margin:0;color:#6B7280}
.dx-search input{border:0;outline:0;font:inherit;font-size:14px;flex:1;background:transparent;min-width:0;padding:0;width:auto;height:auto}
.dx-menu{position:relative}.dx-menu summary{list-style:none;cursor:pointer}.dx-menu summary::-webkit-details-marker{display:none}
.dx-menu .pop{position:absolute;right:0;top:46px;background:#fff;border:1px solid #FDC2A5;border-radius:12px;box-shadow:0 18px 40px -14px rgba(11,18,32,.25);padding:6px;z-index:30;min-width:170px}
.dx-menu .pop a{display:block;padding:9px 12px;border-radius:8px;font-size:13px;font-weight:600}.dx-menu .pop a:hover{background:#FFF1E8;color:#FF5A00}
.dx-live{display:inline-flex;align-items:center;gap:8px;font-size:12px;color:#6B7280;font-weight:600}
.dx-live i{width:8px;height:8px;border-radius:50%;background:#16A34A;box-shadow:0 0 0 0 rgba(22,163,74,.6);animation:dxp 2s infinite}.dx-live.off i{background:#9CA3AF;animation:none}
@keyframes dxp{70%{box-shadow:0 0 0 7px rgba(22,163,74,0)}100%{box-shadow:0 0 0 0 rgba(22,163,74,0)}}
.dx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.dx-k{display:flex;flex-direction:column;gap:8px;position:relative;overflow:hidden;transition:transform .15s,box-shadow .15s}
.dx-k:hover{transform:translateY(-2px);box-shadow:0 14px 30px -14px rgba(11,18,32,.25)}
.dx-k .r1{display:flex;align-items:center;gap:10px}.dx-k .chip{width:36px;height:36px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex:none}
.dx-k .lb{font-size:13px;color:#6B7280;font-weight:600;flex:1}.dx-k .v{font-size:28px;font-weight:800;letter-spacing:-.02em;line-height:1.1}
.dx-k .sub{font-size:12px;color:#6B7280}
.dx-tr{display:inline-flex;align-items:center;gap:3px;font-size:12px;font-weight:700;border-radius:999px;padding:2px 8px;align-self:flex-start}
.dx-tr.good{background:#DCFCE7;color:#166534}.dx-tr.bad{background:#FEE2E2;color:#B91C1C}.dx-tr.flat{background:#F3F4F6;color:#4B5563}
.dx-k svg.sp{position:absolute;right:0;bottom:0;opacity:.9}
.dx-2{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:14px}
.dx-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.dx-22{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:14px}
.dx-tabs{display:inline-flex;background:#FFF7F2;border-radius:10px;padding:3px;gap:2px}
.dx-tabs button{border:0;background:transparent;padding:6px 11px;border-radius:8px;font-size:12px;font-weight:700;color:#6B7280;cursor:pointer;width:auto;height:auto}
.dx-tabs button.on{background:#fff;color:#FF5A00;box-shadow:0 1px 3px rgba(0,0,0,.1)}
.dx-chart{position:relative}.dx-chart svg{display:block;width:100%;height:auto;overflow:visible}
.dx-tip{position:absolute;pointer-events:none;background:#0B1220;color:#fff;border-radius:10px;padding:8px 11px;font-size:12px;line-height:1.5;box-shadow:0 12px 28px -8px rgba(0,0,0,.4);opacity:0;transition:opacity .1s;white-space:nowrap;z-index:5;transform:translate(-50%,-100%)}
.dx-qa{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}
.dx-qa a{display:flex;align-items:center;gap:10px;border:1px solid #FFE0CC;background:#FFFAF6;border-radius:13px;padding:11px 12px;font-size:13px;font-weight:700;transition:all .15s;position:relative}
.dx-qa a:hover{background:#FFF1E8;border-color:#FF5A00;transform:translateY(-1px)}
.dx-qa a.pri{background:linear-gradient(135deg,#FF7A2E,#FF5A00);color:#fff;border-color:#FF5A00;box-shadow:0 8px 18px -8px rgba(255,90,0,.7)}
.dx-qa .qi{width:30px;height:30px;border-radius:9px;background:#fff;color:#FF5A00;display:flex;align-items:center;justify-content:center;flex:none;box-shadow:0 1px 2px rgba(0,0,0,.08)}
.dx-qa a.pri .qi{background:rgba(255,255,255,.2);color:#fff}
.dx-qa em{font-style:normal;background:#E11D48;color:#fff;border-radius:20px;font-size:11px;padding:1px 7px;margin-left:auto}
.dx-bar{height:9px;background:#FFE7D6;border-radius:6px;overflow:hidden}.dx-bar>i{display:block;height:100%;border-radius:6px}
.dx-row{display:grid;gap:10px;align-items:center;font-size:13px;margin:7px 0}
.dx-pipe{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
.dx-pipe a{border:1px solid #F1E3D9;border-left:4px solid var(--c);border-radius:12px;padding:9px 11px;display:flex;flex-direction:column;gap:4px;background:#fff;transition:all .15s}
.dx-pipe a:hover{transform:translateY(-1px);box-shadow:0 10px 20px -12px rgba(11,18,32,.3);background:var(--b)}
.dx-pipe .nm{font-size:12px;font-weight:700;color:var(--c)}.dx-pipe .ct{font-size:22px;font-weight:800;line-height:1}
.dx-pipe .pb{height:4px;background:#F3F4F6;border-radius:3px}.dx-pipe .pb i{display:block;height:4px;border-radius:3px;background:var(--c)}
.dx-feed{display:flex;flex-direction:column}.dx-fi{display:flex;gap:11px;padding:10px 0;border-top:1px solid #FFE7D6;align-items:flex-start}.dx-fi:first-child{border-top:0}
.dx-fi .fi{width:30px;height:30px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex:none}
.dx-fi .t{font-size:13px;font-weight:600;line-height:1.35}.dx-fi .w{font-size:11.5px;color:#9CA3AF}
.dx-al{display:flex;gap:10px;align-items:flex-start;border-top:1px solid #FFE7D6;padding:10px 0;transition:background .12s}.dx-al:first-of-type{border-top:0}
.dx-al:hover{background:#FFFAF6}.dx-lv{font-size:10px;font-weight:800;border-radius:6px;padding:3px 7px;flex:none;letter-spacing:.03em}
.dx-w{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.dx-ring{--p:0;width:74px;height:74px;border-radius:50%;background:conic-gradient(#FF5A00 calc(var(--p)*1%),#FFE7D6 0);display:flex;align-items:center;justify-content:center;flex:none}
.dx-ring b{width:56px;height:56px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font-size:15px}
.dx-tbl{width:100%;min-width:700px;border-collapse:collapse;font-size:13px}.dx-tbl th{text-align:left;color:#6B7280;font-size:12px;padding:8px}.dx-tbl td{padding:11px 8px;border-top:1px solid #FFE7D6}
.dx-tbl tbody tr:hover td{background:#FFFAF6}
.dx-sk .card{position:relative;overflow:hidden}.dx-sk .card::after{content:"";position:absolute;inset:0;background:linear-gradient(100deg,transparent 30%,rgba(255,255,255,.75) 50%,transparent 70%);animation:dxs 1s infinite;transform:translateX(-100%)}
@keyframes dxs{to{transform:translateX(100%)}}
.dx-empty{color:#9CA3AF;font-size:13px;padding:6px 0}
@media (max-width:1200px){.dx-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.dx-w{grid-template-columns:repeat(2,minmax(0,1fr))}.dx-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:900px){.dx-2,.dx-22{grid-template-columns:minmax(0,1fr)}.dx-3{grid-template-columns:minmax(0,1fr)}.dx-pipe{grid-template-columns:repeat(2,minmax(0,1fr))}.dx-search{min-width:0;flex:1 1 180px}}
@media (max-width:560px){.dx-kpis,.dx-w{grid-template-columns:minmax(0,1fr)}.dx-k .v{font-size:24px}.dx-qa{grid-template-columns:1fr 1fr}.dx-top h1{font-size:20px}}
</style>
<div class="dx" id="dxroot" data-q="{{ $q }}">
<header class="dx-top">
  <div class="ttl"><h1>Overview</h1><div class="muted">{{ $now->format('l, j F Y') }} · {{ $storeId ? ($storeList->firstWhere('id',$storeId)->name ?? 'Store') : 'All stores' }} · Asia/Dhaka <span id="dxclock">{{ $now->format('h:i A') }}</span></div></div>
  <form method="get" action="/admin" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0">
    <select name="store" onchange="this.form.submit()" aria-label="Store"><option value="">All stores</option>@foreach($storeList as $st)<option value="{{ $st->id }}" {{ $storeId==$st->id?'selected':'' }}>{{ $st->name }}</option>@endforeach</select>
    <input type="hidden" name="range" value="{{ $range }}">
    <span class="dx-pills">@foreach(['today'=>'Today','7'=>'7 Days','30'=>'30 Days'] as $rk=>$rl)<a href="/admin?range={{ $rk }}{{ $storeId ? '&store='.$storeId : '' }}" class="{{ $range===(string)$rk ? 'on' : '' }}">{{ $rl }}</a>@endforeach<a href="#" onclick="document.getElementById('dxcu').style.display='flex';return false" class="{{ $isCustom ? 'on' : '' }}">Custom</a></span>
    <span id="dxcu" style="display:{{ $isCustom ? 'flex' : 'none' }};gap:6px;align-items:center"><input type="date" name="from" value="{{ request('from') }}"><input type="date" name="to" value="{{ request('to') }}"><button type="submit" onclick="this.form.range.value='custom'" class="dx-ibtn" style="width:auto;padding:0 14px;font-weight:700;font-size:13px">Apply</button></span>
  </form>
  <form action="/admin/orders" class="dx-search">{!! $ico('search',16) !!}<input name="q" aria-label="Search orders" placeholder="Search order, phone, SKU"></form>
  <a href="/admin/orders?status=New" class="dx-ibtn" aria-label="Notifications" title="{{ count($alerts) }} items need attention">{!! $ico('bell') !!}@if(count($alerts))<span class="dx-dot"></span>@endif</a>
  <details class="dx-menu"><summary class="dx-ibtn" style="width:auto;padding:0 13px;gap:7px;font-size:13px;font-weight:700" title="Export summary">{!! $ico('dl',16) !!}Export</summary>
    <div class="pop"><a target="_blank" href="/admin/m/r-orders/export/pdf">PDF</a><a href="/admin/m/r-orders/export/xls">Excel</a><a href="/admin/m/r-orders/export/doc">Word (DOCX)</a><a href="/admin/m/r-orders/export/csv">CSV</a></div></details>
  <button type="button" class="dx-ibtn" id="dxref" title="Refresh now" aria-label="Refresh">{!! $ico('refresh',17) !!}</button>
  <label class="dx-live" id="dxlive" title="Auto refresh every 60 seconds"><i></i><span>Updated <b id="dxupd">{{ $now->format('h:i:s A') }}</b></span><input type="checkbox" id="dxauto" checked style="width:auto;height:auto;margin:0"> auto</label>
</header>

<section class="dx-kpis" id="dxkpis">
@foreach($kpis as [$l,$v,$sub,$icn,$tr,$href,$tn,$spk])
@php [$dir,$ttx,$good] = $tr; $tc = $tone[$tn]; $cls = $good === null ? 'flat' : ($good ? 'good' : 'bad'); @endphp
<a href="{{ $href }}" class="card dx-k"><div class="r1"><span class="chip" style="background:{{ $tc[0] }};color:{{ $tc[1] }}">{!! $ico($icn,19) !!}</span><span class="lb">{{ $l }}</span></div>
  <span class="v">{{ $v }}</span>
  @if($ttx!=='')<span class="dx-tr {{ $cls }}">{!! $ico($dir>0?'up':($dir<0?'down':'flat'),12) !!}{{ $ttx }}</span>@endif
  <span class="sub">{{ $sub }}</span>
  @if($spk && $nT > 1 && ($spk!=='profit' || $canFin))@php $sv = array_column($trend, $spk); $sm = max(1, max($sv)); $sw = 110; $sh = 34; $pp = []; foreach ($sv as $i => $y) $pp[] = round($i/max(1,count($sv)-1)*$sw,1).','.round($sh-2-($y/$sm)*($sh-6),1); @endphp
  <svg class="sp" width="{{ $sw }}" height="{{ $sh }}" viewBox="0 0 {{ $sw }} {{ $sh }}" aria-hidden="true"><polyline points="{{ implode(' ',$pp) }}" fill="none" stroke="{{ $tc[1] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/></svg>@endif
</a>
@endforeach
</section>

<section class="dx-2">
  <div class="card">
    <div class="head"><h2>Performance trend · last {{ $nT }} days</h2>
      <span class="dx-tabs" id="dxtabs"><button type="button" data-m="sales" class="on">Sales &amp; Orders</button>@if($canFin)<button type="button" data-m="profit">Profit</button>@endif<button type="button" data-m="delivery">Delivery</button></span></div>
    <div class="dx-chart" id="dxchart" data-trend="{{ json_encode($trend) }}"></div>
    <div class="muted" id="dxleg" style="margin-top:6px;display:flex;gap:16px;flex-wrap:wrap"></div>
  </div>
  <div class="card"><div class="head"><h2>Quick actions</h2></div>
    <div class="dx-qa">
      <a class="pri" href="/admin/orders/new"><span class="qi">{!! $ico('plus') !!}</span>Create Order</a>
      <a href="/admin/products/new"><span class="qi">{!! $ico('tag') !!}</span>Add Product</a>
      <a href="/admin/orders"><span class="qi">{!! $ico('list') !!}</span>View Orders</a>
      <a href="/admin/inventory"><span class="qi">{!! $ico('stock') !!}</span>Inventory</a>
      <a href="/admin/chat"><span class="qi">{!! $ico('chat') !!}</span>Messages @if($chatUnread)<em>{{ $chatUnread }}</em>@endif</a>
      <a href="/admin/analytics"><span class="qi">{!! $ico('chart') !!}</span>Reports</a>
      <a href="/admin/m/customers"><span class="qi">{!! $ico('users') !!}</span>Customers</a>
      <a href="/admin/call-center/phone"><span class="qi">{!! $ico('phone') !!}</span>Call Center</a>
      <a href="/admin/orders?status=New"><span class="qi">{!! $ico('check') !!}</span>Pending @if($newN)<em>{{ $newN }}</em>@endif</a>
      <a href="/admin/orders?status=Incomplete"><span class="qi">{!! $ico('alert') !!}</span>Incomplete @if($incOpen)<em>{{ $incOpen }}</em>@endif</a>
    </div></div>
</section>

<section class="dx-3">
  <div class="card"><div class="head"><h2>Top products</h2><a class="more" href="/admin/analytics/product-sales">All</a></div>
    @php $tp = max(1, $topProducts->max(2) ?? 1); @endphp
    @forelse($topProducts as $ti => [$pn,$pq,$pg])<a href="/admin/products?q={{ urlencode($pn) }}" style="display:block;margin:9px 0"><div style="display:flex;gap:8px;font-size:13px"><span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><b style="color:#FF5A00">{{ $ti+1 }}.</b> {{ $pn }}</span><b>৳{{ number_format($pg) }}</b></div><div class="dx-bar" style="margin-top:4px"><i style="width:{{ round($pg/$tp*100) }}%;background:linear-gradient(90deg,#FF8A4C,#FF5A00)"></i></div><span class="muted">{{ $pq }} sold</span></a>
    @empty<div class="dx-empty">No sales in this period yet.</div>@endforelse</div>
  <div class="card"><div class="head"><h2>Sales by category</h2><a class="more" href="/admin/analytics/category-sales">All</a></div>
    @php $ct = max(1, $cats->sum(1)); $pal = ['#FF5A00','#0B1220','#F59E0B','#16A34A','#6366F1','#9CA3AF']; @endphp
    @if($cats->count())<div style="display:flex;height:12px;border-radius:7px;overflow:hidden;margin-bottom:10px">@foreach($cats as $ci => [$cl,$cv])<i title="{{ $cl }}: ৳{{ number_format($cv) }}" style="width:{{ round($cv/$ct*100,1) }}%;background:{{ $pal[$ci%6] }}"></i>@endforeach</div>@endif
    @forelse($cats as $ci => [$cl,$cv])<a href="/admin/analytics/category-sales" class="dx-row" style="grid-template-columns:12px 1fr auto"><i style="width:10px;height:10px;border-radius:3px;background:{{ $pal[$ci%6] }}"></i><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $cl }}</span><span><b>৳{{ number_format($cv) }}</b> <span class="muted">{{ round($cv/$ct*100) }}%</span></span></a>
    @empty<div class="dx-empty">No category sales in this period yet.</div>@endforelse</div>
  <div class="card"><div class="head"><h2>Courier performance</h2><a class="more" href="/admin/courier-dashboard">Open</a></div>
    @forelse($couriers as [$cn,$cp,$cs])<a href="/admin/courier-dashboard" class="dx-row" style="grid-template-columns:84px 1fr 52px"><span style="font-weight:600">{{ $cn }}</span><div class="dx-bar"><i style="width:{{ min(100,$cs) }}%;background:{{ $cs>=80 ? '#16A34A' : ($cs>=60 ? '#F59E0B' : '#DC2626') }}"></i></div><b style="text-align:right">{{ $cs }}%</b></a><span class="muted">{{ $cp }} parcels · {{ $cn }}</span>
    @empty<div class="dx-empty">No courier data for this period.</div>@endforelse</div>
</section>

<section class="dx-22">
  <div class="card"><div class="head"><h2>Sales {{ $label }} by store</h2><span class="muted">৳ thousand</span></div>
    @forelse($stores as [$name,$val])<div class="dx-row" style="grid-template-columns:130px 1fr 64px"><span style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $name }}</span><div class="dx-bar" style="height:12px"><i style="width:{{ round($val/$max*100) }}%;background:linear-gradient(90deg,#FF8A4C,#FF5A00)"></i></div><b style="text-align:right">{{ number_format($val/1000, 1) }}</b></div>
    @empty<div class="dx-empty">No stores yet.</div>@endforelse</div>
  <div class="card"><div class="head"><h2>Order pipeline</h2><span class="muted">Click a stage to filter</span></div>
    @php $tot = max(1, collect(\App\Models\Mother::STAGES)->sum(fn($s) => $counts[$s] ?? 0)); @endphp
    <div class="dx-pipe">@foreach(\App\Models\Mother::STAGES as $sg)@php $cn2 = (int)($counts[$sg] ?? 0); @endphp
      <a href="/admin/orders?status={{ urlencode($sg) }}" style="--c:{{ $ST[$sg][1] ?? '#475569' }};--b:{{ $ST[$sg][0] ?? '#F3F4F6' }}"><span class="nm">{{ $sg }}</span><span class="ct">{{ $cn2 }}</span><span class="pb"><i style="width:{{ round($cn2/$tot*100) }}%"></i></span></a>@endforeach</div></div>
</section>

<section class="dx-w">
  <div class="card" style="display:flex;gap:14px;align-items:center"><span class="dx-ring" style="--p:{{ min(100,$goal['pct']) }}"><b>{{ $goal['pct'] }}%</b></span><div><h2>Today's target</h2><div class="muted" style="margin-top:4px">৳{{ number_format($goal['today']) }} of ৳{{ number_format($goal['target']) }}<br>(average of last 7 days)</div></div></div>
  <a href="/admin/m/customers" class="card"><h2>Customers · {{ $label }}</h2><div style="display:flex;gap:18px;margin-top:10px"><div><div style="font-size:26px;font-weight:800;color:#FF5A00">{{ $cust['new'] }}</div><div class="muted">New</div></div><div><div style="font-size:26px;font-weight:800;color:#0B1220">{{ $cust['returning'] }}</div><div class="muted">Returning</div></div></div></a>
  <a href="/admin/call-center/history" class="card"><h2>Call center · today</h2>@if($calls)<div style="display:flex;gap:18px;margin-top:10px"><div><div style="font-size:26px;font-weight:800">{{ $calls['total'] }}</div><div class="muted">Calls</div></div><div><div style="font-size:26px;font-weight:800;color:{{ $calls['missed']?'#B91C1C':'#166534' }}">{{ $calls['missed'] }}</div><div class="muted">Missed</div></div><div><div style="font-size:26px;font-weight:800">{{ floor($calls['talk']/60) }}m</div><div class="muted">Talk time</div></div></div>@else<div class="dx-empty" style="margin-top:8px">No call data yet.</div>@endif</a>
  <a href="/admin/m/customers" class="card"><h2>Fraud risk · 30 days</h2><div style="display:flex;gap:18px;margin-top:10px"><div><div style="font-size:26px;font-weight:800;color:{{ $risk['high']?'#B91C1C':'#166534' }}">{{ $risk['high'] }}</div><div class="muted">High-risk customers</div></div><div><div style="font-size:26px;font-weight:800">{{ $risk['watch'] }}</div><div class="muted">Returns/cancels</div></div></div></a>
</section>

<section class="dx-22">
  <div class="card" style="overflow:hidden">
    <div class="head"><h2>Live orders</h2><span class="dx-live"><i></i>Live</span><a class="more" href="/admin/orders">Open work queue</a></div>
    <div style="overflow-x:auto"><table class="dx-tbl">
      <thead><tr><th>Order</th><th>Store</th><th>Customer</th><th style="text-align:right">Total</th><th>Payment</th><th>Risk</th><th>Status</th><th style="text-align:right">Age</th></tr></thead>
      <tbody>@forelse($orders as $o)@php $b = $bad[$o->phone] ?? 0; $risk2 = $b >= 2 ? 'High' : ($b == 1 ? 'Medium' : 'Low'); $rf = ['Low'=>'#166534','Medium'=>'#C2410C','High'=>'#A3281E'][$risk2]; $ph = strlen($o->phone) > 6 ? substr($o->phone,0,5).'-••••'.substr($o->phone,-2) : $o->phone; $c = $ST[$o->status] ?? ['#F3F4F6','#374151']; @endphp
      <tr><td style="font-weight:700"><a href="/admin/orders/{{ $o->id }}" style="color:#FF5A00">{{ $o->invoice }}</a></td><td>{{ $o->store ?? '-' }}</td><td>{{ $ph }}</td><td style="text-align:right;font-weight:700">৳{{ number_format($o->total) }}</td><td>{{ $o->payment_method ?: 'COD' }}</td><td style="font-weight:700;color:{{ $rf }}">{{ $risk2 }}</td>
      <td><span style="font-size:11px;font-weight:700;border-radius:6px;padding:3px 8px;background:{{ $c[0] }};color:{{ $c[1] }}">{{ $o->status }}</span></td><td style="text-align:right;color:#6B7280">{{ \Carbon\Carbon::parse($o->created_at)->diffForHumans(null, true, true) }}</td></tr>
      @empty<tr><td colspan="8" class="dx-empty">No orders yet. <a style="color:#FF5A00;font-weight:700" href="/admin/orders/new">Create order</a></td></tr>@endforelse</tbody></table></div>
  </div>
  <div style="display:flex;flex-direction:column;gap:14px;min-width:0">
    <div class="card"><div class="head"><h2>Attention required</h2>@if(count($alerts))<span class="dx-lv" style="background:#FEE2E2;color:#B91C1C">{{ count($alerts) }}</span>@endif</div>
      @forelse($alerts as [$lvl,$tt,$sub,$lk])<a href="{{ $lk }}" class="dx-al"><span class="dx-lv" style="background:{{ $LV[$lvl][0] }};color:{{ $LV[$lvl][1] }}">{{ $lvl }}</span><div><div style="font-size:13px;font-weight:700">{{ $tt }}</div><div class="muted">{{ $sub }}</div></div></a>
      @empty<div style="display:flex;gap:8px;align-items:center;color:#166534;font-weight:700;font-size:13px">{!! $ico('check') !!} All clear right now.</div>@endforelse</div>
    <div class="card"><div class="head"><h2>Recent activity</h2></div>
      <div class="dx-feed">@php $FI = ['order'=>['cart','#FFF1E8','#FF5A00'],'edit'=>['edit','#E6EFF9','#1D5FA8'],'stock'=>['stock','#DDF1EE','#0E5F58'],'export'=>['dl','#F1EAF8','#5B3FA6'],'incomplete'=>['alert','#FEF3C7','#B45309'],'call'=>['phone','#DCFCE7','#166534']]; @endphp
      @forelse($feed as [$ty,$tx,$ago,$abs,$lk])@php $fi = $FI[$ty] ?? $FI['edit']; @endphp
      <a href="{{ $lk ?: '#' }}" class="dx-fi"><span class="fi" style="background:{{ $fi[1] }};color:{{ $fi[2] }}">{!! $ico($fi[0],16) !!}</span><div><div class="t">{{ $tx }}</div><div class="w" title="{{ $abs }}">{{ $ago }}</div></div></a>
      @empty<div class="dx-empty">No activity yet.</div>@endforelse</div></div>
    <div class="card"><div class="head"><h2>System health</h2></div>
      @foreach($health as [$k,$v])<div class="dx-row" style="grid-template-columns:1fr auto auto"><span class="muted" style="font-size:13px">{{ $k }}</span><b>{{ $v }}</b><span class="dx-lv" style="background:#DCFCE7;color:#166534">OK</span></div>@endforeach</div>
  </div>
</section>
</div>
@verbatim
<script>
(function(){
var COL={sales:'#FF5A00',orders:'#0B1220',profit:'#16A34A',del:'#0E5F58'};
function fmt(n){return '৳'+Math.round(n).toLocaleString('en-US')}
function smooth(p){if(p.length<2)return '';var d='M'+p[0][0]+','+p[0][1];for(var i=0;i<p.length-1;i++){var a=p[i-1]||p[i],b=p[i],c=p[i+1],e=p[i+2]||c;d+=' C'+(b[0]+(c[0]-a[0])/6)+','+(b[1]+(c[1]-a[1])/6)+' '+(c[0]-(e[0]-b[0])/6)+','+(c[1]-(e[1]-b[1])/6)+' '+c[0]+','+c[1]}return d}
function draw(){
 var box=document.getElementById('dxchart');if(!box)return;var T=JSON.parse(box.dataset.trend||'[]');var m=(document.querySelector('#dxtabs .on')||{dataset:{m:'sales'}}).dataset.m;
 var W=720,H=250,L=44,R=16,Tp=14,B=28,n=T.length,cw=(W-L-R)/Math.max(1,n),iw=W-L-R,ih=H-Tp-B;
 var A,Bs,la,lb,fa,fb;
 if(m==='sales'){A=T.map(function(t){return t.sales});Bs=T.map(function(t){return t.orders});la='Sales (৳)';lb='Orders';fa=fmt;fb=function(v){return v+' orders'}}
 else if(m==='profit'){A=T.map(function(t){return t.profit});Bs=null;la='Gross profit (৳)';fa=fmt}
 else{A=T.map(function(t){return t.orders});Bs=T.map(function(t){return t.delivered});la='Orders';lb='Delivered';fa=function(v){return v+' orders'};fb=function(v){return v+' delivered'}}
 var mxA=Math.max(1,Math.max.apply(null,A)),mxB=Bs?Math.max(1,Math.max.apply(null,Bs)):1;
 var s='<svg viewBox="0 0 '+W+' '+H+'"><defs><linearGradient id="gA" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'+(m==='profit'?COL.profit:COL.sales)+'" stop-opacity=".95"/><stop offset="1" stop-color="'+(m==='profit'?COL.profit:COL.sales)+'" stop-opacity=".35"/></linearGradient></defs>';
 for(var g=0;g<=4;g++){var y=Tp+ih*(1-g/4);s+='<line x1="'+L+'" x2="'+(W-R)+'" y1="'+y+'" y2="'+y+'" stroke="#FFE7D6"/><text x="'+(L-6)+'" y="'+(y+3)+'" text-anchor="end" font-size="10" fill="#9CA3AF">'+(m==='sales'||m==='profit'?(mxA*g/4>=1000?Math.round(mxA*g/4/1000)+'k':Math.round(mxA*g/4)):Math.round(mxA*g/4))+'</text>'}
 var bw=Math.max(5,Math.min(36,cw*.58));
 A.forEach(function(v,i){var h=ih*Math.max(0,v)/mxA,x=L+cw*i+(cw-bw)/2;s+='<rect x="'+x.toFixed(1)+'" y="'+(Tp+ih-h).toFixed(1)+'" width="'+bw.toFixed(1)+'" height="'+Math.max(h,v>0?2:0).toFixed(1)+'" rx="5" fill="url(#gA)"/>'});
 if(Bs){var pts=Bs.map(function(v,i){return [L+cw*i+cw/2,Tp+ih*(1-v/mxB)]});s+='<path d="'+smooth(pts)+'" fill="none" stroke="'+(m==='sales'?COL.orders:COL.del)+'" stroke-width="2.4" stroke-linecap="round"/>';pts.forEach(function(p){s+='<circle cx="'+p[0].toFixed(1)+'" cy="'+p[1].toFixed(1)+'" r="3.4" fill="#fff" stroke="'+(m==='sales'?COL.orders:COL.del)+'" stroke-width="2"/>'})}
 var step=Math.ceil(n/9);T.forEach(function(t,i){if(i%step===0||i===n-1)s+='<text x="'+(L+cw*i+cw/2).toFixed(1)+'" y="'+(H-8)+'" text-anchor="middle" font-size="10" fill="#6B7280">'+t.d+'</text>'});
 T.forEach(function(t,i){s+='<rect class="hz" data-i="'+i+'" x="'+(L+cw*i).toFixed(1)+'" y="'+Tp+'" width="'+cw.toFixed(1)+'" height="'+ih+'" fill="transparent"/>'});
 s+='</svg><div class="dx-tip" id="dxtip"></div>';box.innerHTML=s;
 document.getElementById('dxleg').innerHTML='<span><i style="display:inline-block;width:10px;height:10px;border-radius:3px;background:'+(m==='profit'?COL.profit:COL.sales)+';margin-right:6px"></i>'+la+'</span>'+(Bs?'<span><i style="display:inline-block;width:16px;height:3px;border-radius:2px;background:'+(m==='sales'?COL.orders:COL.del)+';margin-right:6px;vertical-align:middle"></i>'+lb+'</span>':'');
 var tip=document.getElementById('dxtip'),svg=box.querySelector('svg');
 box.querySelectorAll('.hz').forEach(function(r){r.addEventListener('mousemove',function(){var i=+r.dataset.i,t=T[i];var rb=svg.getBoundingClientRect(),k=rb.width/W;tip.innerHTML='<b>'+t.d+'</b><br>'+la+': <b>'+fa(A[i])+'</b>'+(Bs?'<br>'+lb+': <b>'+fb(Bs[i])+'</b>':'');tip.style.left=((L+cw*i+cw/2)*k)+'px';tip.style.top=((Tp+ih*(1-A[i]/mxA))*k-6)+'px';tip.style.opacity=1});r.addEventListener('mouseleave',function(){tip.style.opacity=0})});
}
function wire(){document.querySelectorAll('#dxtabs button').forEach(function(b){b.onclick=function(){document.querySelectorAll('#dxtabs button').forEach(function(x){x.classList.remove('on')});b.classList.add('on');draw()}});draw()}
wire();
var root=document.getElementById('dxroot'),busy=false;
function auto(){try{return localStorage.getItem('dxauto')!=='0'}catch(e){return true}}
var cb=document.getElementById('dxauto');if(cb){cb.checked=auto();cb.onchange=function(){try{localStorage.setItem('dxauto',cb.checked?'1':'0')}catch(e){}document.getElementById('dxlive').classList.toggle('off',!cb.checked)};document.getElementById('dxlive').classList.toggle('off',!cb.checked)}
function refresh(){if(busy)return;busy=true;root.classList.add('dx-sk');
 var tab=(document.querySelector('#dxtabs .on')||{dataset:{}}).dataset.m;
 fetch(location.href,{headers:{'X-Requested-With':'fetch'},cache:'no-store'}).then(function(r){return r.text()}).then(function(h){var d=new DOMParser().parseFromString(h,'text/html').getElementById('dxroot');if(!d)return;
  ['dxkpis'].forEach(function(id){var a=document.getElementById(id),b=d.querySelector('#'+id);if(a&&b)a.innerHTML=b.innerHTML});
  var secs=root.querySelectorAll(':scope > section'),ns=d.querySelectorAll(':scope > section');secs.forEach(function(s,i){if(s.id!=='dxkpis'&&ns[i])s.innerHTML=ns[i].innerHTML});
  if(tab){var tb=root.querySelector('#dxtabs [data-m="'+tab+'"]');if(tb){root.querySelectorAll('#dxtabs button').forEach(function(x){x.classList.remove('on')});tb.classList.add('on')}}
  wire();var t=new Date();document.getElementById('dxupd').textContent=t.toLocaleTimeString('en-US')}).catch(function(){}).then(function(){busy=false;root.classList.remove('dx-sk')})}
document.getElementById('dxref').onclick=refresh;
setInterval(function(){if(cb&&cb.checked&&!document.hidden)refresh()},60000);
})();
</script>
@endverbatim
@endsection
