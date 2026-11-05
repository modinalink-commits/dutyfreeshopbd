<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · NK Corporation</title>
<link rel="icon" href="/img/logo.webp">
<link rel="stylesheet" href="/css/nk-table.css?v=1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Hind+Siliguri:wght@400;600&display=swap">
<style>
:root{--navy:#123a86;--ink:#14213d;--muted:#51607a;--line:#dbe3f0;--bg:#f4f7fc;--blue:#1d4ed8}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:'Plus Jakarta Sans','Hind Siliguri',system-ui,sans-serif;font-size:14px}
a{color:var(--blue)}
.shell{display:flex;min-height:100vh}
.side{width:236px;flex-shrink:0;background:#fff;border-right:1px solid var(--line);padding:14px;display:flex;flex-direction:column;gap:4px}
.brand{background:var(--navy);border-radius:12px;padding:14px;display:flex;align-items:center;gap:10px;color:#fff;margin-bottom:10px}
.brand img{width:40px;height:40px;border-radius:10px}
.brand b{display:block;font-size:14px}.brand small{display:block;font-size:10px;letter-spacing:.08em;color:#c9d6f5;font-weight:700}
.grp{font-size:10px;font-weight:700;letter-spacing:.08em;color:var(--muted);padding:10px 10px 4px}
.side a.nav{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:12px;text-decoration:none;color:var(--ink);font-weight:500}
.side a.nav.on{background:#dce8ff;color:var(--blue);font-weight:700}
.side a.nav .badge{margin-left:auto;background:#b3261e;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:999px}
.side a.nav.soon{color:#7b8aa8}.side a.nav .later{margin-left:auto;font-size:10px;font-weight:700;color:#7b8aa8;border:1px solid var(--line);border-radius:999px;padding:0 6px}
.me{margin-top:auto;border-top:1px solid var(--line);padding:12px 6px 0;display:flex;flex-direction:column;gap:6px;font-size:12px}
.me b{font-size:13px}
main{flex:1;min-width:0;padding:24px;display:flex;flex-direction:column;gap:16px}
h1{margin:0;font-size:22px}
.sub{color:var(--muted);font-size:13px;margin:2px 0 0}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 2px rgba(20,33,61,.06),0 4px 12px rgba(20,33,61,.04)}
.pad{padding:18px 20px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
.kpi{padding:16px 18px;display:flex;flex-direction:column;gap:4px}.kpi span{color:var(--muted);font-size:12px;font-weight:600}.kpi b{font-size:26px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;padding:0 14px;border-radius:999px;border:1px solid #7b8aa8;background:#fff;color:var(--ink);font:600 13px 'Plus Jakarta Sans',sans-serif;cursor:pointer;text-decoration:none}
.btn.pri{background:var(--navy);border-color:var(--navy);color:#fff}
.btn.sm{min-height:32px;padding:0 10px;font-size:12px}
label.f{display:flex;flex-direction:column;gap:5px;font-size:12px;font-weight:600;color:var(--muted)}
input,select,textarea{min-height:40px;border:1px solid #7b8aa8;border-radius:8px;padding:0 10px;font:500 14px 'Plus Jakarta Sans','Hind Siliguri',sans-serif;color:var(--ink);background:#fff;width:100%}
textarea{padding:8px 10px;min-height:60px}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
.pill{display:inline-block;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;background:#eef2f9;color:var(--muted)}
.st-new{background:#fde9e7;color:#b3261e}.st-called{background:#fff1d6;color:#8a4b00}.st-confirmed{background:#e6eeff;color:#1d4ed8}.st-delivered{background:#e3f6ec;color:#0f6b3c}.st-cancelled{background:#eceff3;color:#51607a}
.flash{padding:12px 16px;border-radius:12px;background:#e3f6ec;color:#0f6b3c;font-weight:600}
.err{padding:12px 16px;border-radius:12px;background:#fde9e7;color:#b3261e;font-weight:600}
.pagination{display:flex;flex-wrap:wrap;gap:6px;list-style:none;padding:0;margin:0}.pagination li>*{display:inline-flex;min-width:34px;height:34px;align-items:center;justify-content:center;border:1px solid var(--line);border-radius:8px;background:#fff;text-decoration:none;padding:0 8px}.pagination .active>*{background:var(--navy);color:#fff}
.tabs{display:flex;flex-wrap:wrap;gap:6px}
.tabs a{padding:6px 12px;border-radius:999px;text-decoration:none;border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:600;font-size:12px}
.tabs a.on{background:var(--navy);border-color:var(--navy);color:#fff}
@media (max-width:860px){.shell{flex-direction:column}.side{width:auto;border-right:0;border-bottom:1px solid var(--line)}.me{margin-top:8px}main{padding:16px}}
</style>
</head>
<body>
@php($u = auth()->user())
<div class="shell">
  <aside class="side" aria-label="Main menu">
    <div class="brand" style="flex-direction:column;align-items:stretch;gap:8px"><div style="display:flex;align-items:center;gap:10px"><img src="/img/logo.webp" alt="NK Corporation logo"><div><b>NK Corporation</b><small>BUSINESS CONTROL</small></div></div><span style="align-self:flex-start;background:#2a52a8;color:#fff;font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px">{{ now()->timezone('Asia/Dhaka')->format('F Y') }}</span></div>
    @unless($u->must_change_password)
    @php($newCount = \App\Models\WebsiteOrder::where('status', 'new')->count())
    @php($waitX = \App\Models\BrandExchange::where('status', 'waiting')->count())
    @php($adminOnly = ['company', 'users', 'activity', 'sms', 'hero'])
    @php($superOnly = ['supplier', 'accounts', 'vpl', 'import'])
    @php($mgrOnly = ['map', 'live'])
    @php($officeOnly = ['rdash', 'vehicles', 'loading', 'return', 'settle', 'closing', 'exp', 'export'])
    @php($cur = request()->route()?->getName() ?? '')
    @php($cur = ['trips.wizard' => 'trips.closing', 'trips.sales' => 'fleet.dash', 'trips.slip' => 'trips.loading'][$cur] ?? $cur)
    @php($allRoutes = collect(\App\Support\Roadmap::MENU)->flatten(1)->pluck(2)->filter())
    @php($activeRoute = $allRoutes->first(fn ($r) => $r === $cur) ?? $allRoutes->first(fn ($r) => str_starts_with($cur, explode('.', $r)[0].'.')))
    @foreach(\App\Support\Roadmap::MENU as $group => $items)
      <span class="grp">{{ $group }}</span>
      @foreach($items as [$key, $label, $route, $design])
        @continue(in_array($key, $adminOnly) && ! in_array($u->role, ['super_admin', 'admin']))
        @continue(in_array($key, $superOnly) && $u->role !== 'super_admin')
        @continue($key === 'hr' && ! in_array($u->role, ['super_admin', 'admin', 'accounts']))
        @continue(in_array($key, $mgrOnly) && ! in_array($u->role, ['super_admin', 'admin', 'manager']))
        @continue(in_array($key, $officeOnly) && ! in_array($u->role, ['super_admin', 'admin', 'manager', 'accounts']))
        @if($route)
          <a class="nav {{ $route === $activeRoute ? 'on' : '' }}" href="{{ route($route) }}">{!! \App\Support\Icons::svg($key) !!}<span style="flex-grow:1">{{ $label }}</span>
            @if($key === 'weborders' && $newCount)<span class="badge">{{ $newCount }}</span>@endif
            @if($key === 'brands' && $waitX)<span class="badge">{{ $waitX }}</span>@endif
          </a>
        @else
          <a class="nav soon {{ request()->is('soon/'.$key) ? 'on' : '' }}" href="{{ route('soon', $key) }}">{!! \App\Support\Icons::svg($key) !!}<span style="flex-grow:1">{{ $label }}</span><span class="later">soon</span></a>
        @endif
      @endforeach
    @endforeach
    <span class="grp">PROJECT</span>
    <a class="nav {{ request()->routeIs('roadmap') ? 'on' : '' }}" href="{{ route('roadmap') }}">{!! \App\Support\Icons::svg('activity') !!}<span style="flex-grow:1">Build progress</span></a>
    @endunless
    <div class="me">
      <div style="display:flex;align-items:center;gap:10px"><span style="width:32px;height:32px;border-radius:999px;background:#e6eeff;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">{{ collect(explode(' ', $u->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span><div style="display:flex;flex-direction:column;min-width:0"><b>{{ $u->name }}</b><span style="color:var(--muted)">{{ $u->roleLabel() }}</span></div></div>
      <a href="{{ route('password.edit') }}">Change password</a>
      <form method="post" action="{{ route('logout') }}">@csrf<button class="btn sm" type="submit">Log out</button></form>
    </div>
  </aside>
  <main>
    @if(session('ok'))<div class="flash">{{ session('ok') }}</div>@endif
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    @yield('content')
  </main>
</div>
<script src="/js/nk-table.js?v=1" defer></script>
</body>
</html>
