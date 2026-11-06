<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="/css/nk-type.css?v=1">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · NK Corporation</title>
<link rel="icon" href="/img/logo.webp">
<link rel="stylesheet" href="/css/nk-table.css?v=3">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;600&display=swap">
<style>
:root{--navy:#123a86;--ink:#14213d;--muted:#51607a;--line:#dbe3f0;--bg:#f4f7fc;--blue:#1d4ed8}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:var(--font-ui);font-size:var(--fs-13);line-height:var(--lh-body)}
a{color:var(--blue)}
.shell{display:flex;min-height:100vh}
.side{width:236px;flex-shrink:0;background:#fff;border-right:1px solid var(--line);padding:14px;display:flex;flex-direction:column;gap:4px}
.brand{background:var(--navy);border-radius:12px;padding:14px;display:flex;align-items:center;gap:10px;color:#fff;margin-bottom:10px}
.brand img{width:40px;height:40px;border-radius:10px}
.brand b{display:block;font-size:var(--fs-14)}.brand small{display:block;font-size:var(--fs-10);letter-spacing:.08em;color:#c9d6f5;font-weight:700}
.grp{font-size:var(--fs-11);font-weight:700;letter-spacing:.08em;color:var(--muted);padding:10px 10px 4px}
.side a.nav{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:12px;text-decoration:none;color:var(--ink);font-size:var(--fs-13);font-weight:500;line-height:var(--lh-label)}
.side a.nav.on{background:#dce8ff;color:var(--blue);font-weight:600}
.side a.nav .badge{margin-left:auto;background:#b3261e;color:#fff;font-size:var(--fs-10);font-weight:700;padding:1px 7px;border-radius:999px}
.side a.nav.soon{color:#7b8aa8}.side a.nav .later{margin-left:auto;font-size:var(--fs-10);font-weight:700;color:#7b8aa8;border:1px solid var(--line);border-radius:999px;padding:0 6px}
.me{margin-top:auto;border-top:1px solid var(--line);padding:12px 6px 0;display:flex;flex-direction:column;gap:6px;font-size:var(--fs-12)}
.me b{font-size:var(--fs-13)}
main{flex:1;min-width:0;padding:24px;display:flex;flex-direction:column;gap:16px}
h1{margin:0;font-size:clamp(18px,1.5vw,22px);font-weight:700;line-height:var(--lh-heading)}
h2{line-height:var(--lh-heading)}
.sub{color:var(--muted);font-size:var(--fs-12);line-height:var(--lh-label);margin:2px 0 0}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 2px rgba(20,33,61,.06),0 4px 12px rgba(20,33,61,.04)}
.pad{padding:18px 20px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.kpi{padding:16px 18px;display:flex;flex-direction:column;gap:4px}.kpi span{color:var(--muted);font-size:var(--fs-12);font-weight:500}.kpi b{font-size:var(--fs-22);font-weight:700;font-variant-numeric:tabular-nums;line-height:var(--lh-heading)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;padding:0 14px;border-radius:999px;border:1px solid #7b8aa8;background:#fff;color:var(--ink);font:600 var(--fs-13) var(--font-ui);cursor:pointer;text-decoration:none}
.btn.pri{background:var(--navy);border-color:var(--navy);color:#fff;font-weight:700}
.btn.sm{min-height:32px;padding:0 10px;font-size:var(--fs-12)}
label.f{display:flex;flex-direction:column;gap:5px;font-size:var(--fs-12);font-weight:600;line-height:var(--lh-label);color:var(--muted)}
input,select,textarea{min-height:40px;border:1px solid #7b8aa8;border-radius:8px;padding:0 10px;font:400 var(--fs-13) var(--font-ui);color:var(--ink);background:#fff;width:100%}
::placeholder{font-size:var(--fs-13);color:var(--tx-faint)}
textarea{padding:8px 10px;min-height:60px}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
.pill{display:inline-block;font-size:var(--fs-11);font-weight:600;line-height:1.5;padding:2px 9px;border-radius:999px;background:#eef2f9;color:var(--muted)}
.st-new{background:#fde9e7;color:#b3261e}.st-called{background:#fff1d6;color:#8a4b00}.st-confirmed{background:#e6eeff;color:#1d4ed8}.st-delivered{background:#e3f6ec;color:#0f6b3c}.st-cancelled{background:#eceff3;color:#51607a}
.flash{padding:10px 14px;border-radius:12px;background:#e3f6ec;color:#0f6b3c;font-size:var(--fs-13);font-weight:600}
.err{padding:10px 14px;border-radius:12px;background:#fde9e7;color:#b3261e;font-size:var(--fs-13);font-weight:600}
.pagination{display:flex;flex-wrap:wrap;gap:6px;list-style:none;padding:0;margin:0}.pagination li>*{display:inline-flex;min-width:34px;height:34px;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:8px;background:#fff;text-decoration:none;padding:0 8px}.pagination .active>*{background:var(--navy);color:#fff}
.tabs{display:flex;flex-wrap:wrap;gap:6px}
.tabs a{padding:6px 12px;border-radius:999px;text-decoration:none;border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:600;font-size:var(--fs-12)}
.tabs a.on{background:var(--navy);border-color:var(--navy);color:#fff}
@media (max-width:860px){.shell{flex-direction:column}.side{width:auto;border-right:0;border-bottom:1px solid var(--line)}.me{margin-top:8px}main{padding:16px}}
.gs{position:relative;margin-bottom:10px}
.gs input{width:100%;box-sizing:border-box;min-height:40px;border:1px solid var(--line);border-radius:999px;padding:0 14px;font:500 13px 'Plus Jakarta Sans','Hind Siliguri',sans-serif;background:#fff}
.gsr{position:absolute;z-index:50;left:0;top:44px;width:340px;max-width:86vw;max-height:70vh;overflow:auto;background:#fff;border:1px solid #dbe3f0;border-radius:14px;box-shadow:0 10px 30px rgba(20,33,61,.16);padding:6px;display:flex;flex-direction:column}
.gsr[hidden]{display:none}
.gsg{font:800 10px 'Plus Jakarta Sans',sans-serif;letter-spacing:.1em;color:#51607a;padding:8px 10px 4px}
.gsr a{display:flex;flex-direction:column;gap:2px;padding:8px 10px;border-radius:10px;text-decoration:none;color:#14213d;font-size:var(--fs-13)}
.gsr a span{font-size:var(--fs-12);color:#51607a;font-family:'Hind Siliguri','Plus Jakarta Sans',sans-serif}
.gsr a:hover,.gsr a:focus{background:#eef2f9;outline:none}
.gse{padding:10px;font-size:var(--fs-13);color:#51607a}
/* Sidebar menu on the global table system: each group is a compact bordered grid,
   the group name is its header row, each link a 32px row, the open page a selected row. */
.ngrp{display:flex;flex-direction:column;border:1px solid var(--border-grid,#ced4da);border-radius:8px;overflow:hidden;background:#fff;margin-bottom:8px}
.ngrp:not(:has(a.nav)){display:none}
.ngrp .grp{background:var(--table-header-bg,#f1f4f7);color:var(--table-text,#14213d);font-size:var(--fs-11);font-weight:600;letter-spacing:.06em;padding:0 10px;height:28px;display:flex;align-items:center;border-bottom:1px solid var(--border-strong,#bfc6ce)}
.ngrp a.nav{border-radius:0;min-height:32px;padding:5px 10px;border-bottom:1px solid var(--border-soft,#e2e6ea);font-size:var(--fs-13);gap:9px}
.ngrp a.nav:last-child{border-bottom:0}
.ngrp a.nav:hover{background:var(--table-hover-bg,#f8fafc)}
.ngrp a.nav.on{background:var(--table-select-bg,#eaf1ff);box-shadow:inset 3px 0 0 var(--navy);color:var(--blue);font-weight:600}
.ngrp a.nav svg{width:16px;height:16px;flex-shrink:0}
</style>
</head>
<body>
@php($u = auth()->user())
<div class="shell">
  <aside class="side" aria-label="Main menu">
    <div class="brand" style="flex-direction:column;align-items:stretch;gap:8px"><div style="display:flex;align-items:center;gap:10px"><img src="/img/logo.webp" alt="NK Corporation logo"><div><b>NK Corporation</b><small>BUSINESS CONTROL</small></div></div><span style="align-self:flex-start;background:#2a52a8;color:#fff;font-size:var(--fs-11);font-weight:600;padding:3px 10px;border-radius:999px">{{ now()->timezone('Asia/Dhaka')->format('F Y') }}</span></div>
    @unless($u->must_change_password)
    @if(in_array($u->role, ['super_admin', 'admin', 'manager', 'accounts']))
    <div class="gs" role="search">
      <input id="gsq" type="search" placeholder="Search customer, invoice, route…" aria-label="Search customer, invoice, route" autocomplete="off" aria-controls="gsr" aria-expanded="false">
      <div id="gsr" class="gsr" role="listbox" hidden></div>
    </div>
    <script>
    (function(){var q=document.getElementById('gsq'),r=document.getElementById('gsr'),t=null,seq=0;
      function esc(x){return String(x==null?'':x).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
      function close(){r.hidden=true;q.setAttribute('aria-expanded','false');}
      q.addEventListener('input',function(){clearTimeout(t);var v=q.value.trim();if(v.length<2){close();return;}t=setTimeout(function(){var n=++seq;fetch('{{ route('search') }}?q='+encodeURIComponent(v),{headers:{'Accept':'application/json'}}).then(function(x){return x.ok?x.json():{groups:[]};}).then(function(d){if(n!==seq)return;
        r.innerHTML=d.groups.length?d.groups.map(function(g){return '<span class="gsg">'+esc(g.g)+'</span>'+g.items.map(function(i){return '<a role="option" href="'+esc(i.u)+'"><b>'+esc(i.t)+'</b><span>'+esc(i.m)+'</span></a>';}).join('');}).join(''):'<span class="gse">“'+esc(v)+'” — কিছু পাওয়া যায়নি</span>';
        r.hidden=false;q.setAttribute('aria-expanded','true');}).catch(close);},220);});
      q.addEventListener('keydown',function(e){var a=[].slice.call(r.querySelectorAll('a'));if(e.key==='Escape'){close();return;}if(e.key==='ArrowDown'&&a.length){e.preventDefault();a[0].focus();}if(e.key==='Enter'&&a.length){e.preventDefault();location.href=a[0].href;}});
      r.addEventListener('keydown',function(e){var a=[].slice.call(r.querySelectorAll('a')),i=a.indexOf(document.activeElement);if(e.key==='ArrowDown'&&i<a.length-1){e.preventDefault();a[i+1].focus();}if(e.key==='ArrowUp'){e.preventDefault();(i>0?a[i-1]:q).focus();}if(e.key==='Escape'){close();q.focus();}});
      document.addEventListener('click',function(e){if(!e.target.closest('.gs'))close();});
      document.addEventListener('keydown',function(e){if(e.key==='/'&&!/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)){e.preventDefault();q.focus();}});
    })();
    </script>
    @endif
    @php($newCount = \App\Models\WebsiteOrder::where('status', 'new')->count())
    @php($waitX = \App\Models\BrandExchange::where('status', 'waiting')->count())
    @php($badge = rescue(fn () => [
        'weborders' => $newCount, 'brands' => $waitX,
        'wholesale' => \App\Models\Sale::where('type', 'wholesale')->whereIn('stage', ['waiting', 'on_road'])->count(),
        'corders' => \App\Models\CustomerOrder::where('status', 'new')->count(),
        'closing' => \App\Models\Trip::whereIn('status', ['returned', 'settled'])->count(),
        'cx' => \App\Models\CylinderExchange::where('status', 'pending')->count(),
    ], ['weborders' => $newCount, 'brands' => $waitX], false))
    @php($adminOnly = ['company', 'users', 'activity', 'sms', 'hero', 'website'])
    @php($superOnly = ['supplier', 'accounts', 'vpl', 'import'])
    @php($mgrOnly = ['map', 'live', 'corders'])
    @php($officeOnly = ['cx', 'rdash', 'vehicles', 'loading', 'return', 'settle', 'closing', 'exp', 'export', 'erp'])
    @php($cur = request()->route()?->getName() ?? '')
    @php($cur = ['trips.wizard' => 'trips.closing', 'trips.sales' => 'fleet.dash', 'trips.slip' => 'trips.loading'][$cur] ?? $cur)
    @php($allRoutes = collect(\App\Support\Roadmap::MENU)->flatten(1)->pluck(2)->filter())
    @php($activeRoute = $allRoutes->first(fn ($r) => $r === $cur) ?? $allRoutes->first(fn ($r) => str_starts_with($cur, explode('.', $r)[0].'.')))
    @foreach(\App\Support\Roadmap::MENU as $group => $items)
      <div class="ngrp" role="group" aria-label="{{ $group }}">
      <span class="grp">{{ $group }}</span>
      @foreach($items as [$key, $label, $route, $design])
        @continue(in_array($key, $adminOnly) && ! in_array($u->role, ['super_admin', 'admin']))
        @continue(in_array($key, $superOnly) && $u->role !== 'super_admin')
        @continue($key === 'hr' && ! in_array($u->role, ['super_admin', 'admin', 'accounts']))
        @continue(in_array($key, $mgrOnly) && ! in_array($u->role, ['super_admin', 'admin', 'manager']))
        @continue(in_array($key, $officeOnly) && ! in_array($u->role, ['super_admin', 'admin', 'manager', 'accounts']))
        @if($route)
          <a class="nav {{ $route === $activeRoute ? 'on' : '' }}" href="{{ route($route) }}">{!! \App\Support\Icons::svg($key) !!}<span style="flex-grow:1">{{ $label }}</span>
            @if(! empty($badge[$key]))<span class="badge" aria-label="{{ $badge[$key] }} waiting">{{ $badge[$key] }}</span>@endif
          </a>
        @else
          <a class="nav soon {{ request()->is('soon/'.$key) ? 'on' : '' }}" href="{{ route('soon', $key) }}">{!! \App\Support\Icons::svg($key) !!}<span style="flex-grow:1">{{ $label }}</span><span class="later">soon</span></a>
        @endif
      @endforeach
      </div>
    @endforeach
    <div class="ngrp" role="group" aria-label="PROJECT">
    <span class="grp">PROJECT</span>
    <a class="nav {{ request()->routeIs('roadmap') ? 'on' : '' }}" href="{{ route('roadmap') }}">{!! \App\Support\Icons::svg('activity') !!}<span style="flex-grow:1">Build progress</span></a>
    </div>
    @endunless
    <div class="me">
      <div style="display:flex;align-items:center;gap:10px"><span style="width:32px;height:32px;border-radius:999px;background:#e6eeff;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:var(--fs-12);font-weight:700;flex-shrink:0">{{ collect(explode(' ', $u->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span><div style="display:flex;flex-direction:column;min-width:0"><b>{{ $u->name }}</b><span style="color:var(--muted)">{{ $u->roleLabel() }}</span></div></div>
      <a href="{{ route('password.edit') }}">Change password</a>
      <form method="post" action="{{ route('logout') }}">@csrf<button class="btn sm" type="submit">Log out</button></form>
    </div>
  </aside>
  <main>
    @if(session('ok'))<div class="flash">{{ session('ok') }}@if(session('dl') && ! request()->routeIs('exports.index')) · <a href="{{ session('dl') }}" style="color:inherit;font-weight:700">Download</a>@endif</div>@endif
    @if($errors->any())<div class="err">{{ $errors->first() }}@if($el = session('err_link')) <a href="{{ $el[0] }}" style="font-weight:700;color:inherit">{{ $el[1] }}</a>@endif</div>@endif
    @yield('content')
  </main>
</div>
<script src="/js/nk-table.js?v=3" defer></script>
</body>
</html>
