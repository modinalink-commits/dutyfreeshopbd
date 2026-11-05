<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#123a86"><meta name="robots" content="noindex">
<title>@yield('title', 'আমার হিসাব') · {{ \App\Models\Setting::get('company_name', 'NK Corporation') }}</title>
<link rel="icon" href="/img/logo.webp">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;700&display=swap">
<style>
*{box-sizing:border-box}html,body{margin:0;background:#eef2f9}
body{font-family:'Hind Siliguri','Plus Jakarta Sans',sans-serif;color:#14213d}
.app{max-width:480px;margin:0 auto;min-height:100dvh;background:#fff;display:flex;flex-direction:column}
.bar{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid #dbe3f0;position:sticky;top:0;background:#fff;z-index:5}
.bar .back{width:44px;height:44px;display:flex;align-items:center;justify-content:center;color:#123a86;text-decoration:none}
.bar .ttl{font-size:18px;font-weight:700;flex-grow:1}
.body{flex-grow:1;padding:12px 16px;display:flex;flex-direction:column;gap:14px}
.lb{font-size:13px;font-weight:600;color:#51607a}
.n{font-family:'Plus Jakarta Sans',sans-serif;font-variant-numeric:tabular-nums}
.chip{min-height:52px;border-radius:14px;cursor:pointer;font:700 15px 'Hind Siliguri',sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;border:1px solid #7b8aa8;background:#fff;color:#14213d;padding:0 6px}
.chip.on{border:2px solid #123a86;background:#e6eeff;color:#123a86}
.step{width:52px;height:52px;border-radius:14px;border:1px solid #7b8aa8;background:#fff;font:700 24px 'Plus Jakarta Sans',sans-serif;color:#123a86;cursor:pointer}
.num{font:700 30px 'Plus Jakarta Sans',sans-serif;min-width:56px;text-align:center}
.big{width:100%;min-height:56px;border:0;border-radius:16px;cursor:pointer;font:700 18px 'Hind Siliguri',sans-serif;color:#fff;background:#b45309;display:flex;align-items:center;justify-content:center;text-decoration:none}
.inp{width:100%;min-height:52px;border:1px solid #7b8aa8;border-radius:12px;padding:0 12px;font:700 20px 'Plus Jakarta Sans',sans-serif;color:#14213d}
.txt{width:100%;min-height:48px;border:1px solid #7b8aa8;border-radius:12px;padding:0 12px;font:500 15px 'Hind Siliguri',sans-serif}
.card{border:1px solid #dbe3f0;border-radius:14px;padding:12px}
.pill{font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px}
.ok{background:#e3f6ec;color:#0f6b3c;border-radius:12px;padding:10px 12px;font-size:14px;font-weight:600}.er{background:#fde9e7;color:#b3261e;border-radius:12px;padding:10px 12px;font-size:14px;font-weight:600}
nav.tabs{position:sticky;bottom:0;background:#fff;border-top:1px solid #dbe3f0;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));padding:6px 6px calc(10px + env(safe-area-inset-bottom))}
nav.tabs a{min-height:54px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;font-size:12px;text-decoration:none;border-radius:12px;font-weight:500;color:#51607a}
nav.tabs a.on{font-weight:700;color:#123a86;background:#e6eeff}
button:focus-visible,a:focus-visible,input:focus-visible{outline:2px solid #1d4ed8;outline-offset:2px}
</style>
</head>
<body>
<div class="app">
@yield('bar')
<main class="body">
@if(session('ok'))<div class="ok">{{ session('ok') }}</div>@endif
@if($errors->any())<div class="er">{{ $errors->first() }}</div>@endif
@yield('content')
</main>
@unless(isset($noNav))
@php($tab = $tab ?? 'home')
<nav class="tabs" aria-label="মেনু">
  @foreach([['home', 'হোম', 'my.home', 'M3 11l9-7 9 7M5 10v10h5v-6h4v6h5V10'], ['buy', 'ক্রয়', 'my.purchases', 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6'], ['pay', 'পেমেন্ট', 'my.payments', 'M2 6h20v12H2zM12 15a3 3 0 100-6 3 3 0 000 6z'], ['empty', 'খালি', 'my.empty', 'M8 3h8M9 3v3M15 3v3M6 9a3 3 0 013-3h6a3 3 0 013 3v10a2 2 0 01-2 2H8a2 2 0 01-2-2z'], ['order', 'অর্ডার', 'my.order', 'M12 5v14M5 12h14']] as [$k, $l, $r, $ic])
    <a href="{{ route($r) }}" class="{{ $tab === $k ? 'on' : '' }}" @if($tab === $k) aria-current="page" @endif><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $ic }}"></path></svg><span>{{ $l }}</span></a>
  @endforeach
</nav>
@endunless
</div>
@stack('js')
</body>
</html>
