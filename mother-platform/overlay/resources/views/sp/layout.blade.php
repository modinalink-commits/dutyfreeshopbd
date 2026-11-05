@php
  $path = '/'.trim(request()->path(), '/'); $SP = \App\Support\SupplierPortal::class;
  $nav = [['Dashboard','/supplier-portal','home',null,'M3 11l9-8 9 8v9a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1z'],
    ['Purchase Orders','/supplier-portal/pos','pos','po','M6 2h9l5 5v15H6zM14 2v6h6M9 13h8M9 17h8'],
    ['RFQ / Price Request','/supplier-portal/rfq','rfq','rfq','M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6'],
    ['Bills & Invoices','/supplier-portal/bills','bills','bills','M4 3h16v18l-3-2-2 2-3-2-3 2-2-2-3 2zM8 8h8M8 12h8'],
    ['Ledger','/supplier-portal/ledger','ledger','ledger','M4 4h16v16H4zM4 9h16M9 4v16'],
    ['Payments','/supplier-portal/payments','payments','payments','M2 6h20v12H2zM2 10h20M6 15h4'],
    ['Notifications','/supplier-portal/notifications','notifications',null,'M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0'],
    ['Profile','/supplier-portal/profile','profile',null,'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8']];
  $nav = array_values(array_filter($nav, fn($n) => !$n[3] || $SP::can($n[3])));
  $icon = fn($d) => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="'.$d.'"/></svg>';
  $on = fn($h) => $h === '/supplier-portal' ? $path === '/supplier-portal' : str_starts_with($path, $h);
  $unread = (int)\Illuminate\Support\Facades\DB::table('supplier_notifications')->where('supplier_id',$u->supplier_id)->whereNull('read_at')->count();
  $bottom = array_values(array_filter($nav, fn($n) => in_array($n[2], ['home','pos','rfq','ledger'])));
@endphp
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="noindex,nofollow">
<title>@yield('title','Dashboard') · {{ $brand }}</title><meta name="theme-color" content="#FF5A00">
<link rel="manifest" href="/supplier-portal/manifest.webmanifest"><link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@include('sp.css')
</head><body>
<div class="shell">
  <div class="side">
    <a class="brand" href="/supplier-portal"><img src="/brand/export-logo.png" alt="" onerror="this.style.display='none'"><span><b>{{ $brand }}</b></span></a>
    <div class="nav">@foreach($nav as $n)<a class="nv {{ $on($n[1]) ? 'on' : '' }}" href="{{ $n[1] }}">{!! $icon($n[4]) !!}<span>{{ $n[0] }}</span>@if($n[2]==='notifications' && $unread)<em class="bc">{{ $unread }}</em>@endif</a>@endforeach</div>
    <div class="who"><b>{{ $u->supplier_name }}</b><small>{{ $u->supplier_code }} · {{ \App\Support\SupplierPortal::ROLES[$u->role] ?? $u->role }}</small><form method="post" action="/supplier-portal/logout">@csrf<button class="g sm">Sign out</button></form></div>
  </div>
  <div class="main">
    <div class="top">
      <div class="hi"><small>@php $h=(int)now('Asia/Dhaka')->format('G'); @endphp {{ $h<12 ? 'Good Morning' : ($h<17 ? 'Good Afternoon' : 'Good Evening') }}</small><b>{{ $u->supplier_name }}</b></div>
      <form class="srch" action="/supplier-portal/search" method="get"><input name="q" placeholder="Search PO, bill, payment, product…" value="{{ request('q') }}"></form>
      <a class="bell" href="/supplier-portal/notifications" title="Notifications">{!! $icon('M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0') !!}<em class="bc" id="bc" style="{{ $unread ? '' : 'display:none' }}">{{ $unread }}</em></a>
    </div>
    <div class="page">
      @if(session('ok'))<div class="al ok">{{ session('ok') }}</div>@endif
      @if(session('bad'))<div class="al bad">{{ session('bad') }}</div>@endif
      @if($errors->any())<div class="al bad">{{ $errors->first() }}</div>@endif
      @yield('body')
    </div>
  </div>
</div>
<div class="bnav">@foreach($bottom as $n)<a class="{{ $on($n[1]) ? 'on' : '' }}" href="{{ $n[1] }}">{!! $icon($n[4]) !!}<span>{{ explode(' ', $n[0])[0] }}</span></a>@endforeach<a href="#" id="moreb">{!! $icon('M5 12h.01M12 12h.01M19 12h.01') !!}<span>More</span></a></div>
<div class="sheet" id="sheet"><div class="sh"><b>More</b>@foreach($nav as $n)@if(!in_array($n, $bottom))<a href="{{ $n[1] }}">{!! $icon($n[4]) !!}<span>{{ $n[0] }}</span></a>@endif @endforeach<form method="post" action="/supplier-portal/logout">@csrf<button class="g">Sign out</button></form></div></div>
<script>
(function(){var s=document.getElementById('sheet'),m=document.getElementById('moreb');m.onclick=function(e){e.preventDefault();s.classList.toggle('open')};s.onclick=function(e){if(e.target===s)s.classList.remove('open')};
function poll(){fetch('/supplier-portal/api/summary',{credentials:'same-origin',headers:{'Accept':'application/json'}}).then(function(r){return r.ok?r.json():null}).then(function(j){if(!j)return;var b=document.getElementById('bc');if(b){b.textContent=j.unread;b.style.display=j.unread?'':'none'}}).catch(function(){})}
setInterval(function(){if(!document.hidden)poll()},60000);
if('serviceWorker' in navigator){navigator.serviceWorker.register('/supplier-portal/sw.js',{scope:'/supplier-portal'}).catch(function(){})}})();
</script>
</body></html>
